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
        Schema::create('external_identifiers', function (Blueprint $table) {
            $table->id();
            $table->text('drugbank_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('resource'); // مثل Drugs.com
            $table->text('url'); // الرابط الكامل
            $table->unsignedBigInteger('drug_id'); // سنربطه لاحقاً
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_identifiers');
    }
};
