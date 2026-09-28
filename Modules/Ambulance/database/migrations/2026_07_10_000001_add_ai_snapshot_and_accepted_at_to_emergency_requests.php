<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('emergency_requests', 'severity_level')) {
                $table->string('severity_level')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('emergency_requests', 'suspected_condition')) {
                $table->string('suspected_condition')->nullable()->after('severity_level');
            }
            if (! Schema::hasColumn('emergency_requests', 'first_aid_steps')) {
                $table->json('first_aid_steps')->nullable()->after('suspected_condition');
            }
            if (! Schema::hasColumn('emergency_requests', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('requested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $cols = ['severity_level', 'suspected_condition', 'first_aid_steps', 'accepted_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('emergency_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
