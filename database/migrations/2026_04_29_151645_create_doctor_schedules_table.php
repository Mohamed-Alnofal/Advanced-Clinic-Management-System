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
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();

            $table->enum('day', [
                'sunday','monday','tuesday','wednesday','thursday','friday','saturday'
            ]);

            $table->time('start_time');
            $table->time('end_time');

            $table->integer('consultation_duration'); // 🔥 هذا الحقل الجديد (بالدقائق)
            $table->decimal('price', 8, 2)->nullable();
            $table->boolean('is_booked')
                    ->default(false);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};
