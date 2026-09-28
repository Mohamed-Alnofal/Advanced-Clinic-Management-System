<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_reference_ranges', function (Blueprint $table) {
            $table->id();
            
            // ========== معلومات الفحص ==========
            $table->string('test_name');              // اسم الفحص (عربي)
            $table->string('test_key')->unique();     // المفتاح الفريد (مثل hemoglobin)
            $table->string('unit')->nullable();       // وحدة القياس
            
            // ========== النطاق الطبيعي ==========
            $table->decimal('min_value', 10, 2)->nullable();
            $table->decimal('max_value', 10, 2)->nullable();
            
            // ========== التصنيف حسب الجنس والعمر ==========
            $table->enum('gender', ['male', 'female', 'all'])->default('all');
            $table->integer('age_min')->nullable();
            $table->integer('age_max')->nullable();
            
            // ========== معلومات إضافية ==========
            $table->text('description')->nullable();   // وصف الفحص
            $table->text('advice_low')->nullable();    // نصائح عند الانخفاض
            $table->text('advice_high')->nullable();   // نصائح عند الارتفاع
            $table->text('interpretation_low')->nullable();   // تفسير الانخفاض
            $table->text('interpretation_high')->nullable();  // تفسير الارتفاع
            $table->text('interpretation_normal')->nullable(); // تفسير الطبيعي
            
            $table->timestamps();
            
            // ========== الفهارس ==========
            $table->index('test_key');
            $table->index(['gender', 'age_min', 'age_max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_reference_ranges');
    }
};