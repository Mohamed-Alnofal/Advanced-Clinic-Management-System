<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create('subscriptions', function (Blueprint $table) {

    $table->id();

    $table->unsignedBigInteger('subscription_plan_id');

    $table->morphs('subscriber');

    $table->dateTime('starts_at')->nullable();
    $table->dateTime('ends_at')->nullable();

    $table->enum('status', [
        'pending',
        'active',
        'expired',
        'cancelled',
        ])->default('pending');
        
    $table->boolean('auto_renew')->default(false);

    $table->boolean('cancel_at_period_end')
        ->default(false);

    $table->timestamps();

    $table->index([
        'subscriber_type',
        'subscriber_id',
        'status',
    ]);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};