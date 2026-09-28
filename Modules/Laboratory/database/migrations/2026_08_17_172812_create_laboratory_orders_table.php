<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_orders', function (Blueprint $table) {

            $table->id();

            /*
             * الطبيب الذي أنشأ طلب التحليل
             */
            $table->foreignId('doctor_id')
                ->constrained('doctors')
                ->cascadeOnDelete();

            /*
             * المريض
             *
             * يتم استخراجه من patient_file
             * ولا يرسله Flutter مباشرة
             */
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            /*
             * الملف الطبي
             */
            $table->foreignId('patient_file_id')
                ->constrained('patient_files')
                ->cascadeOnDelete();

            /*
             * الزيارة الطبية التي صدر منها الطلب
             */
            $table->foreignId('medical_visit_id')
                ->constrained('medical_visits')
                ->cascadeOnDelete();

            /*
             * المخبر الذي سيتم إرسال الطلب إليه
             */
            $table->foreignId('laboratory_id')
                ->constrained('laboratories')
                ->cascadeOnDelete();

            /*
             * ملاحظات الطبيب
             */
            $table->text('doctor_notes')
                ->nullable();

            /*
             * حالة الطلب
             */
            $table->enum('status', [
                'pending',
                'accepted',
                'rejected',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('pending');

            /*
             * سبب رفض الطلب
             */
            $table->text('rejection_reason')
                ->nullable();

            $table->timestamps();

            /*
             * Indexes
             */
            $table->index('doctor_id');
            $table->index('patient_id');
            $table->index('patient_file_id');
            $table->index('medical_visit_id');
            $table->index('laboratory_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_orders');
    }
};