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
        Schema::create('patient_access_requests', function (Blueprint $table) {

            $table->id();

            // الطبيب الطالب
            $table->foreignId('doctor_id')
                ->constrained('doctors')
                ->cascadeOnDelete();

            // المريض
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            // الملف الطبي
            $table->foreignId('patient_file_id')
                ->constrained('patient_files')
                ->cascadeOnDelete();

            // 🔥 أهم حقل: الزيارة المطلوبة
            $table->foreignId('medical_visit_id')
                ->constrained('medical_visits')
                ->cascadeOnDelete();

            // حالة الطلب
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_access_requests');
    }
};
