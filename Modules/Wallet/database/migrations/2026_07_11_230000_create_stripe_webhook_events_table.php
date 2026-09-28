<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('type')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('wallet_transactions')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('wallet_transactions', 'stripe_event_id')) {
                    $table->string('stripe_event_id')->nullable()->after('stripe_session_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');

        if (Schema::hasTable('wallet_transactions') && Schema::hasColumn('wallet_transactions', 'stripe_event_id')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropColumn('stripe_event_id');
            });
        }
    }
};
