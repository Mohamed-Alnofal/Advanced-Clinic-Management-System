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
        Schema::create('doctor_monthly_schedules', function (Blueprint $table) {

            $table->id();

            $table->foreignId('doctor_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();

            // مصدر السجل
            $table->foreignId('doctor_schedule_id')
                ->nullable()
                ->constrained('doctor_schedules')
                ->nullOnDelete();

            $table->date('date');

            $table->time('start_time');

            $table->time('end_time');

            $table->integer('consultation_duration');

            $table->decimal('price',10,2);

            /*
            available
            holiday
            closed
            */
            $table->string('status')
                ->default('available');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_monthly_schedules');
    }
};
