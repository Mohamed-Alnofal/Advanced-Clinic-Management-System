<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('emergency_requests', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('requested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            if (Schema::hasColumn('emergency_requests', 'escalated_at')) {
                $table->dropColumn('escalated_at');
            }
        });
    }
};
