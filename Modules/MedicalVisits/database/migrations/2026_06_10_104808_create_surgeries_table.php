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
       Schema::create('surgeries', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('patient_file_id');
            $table->foreign('patient_file_id')
                ->references('id')
                ->on('patient_files')
                ->cascadeOnDelete();

            $table->string('surgery_name');

            $table->date('surgery_date')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surgeries');
    }
};
