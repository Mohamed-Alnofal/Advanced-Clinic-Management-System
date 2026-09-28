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
        Schema::create('attachments', function (Blueprint $table) {

            $table->id();
            $table->unsignedBigInteger('medical_visit_id');
            $table->foreign('medical_visit_id')
                ->references('id')
                ->on('medical_visits')
                ->cascadeOnDelete();
            $table->string('file');

            $table->enum('type',[
                'xray',
                'analysis',
                'report',
                'other'
            ]);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
