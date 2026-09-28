<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('emergency_requests', 'sos_signal_id')) {
                $table->foreignId('sos_signal_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('emergency_sos_signals')
                    ->nullOnDelete();
            }
        });

        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });

        Schema::create('emergency_medical_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_signal_id')
                ->constrained('emergency_sos_signals')
                ->cascadeOnDelete();
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();
            $table->json('vitals_json')->nullable();
            $table->json('allergies_json')->nullable();
            $table->json('chronic_conditions_json')->nullable();
            $table->json('last_visits_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('break_glass_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accessor_id');
            $table->string('accessor_type', 32);
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();
            $table->foreignId('sos_signal_id')
                ->nullable()
                ->constrained('emergency_sos_signals')
                ->nullOnDelete();
            $table->string('reason');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['patient_id', 'created_at']);
            $table->index(['sos_signal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_glass_access_logs');
        Schema::dropIfExists('emergency_medical_snapshots');

        Schema::table('emergency_requests', function (Blueprint $table) {
            if (Schema::hasColumn('emergency_requests', 'sos_signal_id')) {
                $table->dropConstrainedForeignId('sos_signal_id');
            }
            $table->dropIndex(['user_id', 'status']);
        });
    }
};
