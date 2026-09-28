<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('clinic_join_requests');

        Schema::table('medical_facilities', function (Blueprint $table) {
            if (Schema::hasColumn('medical_facilities', 'owner_doctor_id')) {
                $table->dropConstrainedForeignId('owner_doctor_id');
            }
            if (Schema::hasColumn('medical_facilities', 'is_shared')) {
                $table->dropColumn('is_shared');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medical_facilities', function (Blueprint $table) {
            if (! Schema::hasColumn('medical_facilities', 'is_shared')) {
                $table->boolean('is_shared')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('medical_facilities', 'owner_doctor_id')) {
                $table->foreignId('owner_doctor_id')
                    ->nullable()
                    ->after('is_shared')
                    ->constrained('doctors')
                    ->nullOnDelete();
            }
        });

        Schema::create('clinic_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_facility_id')->constrained('medical_facilities')->cascadeOnDelete();
            $table->foreignId('requester_doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('invited_by_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->enum('type', ['join_request', 'invite'])->default('join_request');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }
};
