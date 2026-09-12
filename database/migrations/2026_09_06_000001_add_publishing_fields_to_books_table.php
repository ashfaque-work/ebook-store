<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // A sold book must never be deleted (it would strand every buyer),
            // so unpublishing is the way a book leaves the catalogue.
            $table->boolean('is_published')->default(false)->after('price');
            $table->timestamp('published_at')->nullable()->after('is_published');
            $table->boolean('is_featured')->default(false)->after('published_at');

            $table->string('language', 12)->default('en')->after('description');
            $table->string('isbn', 20)->nullable()->after('language');
            $table->unsignedInteger('page_count')->nullable()->after('isbn');

            $table->string('file_format', 8)->default('pdf')->after('file_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_format');
            // Free preview, private disk. Used by the reader in Phase C.
            $table->string('sample_path')->nullable()->after('file_size');

            $table->index(['is_published', 'created_at']);
            $table->index(['genre_id', 'is_published']);
        });

        // Everything already in the catalogue was live before this column existed.
        DB::table('books')->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Same trap as the orders migration: InnoDB uses the (genre_id, …)
        // composite index to satisfy the genre_id foreign key and will not let
        // it go while the constraint stands. SQLite does not care, so only a
        // rollback on MySQL surfaces this.
        $onMysql = DB::getDriverName() === 'mysql';

        if ($onMysql) {
            Schema::table('books', fn (Blueprint $table) => $table->dropForeign(['genre_id']));
        }

        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'created_at']);
            $table->dropIndex(['genre_id', 'is_published']);
        });

        if ($onMysql) {
            Schema::table('books', function (Blueprint $table) {
                $table->foreign('genre_id')->references('id')->on('genres')->cascadeOnDelete();
            });
        }

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'is_published', 'published_at', 'is_featured',
                'language', 'isbn', 'page_count',
                'file_format', 'file_size', 'sample_path',
            ]);
        });
    }
};
