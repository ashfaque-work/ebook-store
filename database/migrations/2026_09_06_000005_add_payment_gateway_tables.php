<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('gateway', 32)->nullable()->after('currency');
            $table->string('gateway_order_id')->nullable()->index()->after('gateway');
            $table->string('gateway_payment_id')->nullable()->index()->after('gateway_order_id');
            $table->string('failure_reason')->nullable()->after('gateway_payment_id');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->unsignedBigInteger('refunded_paise')->default(0)->after('refunded_at');
        });

        Schema::table('users', function (Blueprint $table) {
            // Place of supply for GST. Two-character state code.
            $table->char('state_code', 2)->nullable()->after('role');
        });

        // Every attempt against the gateway, successful or not. One order can
        // have several: a declined card, then a UPI payment that works.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('gateway_payment_id')->unique();
            $table->string('status', 32);              // created|captured|failed|refunded
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('refunded_paise')->default(0);
            $table->string('method', 32)->nullable();  // upi|card|netbanking|wallet
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        // Webhook idempotency. The row is inserted before the event is acted
        // on, so a replay collides on event_id and becomes a no-op.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');

        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('state_code'));

        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['gateway_order_id']);
            $t->dropIndex(['gateway_payment_id']);
            $t->dropColumn([
                'gateway', 'gateway_order_id', 'gateway_payment_id',
                'failure_reason', 'refunded_at', 'refunded_paise',
            ]);
        });
    }
};
