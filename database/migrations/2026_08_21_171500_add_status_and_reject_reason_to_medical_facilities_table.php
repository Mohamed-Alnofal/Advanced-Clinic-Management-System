<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_facilities', function (Blueprint $table) {
            if (! Schema::hasColumn('medical_facilities', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])
                    ->default('pending')
                    ->after('type');
            }

            if (! Schema::hasColumn('medical_facilities', 'reject_reason')) {
                $table->text('reject_reason')
                    ->nullable()
                    ->after('status');
            }
        });

        // Existing verified facilities should stay usable in search/booking.
        if (Schema::hasColumn('medical_facilities', 'status')) {
            \Illuminate\Support\Facades\DB::table('medical_facilities')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update(['status' => 'pending']);

            \Illuminate\Support\Facades\DB::table('medical_facilities')
                ->where('is_verified', true)
                ->where('status', 'pending')
                ->update(['status' => 'approved']);
        }
    }

    public function down(): void
    {
        Schema::table('medical_facilities', function (Blueprint $table) {
            if (Schema::hasColumn('medical_facilities', 'reject_reason')) {
                $table->dropColumn('reject_reason');
            }
            if (Schema::hasColumn('medical_facilities', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
