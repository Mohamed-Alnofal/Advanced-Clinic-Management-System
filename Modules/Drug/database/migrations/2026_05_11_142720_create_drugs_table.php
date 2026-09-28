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
        Schema::create('drugs', function (Blueprint $table) {
        $table->id(); 
        $table->text('name');
        $table->text('description')->nullable();
        $table->text('state')->nullable();
        $table->text('indication')->nullable();
        $table->text('pharmacodynamics')->nullable();
        $table->text('toxicity')->nullable();
        $table->text('half_life')->nullable();
        $table->text('route_of_elimination')->nullable();
        $table->text('clearance')->nullable();
        $table->text('attribute')->nullable(); 
        $table->integer('quantity')->default(0);

            // Index
        $table->index('name');
        
        $table->timestamps();
    });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
