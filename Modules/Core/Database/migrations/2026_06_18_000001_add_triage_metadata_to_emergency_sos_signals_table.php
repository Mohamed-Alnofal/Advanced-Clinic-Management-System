<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_sos_signals', function (Blueprint $table) {
            $table->string('detected_category')->nullable()->after('suspected_condition');
            $table->json('warning_signs')->nullable()->after('first_aid_steps');
            $table->json('do_not')->nullable()->after('warning_signs');
            $table->string('ai_confidence', 20)->nullable()->after('do_not');
            $table->string('ai_source', 40)->nullable()->after('ai_confidence');
            $table->boolean('call_emergency_now')->default(false)->after('ai_source');
            $table->unsignedTinyInteger('rules_score')->nullable()->after('call_emergency_now');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_sos_signals', function (Blueprint $table) {
            $table->dropColumn([
                'detected_category',
                'warning_signs',
                'do_not',
                'ai_confidence',
                'ai_source',
                'call_emergency_now',
                'rules_score',
            ]);
        });
    }
};
