<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medical_visits', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('patient_file_id');
            $table->foreign('patient_file_id')
                ->references('id')
                ->on('patient_files')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')
                ->references('id')
                ->on('doctors')
                ->cascadeOnDelete();

            // $table->unsignedBigInteger('appointment_id')->nullable();
            // $table->foreign('appointment_id')
            //     ->references('id')
            //     ->on('appointments')
            //     ->nullOnDelete();

            $table->text('chief_complaint')->nullable();

            $table->text('diagnosis');

            $table->string('icd_code')->nullable();

            $table->longText('notes')->nullable();

            $table->text('treatment_plan')->nullable();

            $table->json('speciality_data')->nullable();

            $table->dateTime('visit_date');

            $table->enum('category', [
                'general',
                'cardiology',
                'dermatology',
                'psychiatry',
                'ophthalmology',
                'urology',
                'orthopedic',
                'other'
            ]);

            $table->enum('visibility', [
                'public',
                'approved_doctors',
                'private',
            ])->default('approved_doctors');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_visits');
    }
};
