<?php

namespace Modules\Doctors\Services;

use Modules\Doctors\Models\DoctorHoliday;
use Modules\Doctors\Models\DoctorMonthlySchedule;

class DoctorHolidayService
{
    public function all($doctorId)
    {
        return DoctorHoliday::where('doctor_id', $doctorId)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getDoctorHolidays($doctorId)
    {
        return $this->all($doctorId);
    }

    public function create(array $data)
    {
        $holiday = DoctorHoliday::create($data);
        $this->markMonthlyAsHoliday($holiday);

        return $holiday;
    }

    public function update(DoctorHoliday $holiday, array $data)
    {
        $previous = $holiday->replicate();
        $holiday->update($data);
        $this->restoreMonthlyOutsideHolidays($previous);
        $this->markMonthlyAsHoliday($holiday->fresh());

        return $holiday->fresh();
    }

    public function delete(DoctorHoliday $holiday)
    {
        $snapshot = $holiday->replicate();
        $deleted = $holiday->delete();
        $this->restoreMonthlyOutsideHolidays($snapshot);

        return $deleted;
    }

    private function markMonthlyAsHoliday(DoctorHoliday $holiday): void
    {
        DoctorMonthlySchedule::query()
            ->where('doctor_id', $holiday->doctor_id)
            ->whereDate('date', '>=', $holiday->start_date->toDateString())
            ->whereDate('date', '<=', $holiday->end_date->toDateString())
            ->where('status', 'available')
            ->update(['status' => 'holiday']);
    }

    private function restoreMonthlyOutsideHolidays(DoctorHoliday $range): void
    {
        $rows = DoctorMonthlySchedule::query()
            ->where('doctor_id', $range->doctor_id)
            ->whereDate('date', '>=', $range->start_date->toDateString())
            ->whereDate('date', '<=', $range->end_date->toDateString())
            ->where('status', 'holiday')
            ->get();

        foreach ($rows as $row) {
            if (! DoctorHoliday::coversDate($row->doctor_id, $row->date->toDateString())) {
                $row->update(['status' => 'available']);
            }
        }
    }
}