<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulance_drivers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('ambulance_company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('phone')->unique();

            $table->string('email')->unique();

            $table->string('password');

            $table->boolean('is_active')->default(true);

            $table->rememberToken();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_drivers');
    }
};