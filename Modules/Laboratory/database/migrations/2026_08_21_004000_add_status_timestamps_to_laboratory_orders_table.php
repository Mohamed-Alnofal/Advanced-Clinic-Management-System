<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('laboratory_orders')) {
            return;
        }

        Schema::table('laboratory_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('laboratory_orders', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('rejection_reason');
            }
            if (! Schema::hasColumn('laboratory_orders', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('accepted_at');
            }
            if (! Schema::hasColumn('laboratory_orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (! Schema::hasColumn('laboratory_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('laboratory_orders')) {
            return;
        }

        Schema::table('laboratory_orders', function (Blueprint $table) {
            foreach (['accepted_at', 'started_at', 'completed_at', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('laboratory_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
