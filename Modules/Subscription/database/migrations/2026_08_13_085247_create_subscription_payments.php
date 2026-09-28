<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            /*
             * wallet
             * manual
             * stripe
             *
             * سنضيف Stripe لاحقًا.
             */
            $table->enum('method', [
                'wallet',
                'manual',
                'stripe',
            ]);

            $table->enum('status', [
                'pending',
                'paid',
                'failed',
                'rejected',
                'refunded',
            ])->default('pending');

            /*
             * إيصال الدفع اليدوي.
             */
            $table->string('receipt')
                ->nullable();

            /*
             * مرجع خارجي مستقبلًا:
             * Stripe / Bank Transfer ...
             */
            $table->string('reference')
                ->nullable();

            $table->string('stripe_session_id')
                ->nullable();

            $table->string('stripe_payment_intent_id')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'method',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};