<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // جدول المنتجات (بناءً على تفاصيل الصورة Screenshot (159).jpg)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم المنتج التجاري (Erbitux)
            $table->string('labeller'); // الشركة المصنعة (ImClone LLC)
            $table->string('dosage_form'); // شكل الجرعة (Solution)
            $table->string('strength'); // القوة (2 mg/mL)
            $table->string('route'); // طريقة الإعطاء (Intravenous)
            $table->boolean('generic')->default(false); // هل هو دواء جنيس؟
            $table->boolean('otc')->default(false); // هل هو دواء بدون وصفة؟
            $table->unsignedBigInteger('drug_id'); // المعرف العلمي للدواء
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
