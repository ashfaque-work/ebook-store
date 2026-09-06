<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds get their own rows for the same reason payments do: an admin action
 * and the gateway's webhook both report the same refund, and incrementing a
 * counter from each would double-count it. The unique gateway_refund_id makes
 * applying a refund idempotent, and the order's refunded total is derived by
 * summing these rows rather than being accumulated in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_refund_id')->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('reason')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
