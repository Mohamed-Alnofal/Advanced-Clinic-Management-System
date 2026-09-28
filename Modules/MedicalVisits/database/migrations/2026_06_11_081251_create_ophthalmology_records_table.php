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
        Schema::create('ophthalmology_records', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('medical_visit_id');
            $table->foreign('medical_visit_id')
                ->references('id')
                ->on('medical_visits')
                ->cascadeOnDelete();

            $table->string('visual_acuity_od')->nullable();
            $table->string('visual_acuity_os')->nullable();

            $table->decimal('iop_od',5,2)->nullable();
            $table->decimal('iop_os',5,2)->nullable();

            $table->text('cornea')->nullable();
            $table->text('lens')->nullable();
            $table->text('retina')->nullable();
            $table->text('optic_disc')->nullable();
            $table->text('slit_lamp_exam')->nullable();
            $table->text('refraction')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ophthalmology_records');
    }
};
