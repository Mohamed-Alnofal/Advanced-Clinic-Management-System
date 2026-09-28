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
    Schema::table('doctor_schedules', function (Blueprint $table) {
        $table->enum('consultation_type', ['clinic', 'video'])
              ->default('clinic')
              ->after('clinic_id');
    });
}

public function down(): void
{
    Schema::table('doctor_schedules', function (Blueprint $table) {
        $table->dropColumn('consultation_type');
    });
}
};
