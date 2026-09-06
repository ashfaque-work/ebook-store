<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money becomes an integer number of paise.
 *
 * Every Indian payment gateway takes and returns integer paise. Keeping
 * decimal(8,2) here would mean converting at the boundary on every request,
 * and a one-paisa drift is enough to fail a signature comparison and leave a
 * customer charged for an order the application believes is unpaid.
 *
 * Done before the gateway integration rather than after: retrofitting a
 * currency representation under a live gateway is miserable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->unsignedBigInteger('price_paise')->default(0)->after('price');
            // GST on ebooks is 5% where a printed edition of the title exists
            // and 18% otherwise, so the rate belongs on the book.
            $table->decimal('tax_rate', 5, 2)->default(18.00)->after('price_paise');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price_paise')->default(0)->after('price');
            $table->unsignedBigInteger('subtotal_paise')->default(0)->after('price_paise');
            $table->unsignedBigInteger('tax_paise')->default(0)->after('subtotal_paise');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_paise');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('total_paise')->default(0)->after('total');
            $table->unsignedBigInteger('subtotal_paise')->default(0)->after('total_paise');
            $table->unsignedBigInteger('tax_paise')->default(0)->after('subtotal_paise');
            $table->char('currency', 3)->default('INR')->after('tax_paise');
            // cgst_sgst when the buyer is in our own state, igst otherwise.
            $table->string('tax_type', 12)->nullable()->after('currency');
            $table->char('buyer_state_code', 2)->nullable()->after('tax_type');
            $table->string('invoice_number')->nullable()->unique()->after('order_number');
        });

        // Backfill in SQL so it does not depend on the application booting.
        DB::statement('UPDATE books SET price_paise = CAST(ROUND(price * 100) AS '.$this->intType().')');
        DB::statement('UPDATE order_items SET price_paise = CAST(ROUND(price * 100) AS '.$this->intType().')');
        DB::statement('UPDATE order_items SET subtotal_paise = price_paise');
        DB::statement('UPDATE orders SET total_paise = CAST(ROUND(total * 100) AS '.$this->intType().')');
        DB::statement('UPDATE orders SET subtotal_paise = total_paise');

        // Drop the decimal columns separately: SQLite rebuilds the table for
        // each drop and does not like several in one statement.
        Schema::table('books', fn (Blueprint $table) => $table->dropColumn('price'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('price'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('total'));

        // Gapless, per-financial-year invoice numbering. A counter table makes
        // the sequence safe under concurrency; MAX(invoice_number) + 1 is not.
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 9)->unique();   // e.g. 2026-27
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');

        Schema::table('books', fn (Blueprint $t) => $t->decimal('price', 8, 2)->default(0));
        Schema::table('order_items', fn (Blueprint $t) => $t->decimal('price', 8, 2)->default(0));
        Schema::table('orders', fn (Blueprint $t) => $t->decimal('total', 8, 2)->default(0));

        DB::statement('UPDATE books SET price = price_paise / 100.0');
        DB::statement('UPDATE order_items SET price = price_paise / 100.0');
        DB::statement('UPDATE orders SET total = total_paise / 100.0');

        Schema::table('books', fn (Blueprint $t) => $t->dropColumn(['price_paise', 'tax_rate']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['price_paise', 'subtotal_paise', 'tax_paise', 'tax_rate']));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropUnique(['invoice_number']);
            $t->dropColumn(['total_paise', 'subtotal_paise', 'tax_paise', 'currency', 'tax_type', 'buyer_state_code', 'invoice_number']);
        });
    }

    /** SQLite spells the integer cast differently from MySQL. */
    private function intType(): string
    {
        return DB::getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
    }
};
