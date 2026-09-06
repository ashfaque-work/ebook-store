<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An opening passage, distinct from the blurb.
 *
 * A bookshop's most characteristic moment is opening a book, so the home page
 * leads with the writing rather than with marketing copy. That needs the
 * actual first lines, which a description does not give us.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('excerpt');
        });
    }
};
