<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_drug', function (Blueprint $table) {

            $table->unique(
                ['pharmacy_id', 'drug_id'],
                'pharmacy_drug_unique'
            );

        });
    }

    public function down(): void
    {
        Schema::table('pharmacy_drug', function (Blueprint $table) {

            $table->dropUnique(
                'pharmacy_drug_unique'
            );

        });
    }
};