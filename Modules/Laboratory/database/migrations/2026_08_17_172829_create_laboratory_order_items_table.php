<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_order_items', function (Blueprint $table) {

            $table->id();

            /*
             * طلب التحليل
             */
            $table->foreignId('laboratory_order_id')
                ->constrained('laboratory_orders')
                ->cascadeOnDelete();

            /*
             * اسم التحليل
             */
            $table->string('test_name');

            /*
             * ملاحظة خاصة بهذا التحليل
             */
            $table->text('doctor_notes')
                ->nullable();

            /*
             * حالة التحليل نفسه
             */
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
            ])->default('pending');

            $table->timestamps();

            $table->index('laboratory_order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_order_items');
    }
};