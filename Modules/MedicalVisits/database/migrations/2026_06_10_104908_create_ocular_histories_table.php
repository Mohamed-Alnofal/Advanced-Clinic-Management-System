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
        Schema::create('ocular_histories', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('patient_file_id');
            $table->foreign('patient_file_id')
                ->references('id')
                ->on('patient_files')
                ->cascadeOnDelete();

            $table->string('condition'); 
            // مثال: myopia, cataract, glaucoma

            $table->date('diagnosed_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocular_histories');
    }
};
