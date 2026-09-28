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
                Schema::create('appointments', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
    $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();

    // 🔥 المهم الجديد
    $table->foreignId('schedule_id')
        ->constrained('doctor_schedules')
        ->cascadeOnDelete();

    $table->enum('booking_for', ['self', 'relative'])->default('self');


    $table->enum('status', [
        'pending',
        'accepted',
        'rejected',
        'cancelled',
        'completed'
    ])->default('pending');

    $table->text('reject_reason')->nullable();
    $table->text('cancel_reason')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
