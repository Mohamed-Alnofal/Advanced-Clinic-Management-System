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
        Schema::create('laboratory_results', function (Blueprint $table) {

            $table->id();

            // طلب التحاليل
            $table->foreignId('laboratory_order_id')
                ->constrained('laboratory_orders')
                ->cascadeOnDelete();

            // مسار الملف المخزن على السيرفر
            $table->string('result_file');

            // اسم الملف الأصلي الذي رفعه المخبر
            $table->string('original_file_name');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laboratory_results');
    }
};
