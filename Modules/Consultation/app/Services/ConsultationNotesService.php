<?php

namespace Modules\Consultation\Services;

use Modules\Consultation\Models\Consultation;

class ConsultationNotesService
{
    public function saveNotes(Consultation $consultation, string $notes): Consultation
    {
        $consultation->doctor_notes = $notes;
        $consultation->save();

        return $consultation->refresh();
    }

    public function getNotes(Consultation $consultation): ?string
    {
        return $consultation->doctor_notes;
    }
}