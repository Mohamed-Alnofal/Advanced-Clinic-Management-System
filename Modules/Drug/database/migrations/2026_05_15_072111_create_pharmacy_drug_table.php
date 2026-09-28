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
        Schema::create('pharmacy_drug', function (Blueprint $table) {
          
            $table->id();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('drug_id')
                ->constrained()
                ->cascadeOnDelete();

            // 💰 السعر
            $table->decimal('price', 10, 2);

            // 📦 الكمية
            $table->integer('quantity')->default(0);

            // ✅ التوفر
            $table->boolean('is_available')
                ->default(true);

                    // Index
            $table->index(['drug_id', 'pharmacy_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_drug');
    }
};
