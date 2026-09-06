<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            // An EPUB CFI, or a page number for PDF. Opaque to the server:
            // stored as given and never parsed here.
            $table->string('location', 512)->nullable();
            $table->unsignedTinyInteger('percent')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            // Makes progress sync an updateOrCreate rather than a race.
            $table->unique(['user_id', 'book_id']);
            $table->index(['user_id', 'last_read_at']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('location', 512);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'book_id']);
        });

        Schema::create('highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('location', 512);
            $table->text('text');
            $table->text('note')->nullable();
            $table->string('color', 16)->default('yellow');
            $table->timestamps();

            $table->index(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlights');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('reading_progress');
    }
};
