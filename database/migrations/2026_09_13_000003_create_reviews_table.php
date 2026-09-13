<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What readers thought.
 *
 * An unknown shop's hardest problem is that nobody has heard of it, and the
 * cheapest answer is other people's opinions. This store can do something most
 * cannot: it knows who actually read the book, so a review can say so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body')->nullable();

            // Recorded at the time of writing rather than worked out on
            // display: whether someone had read it when they reviewed it is a
            // fact about the review, and refunding a book later should not
            // rewrite history.
            $table->boolean('verified')->default(false);
            $table->unsignedTinyInteger('percent_read')->default(0);

            // Reviews are shown by default and hidden by an admin if they have
            // to be. A queue nobody empties is the same as no reviews at all.
            $table->timestamp('hidden_at')->nullable();
            $table->string('hidden_reason', 200)->nullable();

            $table->timestamps();

            // One person, one review, enforced by the database rather than by
            // remembering to check.
            $table->unique(['book_id', 'user_id']);
            $table->index(['book_id', 'hidden_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
