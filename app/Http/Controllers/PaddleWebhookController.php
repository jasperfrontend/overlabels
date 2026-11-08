<?php

namespace App\Http\Controllers;

use App\Models\PaddleCustomer;
use App\Models\PaddlePurchase;
use App\Models\User;
use App\Services\Paddle\PaddleSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives Paddle webhook deliveries for the Overlabels store.
 *
 * Order of business: verify the signature on the RAW body, then decode, then
 * route. Anything but a 2xx makes Paddle retry (up to 60 times over about two
 * days on live), so a failure to verify or to write is answered with an error
 * on purpose, never with 200.
 *
 * The store sells one-off products, not plans. transaction.completed grants a
 * product, an approved full refund or chargeback takes it back. Subscription
 * events are acknowledged and ignored: nothing in the catalogue recurs.
 */
class PaddleWebhookController extends Controller
{
    /** The custom_data key the checkout page sets to the buyer's user id. */
    public const USER_KEY = 'overlabels_user_id';

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.paddle.webhook_secret');

        if ($secret === '') {
            Log::error('Paddle webhook received but PADDLE_WEBHOOK_SECRET is not set');

            return response()->json(['error' => 'Not configured'], 500);
        }

        $rawBody = $request->getContent();

        if (! PaddleSignature::isValid($rawBody, (string) $request->header('Paddle-Signature', ''), $secret)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload) || ! is_string($payload['event_type'] ?? null) || ! is_array($payload['data'] ?? null)) {
            return response()->json(['error' => 'Malformed payload'], 400);
        }

        $eventType = $payload['event_type'];
        $data = $payload['data'];
        $occurredAt = $this->parseTime($payload['occurred_at'] ?? null) ?? now();

        DB::transaction(function () use ($eventType, $data, $occurredAt) {
            match ($eventType) {
                'transaction.completed' => $this->transactionCompleted($data, $occurredAt),
                'customer.created', 'customer.updated' => $this->upsertCustomer((string) ($data['id'] ?? ''), null, $occurredAt),
                'adjustment.created', 'adjustment.updated' => $this->adjustment($data, $occurredAt),
                default => null,
            };
        });

        return response()->json(['ok' => true]);
    }

    /** @param  array<string, mixed>  $data */
    private function transactionCompleted(array $data, Carbon $occurredAt): void
    {
        $transactionId = (string) ($data['id'] ?? '');

        if ($transactionId === '') {
            return;
        }

        $customerId = is_string($data['customer_id'] ?? null) ? $data['customer_id'] : null;
        $userId = $this->resolveUserId($data['custom_data'][self::USER_KEY] ?? null);

        if ($userId === null) {
            Log::warning('Paddle sale not tied to an Overlabels account', ['transaction_id' => $transactionId]);
        }

        if ($customerId !== null) {
            $this->upsertCustomer($customerId, $userId, $occurredAt);
        }

        $paidAt = $this->parseTime($data['billed_at'] ?? null) ?? $occurredAt;

        foreach ($this->lineItems($data) as $item) {
            $existing = PaddlePurchase::query()
                ->where('transaction_id', $transactionId)
                ->where('price_id', $item['price_id'])
                ->first();

            if ($existing?->paddle_occurred_at?->greaterThan($occurredAt)) {
                continue;
            }

            PaddlePurchase::query()->updateOrCreate(
                ['transaction_id' => $transactionId, 'price_id' => $item['price_id']],
                [
                    'product_id' => $item['product_id'],
                    'customer_id' => $customerId,
                    'user_id' => $userId ?? $existing?->user_id,
                    'status' => 'completed',
                    'amount' => $item['amount'],
                    'currency' => is_string($data['currency_code'] ?? null) ? $data['currency_code'] : null,
                    'paid_at' => $paidAt,
                    'paddle_occurred_at' => $occurredAt,
                ],
            );
        }
    }

    /**
     * An approved FULL refund or chargeback takes the product back; an approved
     * chargeback reversal gives it back. Partial refunds leave access alone.
     *
     * @param  array<string, mixed>  $data
     */
    private function adjustment(array $data, Carbon $occurredAt): void
    {
        $transactionId = (string) ($data['transaction_id'] ?? '');
        $action = $data['action'] ?? null;

        if ($transactionId === '' || ($data['status'] ?? null) !== 'approved') {
            return;
        }

        $purchases = PaddlePurchase::query()->where('transaction_id', $transactionId);

        if ($action === 'chargeback_reverse') {
            $purchases->update(['refunded_at' => null]);

            return;
        }

        if (in_array($action, ['refund', 'chargeback'], true) && ($data['type'] ?? null) === 'full') {
            $purchases->whereNull('refunded_at')->update(['refunded_at' => $occurredAt]);
        }
    }

    private function upsertCustomer(string $customerId, ?int $userId, Carbon $occurredAt): void
    {
        if ($customerId === '') {
            return;
        }

        $customer = PaddleCustomer::query()->find($customerId);

        if ($customer === null) {
            PaddleCustomer::query()->create([
                'customer_id' => $customerId,
                'user_id' => $userId,
                'paddle_occurred_at' => $occurredAt,
            ]);

            return;
        }

        // A late, older delivery may still link a user, but never moves the clock back.
        if ($userId !== null && $customer->user_id === null) {
            $customer->user_id = $userId;
        }

        if ($customer->paddle_occurred_at === null || $occurredAt->greaterThan($customer->paddle_occurred_at)) {
            $customer->paddle_occurred_at = $occurredAt;
        }

        $customer->save();
    }

    /**
     * The checkout sets custom_data from the signed-in session, but it travels
     * through the browser, so it is checked against a real account here.
     */
    private function resolveUserId(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        $id = (int) $value;

        return User::query()->whereKey($id)->exists() ? $id : null;
    }

    /**
     * details.line_items carries the per-item total; items is the fallback.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{price_id: string, product_id: string, amount: string|null}>
     */
    private function lineItems(array $data): array
    {
        $out = [];

        foreach ($data['details']['line_items'] ?? [] as $line) {
            $priceId = $line['price_id'] ?? null;
            $productId = $line['product']['id'] ?? null;

            if (is_string($priceId) && is_string($productId)) {
                $out[] = ['price_id' => $priceId, 'product_id' => $productId, 'amount' => isset($line['totals']['total']) ? (string) $line['totals']['total'] : null];
            }
        }

        if ($out !== []) {
            return $out;
        }

        foreach ($data['items'] ?? [] as $item) {
            $priceId = $item['price']['id'] ?? null;
            $productId = $item['price']['product_id'] ?? null;

            if (is_string($priceId) && is_string($productId)) {
                $out[] = ['price_id' => $priceId, 'product_id' => $productId, 'amount' => null];
            }
        }

        return $out;
    }

    private function parseTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
