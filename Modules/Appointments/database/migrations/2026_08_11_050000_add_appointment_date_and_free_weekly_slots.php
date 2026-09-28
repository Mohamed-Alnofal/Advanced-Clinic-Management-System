<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly doctor_schedules are templates (monday/tuesday/…).
 * Booking must lock a slot for a calendar date, not forever via is_booked.
 *
 * Safe: does NOT delete schedules or appointments — only adds a column,
 * backfills dates for existing bookings, and frees orphaned is_booked flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appointments') && ! Schema::hasColumn('appointments', 'appointment_date')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->date('appointment_date')->nullable()->after('schedule_id');
                $table->index(['schedule_id', 'appointment_date']);
            });
        }

        // Backfill date for existing active bookings so they keep locking correctly.
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'appointment_date')) {
            $rows = DB::table('appointments')
                ->whereNull('appointment_date')
                ->whereIn('status', ['pending', 'accepted', 'completed'])
                ->get(['id', 'created_at']);

            foreach ($rows as $row) {
                $date = $row->created_at
                    ? date('Y-m-d', strtotime((string) $row->created_at))
                    : date('Y-m-d');
                DB::table('appointments')->where('id', $row->id)->update([
                    'appointment_date' => $date,
                ]);
            }
        }

        // Free weekly templates that are no longer tied to an active booking.
        // Does NOT delete any doctor_schedules rows.
        if (Schema::hasTable('doctor_schedules') && Schema::hasTable('appointments')) {
            $activeScheduleIds = DB::table('appointments')
                ->whereIn('status', ['pending', 'accepted'])
                ->pluck('schedule_id')
                ->unique()
                ->filter()
                ->all();

            $query = DB::table('doctor_schedules')->where('is_booked', true);
            if (! empty($activeScheduleIds)) {
                $query->whereNotIn('id', $activeScheduleIds);
            }
            $query->update([
                'is_booked' => false,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'appointment_date')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropIndex(['schedule_id', 'appointment_date']);
                $table->dropColumn('appointment_date');
            });
        }
    }
};
