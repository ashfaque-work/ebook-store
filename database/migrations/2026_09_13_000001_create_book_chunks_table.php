<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The searchable text of every book, cut into passages.
 *
 * Held separately from `books` because it is a different shape of thing: one
 * row per few hundred words, rebuilt whenever the file changes, and worthless
 * without the book it came from — hence the cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();

            // Reading order within the book, so a result can be put back in
            // context and neighbouring passages found.
            $table->unsignedInteger('position');

            // The document this came from, which is what the reader needs to
            // open the book at the right chapter.
            $table->string('section');
            $table->string('heading', 160)->nullable();

            $table->text('content');
            $table->unsignedSmallInteger('word_count');

            $table->index(['book_id', 'position']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Postgres does the searching itself. The column is generated rather
        // than filled in by the application, so it cannot drift from the text
        // it describes, and GIN is the index full-text search actually uses —
        // without it every query is a sequential scan of the whole catalogue.
        DB::statement("
            ALTER TABLE book_chunks
            ADD COLUMN searchable tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(heading, '')), 'B') ||
                setweight(to_tsvector('english', content), 'A')
            ) STORED
        ");

        DB::statement('CREATE INDEX book_chunks_searchable_index ON book_chunks USING GIN (searchable)');
    }

    public function down(): void
    {
        Schema::dropIfExists('book_chunks');
    }
};
