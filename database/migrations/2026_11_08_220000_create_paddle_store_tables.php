<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Overlabels store sells one-off products through Paddle, the merchant of
 * record. These two tables mirror what Paddle's webhooks report, so the app
 * can answer "does this account own that product" without calling Paddle.
 *
 * - `paddle_customers` maps a Paddle customer (ctm_) to an Overlabels user.
 *   It holds no email or address: the buyer is our own user, already known
 *   by their Twitch identity, and Paddle keeps the billing details.
 * - `paddle_purchases` is one row per line item of a completed transaction
 *   (txn_). The user comes from the checkout's custom_data; a row with no
 *   user is a sale that could not be tied to an account and needs a look.
 *
 * Paddle delivers at least once and out of order, so both tables are written
 * by upsert on Paddle's own ids, never by blind insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paddle_customers', function (Blueprint $table) {
            $table->string('customer_id', 50)->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Paddle's own occurred_at of the last event applied, so an older
            // delivery arriving late cannot overwrite a newer one.
            $table->timestamp('paddle_occurred_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('paddle_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 50);
            $table->string('price_id', 50);
            $table->string('product_id', 50);
            $table->string('customer_id', 50)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            // Lowest currency unit as Paddle sends it (cents), kept as a string.
            $table->string('amount', 20)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('paddle_occurred_at')->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'price_id']);
            $table->index(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paddle_purchases');
        Schema::dropIfExists('paddle_customers');
    }
};
