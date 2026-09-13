<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Room for a vector per passage, so passages can be found by meaning.
 *
 * Postgres only, and only where pgvector is installable. Everywhere else the
 * column simply does not exist and the search stays full-text — which is the
 * behaviour that matters, because the test suite runs on SQLite and must not
 * need an extension to do it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A note of which model wrote the vectors, so a change of provider is
        // detectable rather than silently mixing incomparable numbers.
        Schema::table('book_chunks', function (Blueprint $table) {
            $table->string('embedded_with', 80)->nullable();
        });

        if (DB::getDriverName() !== 'pgsql' || ! $this->vectorAvailable()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        $dimensions = (int) config('search.embeddings.dimensions', 768);

        DB::statement("ALTER TABLE book_chunks ADD COLUMN embedding vector({$dimensions})");

        // HNSW rather than IVFFlat: it needs no training pass, so it works on
        // an empty table and stays good as rows arrive. Cosine, because the
        // vectors are normalised and cosine is what the models are trained for.
        DB::statement('CREATE INDEX book_chunks_embedding_index ON book_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS book_chunks_embedding_index');
            DB::statement('ALTER TABLE book_chunks DROP COLUMN IF EXISTS embedding');
        }

        Schema::table('book_chunks', function (Blueprint $table) {
            $table->dropColumn('embedded_with');
        });
    }

    /**
     * Neon ships pgvector; a bare Postgres may not. Asking first turns "this
     * deploy cannot migrate" into "this deploy has no semantic search", which
     * is a far better failure.
     */
    private function vectorAvailable(): bool
    {
        try {
            return DB::select("SELECT 1 FROM pg_available_extensions WHERE name = 'vector'") !== [];
        } catch (Throwable) {
            return false;
        }
    }
};
