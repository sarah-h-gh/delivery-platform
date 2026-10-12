<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Filter active statuses, including the pagination count query.
            // Multiple statuses still require a global created_at sort.
            $table->index('status');

            // Driver equality first, then oldest-first ordering within that driver.
            // The existing driver foreign-key index cannot provide this ordering.
            $table->index(['driver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // MySQL/MariaDB may automatically discard the original foreign-key
        // index once the composite index covers it. Restore it before dropping.
        if (Schema::getConnection()->getDriverName() === 'mysql'
            && ! Schema::hasIndex('orders', ['driver_id'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('driver_id', 'orders_driver_id_foreign');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['driver_id', 'created_at']);
            $table->dropIndex(['status']);
        });
    }
};
