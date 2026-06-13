<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Keep the book row if the catalog entry is later removed, so the
            // customer never loses access to something they paid for.
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            // Snapshot title + price at purchase time (catalog may change).
            $table->string('title');
            $table->decimal('price', 8, 2);
            $table->timestamps();

            // A book can appear only once per order.
            $table->unique(['order_id', 'book_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
