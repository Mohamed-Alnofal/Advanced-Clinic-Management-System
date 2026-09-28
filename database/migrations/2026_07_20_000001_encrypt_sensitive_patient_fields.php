<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE relatives MODIFY name TEXT NOT NULL');
        DB::statement('ALTER TABLE relatives MODIFY age TEXT NULL');
        DB::statement('ALTER TABLE relatives MODIFY gender TEXT NULL');

        DB::statement('ALTER TABLE consultations MODIFY livekit_room_sid TEXT NULL');
        DB::statement('ALTER TABLE consultations MODIFY doctor_notes LONGTEXT NULL');

        DB::statement('ALTER TABLE emergency_sos_signals MODIFY raw_transcript LONGTEXT NOT NULL');

        DB::statement('ALTER TABLE medical_visits MODIFY chief_complaint LONGTEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY diagnosis LONGTEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY icd_code LONGTEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY notes LONGTEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY treatment_plan LONGTEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY speciality_data LONGTEXT NULL');

        DB::statement('ALTER TABLE ophthalmology_records MODIFY visual_acuity_od LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY visual_acuity_os LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY iop_od LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY iop_os LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY cornea LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY lens LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY retina LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY optic_disc LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY slit_lamp_exam LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY refraction LONGTEXT NULL');

        DB::statement('ALTER TABLE emergency_medical_snapshots MODIFY vitals_json LONGTEXT NULL');
        DB::statement('ALTER TABLE emergency_medical_snapshots MODIFY allergies_json LONGTEXT NULL');
        DB::statement('ALTER TABLE emergency_medical_snapshots MODIFY chronic_conditions_json LONGTEXT NULL');
        DB::statement('ALTER TABLE emergency_medical_snapshots MODIFY last_visits_json LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE relatives MODIFY name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE relatives MODIFY age INT NULL');
        DB::statement("ALTER TABLE relatives MODIFY gender ENUM('male','female') NULL");

        DB::statement('ALTER TABLE consultations MODIFY livekit_room_sid VARCHAR(255) NULL');
        DB::statement('ALTER TABLE consultations MODIFY doctor_notes LONGTEXT NULL');

        DB::statement('ALTER TABLE emergency_sos_signals MODIFY raw_transcript TEXT NOT NULL');

        DB::statement('ALTER TABLE medical_visits MODIFY chief_complaint TEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY diagnosis TEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY icd_code VARCHAR(255) NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY notes LONGTEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY treatment_plan LONGTEXT NULL');
        DB::statement('ALTER TABLE medical_visits MODIFY speciality_data JSON NULL');

        DB::statement('ALTER TABLE ophthalmology_records MODIFY visual_acuity_od VARCHAR(255) NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY visual_acuity_os VARCHAR(255) NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY iop_od VARCHAR(255) NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY iop_os VARCHAR(255) NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY cornea VARCHAR(255) NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY lens VARCHAR(255) NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY retina VARCHAR(255) NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY optic_disc VARCHAR(255) NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY slit_lamp_exam LONGTEXT NULL');
        DB::statement('ALTER TABLE ophthalmology_records MODIFY refraction VARCHAR(255) NULL');

        DB::statement('ALTER TABLE emergency_medical_snapshots MODIFY vitals_json JSON NULL');
        DB::statement('ALTER TABLE emergency_medical_snapshots MODIFY allergies_json JSON NULL');
        DB::statement('ALTER TABLE emergency_medical_snapshots MODIFY chronic_conditions_json JSON NULL');
        DB::statement('ALTER TABLE emergency_medical_snapshots MODIFY last_visits_json JSON NULL');
    }
};