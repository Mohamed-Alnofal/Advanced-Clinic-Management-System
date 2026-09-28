<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->unique('user_id');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_transactions', 'type')) {
                DB::statement("ALTER TABLE wallet_transactions MODIFY type ENUM('deposit','withdraw','hold','refund','payment','payout') NOT NULL");
            }
            if (! Schema::hasColumn('wallet_transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('status');
            }
            $table->index(['wallet_id', 'status']);
        });

        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['held', 'captured', 'released'])->default('held');
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_holds');

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_transactions', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
            $table->dropIndex(['wallet_id', 'status']);
            DB::statement("ALTER TABLE wallet_transactions MODIFY type ENUM('deposit','withdraw') NOT NULL");
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
