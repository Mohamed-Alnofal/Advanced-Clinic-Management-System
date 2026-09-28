<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::create('ambulance_companies', function (Blueprint $table) {

        $table->id();

        // صاحب الشركة
        $table->foreignId('user_id')
            ->constrained()
            ->cascadeOnDelete();

        // المحافظة
        $table->foreignId('governorate_id')
            ->constrained()
            ->cascadeOnDelete();

        // اسم الشركة
        $table->string('name');

        // رقم الترخيص
        $table->string('license_number')->unique();

        // الهاتف
        $table->string('phone');

        // البريد الإلكتروني
        $table->string('email')->nullable();

        // العنوان
        $table->string('address');

        // الموقع
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();

        // الشعار
        $table->string('logo')->nullable();

        // وصف الشركة
        $table->text('description')->nullable();

        // ساعات العمل
        $table->time('work_from')->nullable();

        $table->time('work_to')->nullable();

        // هل تعمل 24 ساعة؟
        $table->boolean('is_24_hours')->default(false);

        // حالة طلب التسجيل
        $table->enum('status', [
            'pending',
            'approved',
            'rejected'
        ])->default('pending');

        // هل الشركة مفعلة
        $table->boolean('is_active')->default(true);

        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('ambulance_companies');
    }
};