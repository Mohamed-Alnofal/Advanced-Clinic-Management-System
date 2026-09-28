<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
     Schema::create('medical_facilities', function (Blueprint $table) {

            $table->id();

            $table->foreignId('governorate_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->enum('type', [
                'clinic',
                'pharmacy',
                'hospital',
                'ambulance'
            ]);

            // Request status
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending');

            // Rejection reason
            $table->text('reject_reason')
                ->nullable();

            $table->decimal('latitude', 10, 7)->nullable();

            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('address')->nullable();

            $table->string('phone')->nullable();

            $table->string('image')->nullable();

            $table->boolean('is_verified')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_facilities');
    }
};