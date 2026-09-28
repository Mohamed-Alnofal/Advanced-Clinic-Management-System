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
    Schema::create('emergency_requests', function (Blueprint $table) {

        $table->id();

        $table->foreignId('user_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->foreignId('ambulance_unit_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();

        $table->string('case_number')->unique();

        $table->foreignId('ambulance_company_id')
    ->nullable()
    ->constrained()
    ->nullOnDelete();

    $table->string('address')->nullable();

    $table->enum('priority', [
        'low',
        'medium',
        'high',
        'critical'
    ])->default('medium');

    $table->text('cancel_reason')->nullable();
        $table->text('description')->nullable();

        $table->decimal('latitude',10,7);

        $table->decimal('longitude',10,7);

        $table->enum('status',[
            'pending',
            'assigned',
            'arrived',
            'completed',
            'cancelled'
        ])->default('pending');

        $table->timestamp('requested_at')->nullable();

        $table->timestamp('arrived_at')->nullable();

        $table->timestamp('completed_at')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_requests');
    }
};
