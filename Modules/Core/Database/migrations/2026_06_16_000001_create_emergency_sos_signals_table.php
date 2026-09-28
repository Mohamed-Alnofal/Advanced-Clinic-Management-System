<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_sos_signals', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('patient_id')->nullable();
            $table->foreign('patient_id')
                ->references('id')
                ->on('patients')
                ->nullOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->text('raw_transcript');

            $table->enum('severity_level', ['Critical', 'Urgent', 'Stable']);

            $table->string('suspected_condition');

            $table->json('first_aid_steps');

            $table->timestamp('created_at')->useCurrent();

            $table->index('severity_level');
            $table->index('patient_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_sos_signals');
    }
};
