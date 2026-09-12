<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Derived from the buyer and the exact set of books being bought.
            // The unique index is the last line of defence against a
            // double-submitted checkout creating two orders.
            $table->string('idempotency_key', 64)->nullable()->unique()->after('order_number');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        // InnoDB satisfies the user_id foreign key with this composite index,
        // because user_id is its leftmost column, and refuses to drop it while
        // the constraint stands. The constraint has to step aside first.
        // SQLite does not enforce any of this, which is why the test suite
        // never caught it — only a rollback on MySQL does.
        $onMysql = DB::getDriverName() === 'mysql';

        if ($onMysql) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropForeign(['user_id']));
        }

        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex(['user_id', 'status']));

        if ($onMysql) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
