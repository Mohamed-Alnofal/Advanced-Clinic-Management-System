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
        Schema::create('vital_signs', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('patient_file_id');
            $table->foreign('patient_file_id')
                ->references('id')
                ->on('patient_files')
                ->cascadeOnDelete();

            $table->string('blood_type')->nullable();

            $table->string('rh_factor')->nullable();

            $table->decimal('height',5,2)->nullable();

            $table->decimal('weight',5,2)->nullable();

            $table->decimal('bmi',5,2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
};
