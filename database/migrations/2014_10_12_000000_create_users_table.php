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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->unique();
            $table->integer('age')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();       
            $table->boolean('is_verified')->default(false);
            $table->text('fcm_token')->nullable();
            $table->string('otp', 6)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            // $table->enum('status', ['active', 'suspended'])->default('active');
            $table->enum('status', [
                'active',
                'pending',
                'accepted',
                'rejected',
                'suspended'
            ])->default('active');
            $table->string('suspension_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
