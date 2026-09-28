<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_care_specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('home_care_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained('home_care_specialties')->nullOnDelete();
            $table->string('provider_type')->default('general');
            $table->string('full_name');
            $table->string('license_number')->nullable();
            $table->string('certificate_path')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedInteger('experience_years')->default(0);
            $table->decimal('visit_fee', 10, 2)->default(0);
            $table->decimal('service_radius_km', 8, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'suspended'])->default('pending');
            $table->text('notes')->nullable();
            $table->json('available_days')->nullable();
            $table->string('available_hours')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_care_profiles');
        Schema::dropIfExists('home_care_specialties');
    }
};
