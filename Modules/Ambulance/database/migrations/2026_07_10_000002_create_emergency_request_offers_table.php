<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_request_offers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('emergency_request_id')
                ->constrained('emergency_requests')
                ->cascadeOnDelete();

            $table->foreignId('ambulance_unit_id')
                ->constrained('ambulance_units')
                ->cascadeOnDelete();

            $table->foreignId('ambulance_driver_id')
                ->constrained('ambulance_drivers')
                ->cascadeOnDelete();

            $table->enum('status', ['offered', 'accepted', 'rejected', 'expired'])
                ->default('offered');

            $table->timestamp('offered_at');
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['emergency_request_id', 'ambulance_unit_id'], 'er_offers_request_unit_unique');
            $table->index(['ambulance_driver_id', 'status'], 'er_offers_driver_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_request_offers');
    }
};
