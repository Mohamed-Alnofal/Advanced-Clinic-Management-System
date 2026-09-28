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
        Schema::create('doctor_day_actions', function(Blueprint $table){

        $table->id();


        $table->foreignId('doctor_id')
        ->constrained();


        $table->date('date');


        $table->enum('action',[

        'cancel',
        'delay'

        ]);


        $table->integer('delay_minutes')
        ->nullable();


        $table->text('reason')
        ->nullable();


        $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_day_actions');
    }
};
