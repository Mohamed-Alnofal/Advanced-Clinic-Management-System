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
        Schema::create('appointment_reschedules', function (Blueprint $table) {

            $table->id();


            $table->foreignId('appointment_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->date('old_date');

            $table->time('old_time');


            $table->date('new_date')
                ->nullable();


            $table->time('new_time')
                ->nullable();



            $table->enum('status',[
                'pending',
                'accepted',
                'rejected'
            ])
            ->default('pending');



            $table->text('reason')
                ->nullable();



            $table->foreignId('created_by')
                ->nullable();



            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_reschedules');
    }
};
