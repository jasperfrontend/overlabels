## OL-2611-004 - feat(store): Paddle fulfillment backend for one-off product purchases

**Shipped:** 2026-11-08
**Commit:** `git log --grep=OL-2611-004`

### Surface
- `app/Http/Controllers/PaddleWebhookController.php` - new file
- `app/Http/Controllers/Settings/BillingPortalController.php` - new file
- `app/Models/PaddleCustomer.php` - new file
- `app/Models/PaddlePurchase.php` - new file
- `app/Services/Paddle/PaddleSignature.php` - new file
- `database/migrations/2026_11_08_220000_create_paddle_store_tables.php` - new file
- `config/services.php` - `paddle` block
- `routes/api.php` - `POST /api/paddle/webhook`, named `webhooks.paddle`
- `routes/settings.php` - `GET /settings/billing/portal`, named `settings.billing.portal`
- `.env.example` - `PADDLE_ENVIRONMENT`, `PADDLE_API_KEY`, `PADDLE_CLIENT_TOKEN`, `PADDLE_WEBHOOK_SECRET`
- `tests/Feature/PaddleWebhookTest.php` - new file
- `tests/Feature/PaddleBillingPortalTest.php` - new file

### Claims
- **C1** [code] `PaddleSignature::isValid()` parses `ts` and every `h1` from the `Paddle-Signature` header and accepts when any `h1` equals HMAC-SHA256 of `"<ts>:<raw body>"` keyed with the secret, compared with `hash_equals`.
- **C2** [code] `PaddleSignature::isValid()` rejects a `ts` more than `TOLERANCE_SECONDS` (300) from now, an empty secret and an empty header.
- **C3** [code] `PaddleWebhookController::handle()` verifies against `$request->getContent()` before decoding, answers 401 on a bad signature, 500 when `services.paddle.webhook_secret` is empty, 400 on a body without `event_type` and `data`, and 200 otherwise.
- **C4** [code] `transaction.completed` upserts one `paddle_purchases` row per `details.line_items` entry on `(transaction_id, price_id)`, falling back to `items` when there are no line items, with `user_id` from `custom_data.overlabels_user_id` only when it names an existing user.
- **C5** [code] A `transaction.completed` whose `occurred_at` is older than the stored row's `paddle_occurred_at` does not overwrite it.
- **C6** [code] An `adjustment.*` with `status` approved, `type` full and `action` refund or chargeback sets `refunded_at` on the transaction's rows; an approved `chargeback_reverse` clears it. Partial and unapproved adjustments change nothing.
- **C7** [code] `customer.created` / `customer.updated` upsert `paddle_customers` by `customer_id` and store no email; `paddle_customers` has no email column.
- **C8** [code] `subscription.*` and every other event type are answered 200 and write nothing.
- **C9** [code] `PaddlePurchase::userOwns()` is true only for a row of that user and product with `status` completed and `refunded_at` null.
- **C10** [code] `BillingPortalController` resolves the Paddle customer from `$request->user()` only, 404s when the user has none, 503s with no API key, 502s when Paddle fails or returns a non-https URL, and otherwise returns `Inertia::location()` to `data.urls.general.overview`.
- **C11** [code] Both new tables reference `users` with `cascadeOnDelete`, so account erasure removes them.
- **C12** [test] `PaddleWebhookTest` (23 cases) and `PaddleBillingPortalTest` (4 cases) cover C1-C10.
- **C13** [unverified] The real sandbox `transaction.completed` event of 2026-11-08 (`txn_01m6ygy4mfkkx6cw10fgjbw7dt`), signed with the sandbox destination's secret and posted to the local app, was accepted and written; the same body with a bad signature got 401.
- **C14** [unverified] The controller minted a real sandbox portal session and redirected to `sandbox-customer-portal.paddle.com`.

### Unchanged
- No checkout, Buy button or link to the portal exists in the UI. Nothing creates a `paddle_customers` row except the webhook.
- No subscription state is stored.

### Risk
- Until `PADDLE_WEBHOOK_SECRET` is set on prod, every delivery to the prod route is answered 500 and Paddle retries it. The sandbox destination already points at `https://overlabels.com/api/paddle/webhook`.
