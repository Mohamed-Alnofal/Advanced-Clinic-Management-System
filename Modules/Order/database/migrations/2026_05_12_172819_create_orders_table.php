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
         Schema::create('orders', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pharmacist_id');

        
            $table->text('delivery_address');
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();

            $table->enum('status', [
                'pending',
                'accepted',
                'preparing',
                'ready',
                'on_the_way',
                'delivered',
                'rejected',
                'cancelled'
            ])->default('pending');


                        // 💰 الدفع
             $table->enum('payment_status', [
                'paid',
                'cash_on_delivery'
                ])->default('cash_on_delivery');
                
            $table->string('prescription_file')->nullable();
            $table->decimal('total_price', 8, 2)->nullable();

                    // Indexes
            $table->index('user_id');
            $table->index('pharmacist_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index(['user_id', 'status']);
            $table->index(['pharmacist_id', 'status']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
