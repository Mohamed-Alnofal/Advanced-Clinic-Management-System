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
        if (!Schema::hasColumn('prescriptions', 'dispensing_type')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->enum('dispensing_type', [
                    'one_time',
                    'repeatable',
                ])
                ->default('repeatable')
                ->after('medical_visit_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prescriptions', 'dispensing_type')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropColumn('dispensing_type');
            });
        }
    }
};
