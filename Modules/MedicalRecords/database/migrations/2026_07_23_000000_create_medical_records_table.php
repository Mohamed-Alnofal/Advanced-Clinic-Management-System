<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            
            // ========== العلاقات ==========
            $table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
            // ربط السجل بزيارة طبية (اختياري) ليصبح جزءًا من سجل الزيارات
            $table->foreignId('medical_visit_id')->nullable()->constrained('medical_visits')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('cascade');
            
            // ========== معلومات السجل ==========
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            
            // ========== معلومات الملف ==========
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            
            // ========== الحالة والنتائج ==========
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->json('report')->nullable();
            $table->json('extracted_data')->nullable();
            $table->text('error_message')->nullable();
            
            // ========== التواريخ ==========
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            
            // ========== 🚀 الفهارس (Indexes) ==========
            
            // فهارس عادية
            $table->index('patient_id');                 // لجلب سجلات مريض معين
            $table->index('status');                    // لتصفية حسب الحالة
            $table->index('created_at');                // للترتيب حسب التاريخ
            $table->index('analyzed_at');               // للبحث عن السجلات المحللة
            
            // فهارس مركبة (Composite Indexes)
            $table->index(['patient_id', 'status']);    // لجلب سجلات مريض بحالة معينة
            $table->index(['patient_id', 'created_at']);// لجلب سجلات مريض مرتبة زمنياً
            $table->index(['status', 'created_at']);    // للسجلات حسب الحالة والتاريخ
            
            // فهارس فريدة (Unique)
            // $table->unique(['patient_id', 'file_path']); // لمنع تكرار نفس الملف لنفس المريض (اختياري)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};