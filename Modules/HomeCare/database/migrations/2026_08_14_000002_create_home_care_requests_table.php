<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_care_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('home_care_profile_id')->nullable()->constrained('home_care_profiles')->nullOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained('home_care_specialties')->nullOnDelete();
            $table->string('patient_name')->nullable();
            $table->date('requested_date');
            $table->time('requested_time');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 10, 8)->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled', 'completed'])->default('pending');
            $table->decimal('visit_fee', 10, 2)->nullable();
            $table->text('reject_reason')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_care_requests');
    }
};
