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
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'created_at']);
            $table->dropIndex(['genre_id', 'is_published']);

            $table->dropColumn([
                'is_published', 'published_at', 'is_featured',
                'language', 'isbn', 'page_count',
                'file_format', 'file_size', 'sample_path',
            ]);
        });
    }
};
