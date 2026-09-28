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
        Schema::create('consultations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('appointment_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // LiveKit Room Name
            $table->string('room_name')->unique();

            // LiveKit Room SID
            $table->string('livekit_room_sid')
                ->nullable();

            $table->enum('status', [
                'scheduled',
                'active',
                'completed',
                'cancelled'
            ])->default('scheduled');

            $table->timestamp('started_at')
                ->nullable();

            $table->timestamp('ended_at')
                ->nullable();

            $table->tinyInteger('rating')
                ->nullable();

            $table->longText('doctor_notes')
                ->nullable();

            $table->string('recording_url')
                ->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};