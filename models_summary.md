# Domain Models & Relationships Summary

## Model: Appointment
- **Class:** `Modules\Appointments\Models\Appointment`
- **Table:** `appointments`
- **Fillable Attributes:** doctor_id, user_id, clinic_id, schedule_id, doctor_schedule_id, booking_for, is_booked, appointment_date, status, reject_reason, cancel_reason, appointment_type, consultation_fee, payment_hold_id, payment_method
- **Casts:** {"id":"int","appointment_date":"date"}
- **Relationships:**
  - `doctor()`: **BelongsTo** -> `Doctor`
  - `patient()`: **BelongsTo** -> `User`
  - `user()`: **BelongsTo** -> `User`
  - `clinic()`: **BelongsTo** -> `Clinic`
  - `schedule()`: **BelongsTo** -> `DoctorSchedule`
  - `consultation()`: **HasOne** -> `Consultation`
  - `walletHold()`: **BelongsTo** -> `WalletHold`

---

## Model: AuditLog
- **Class:** `Modules\Audit\Models\AuditLog`
- **Table:** `audit_logs`
- **Fillable Attributes:** user_id, user_email, user_name, role, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, url, method
- **Casts:** {"id":"int","old_values":"array","new_values":"array"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`

---

## Model: Clinic
- **Class:** `Modules\Clinics\Models\Clinic`
- **Table:** `clinics`
- **Fillable Attributes:** medical_facility_id, doctor_id
- **Casts:** {"id":"int"}
- **Relationships:**
  - `doctor()`: **BelongsTo** -> `Doctor`
  - `appointments()`: **HasMany** -> `Appointment`
  - `medicalCenter()`: **BelongsTo** -> `MedicalFacility`

---

## Model: BreakGlassAccessLog
- **Class:** `Modules\Core\Models\BreakGlassAccessLog`
- **Table:** `break_glass_access_logs`
- **Fillable Attributes:** accessor_id, accessor_type, patient_id, sos_signal_id, reason, ip, created_at
- **Casts:** {"id":"int","created_at":"datetime"}
- **Relationships:**
  - `patient()`: **BelongsTo** -> `Patients`
  - `sosSignal()`: **BelongsTo** -> `EmergencySosSignal`

---

## Model: EmergencyMedicalSnapshot
- **Class:** `Modules\Core\Models\EmergencyMedicalSnapshot`
- **Table:** `emergency_medical_snapshots`
- **Fillable Attributes:** sos_signal_id, patient_id, vitals_json, allergies_json, chronic_conditions_json, last_visits_json, created_at
- **Casts:** {"id":"int","vitals_json":"encrypted:array","allergies_json":"encrypted:array","chronic_conditions_json":"encrypted:array","last_visits_json":"encrypted:array","created_at":"datetime"}
- **Relationships:**
  - `sosSignal()`: **BelongsTo** -> `EmergencySosSignal`
  - `patient()`: **BelongsTo** -> `Patients`

---

## Model: EmergencySosSignal
- **Class:** `Modules\Core\Models\EmergencySosSignal`
- **Table:** `emergency_sos_signals`
- **Fillable Attributes:** patient_id, latitude, longitude, raw_transcript, severity_level, suspected_condition, detected_category, first_aid_steps, warning_signs, do_not, ai_confidence, ai_source, call_emergency_now, rules_score, created_at
- **Casts:** {"id":"int","latitude":"decimal:7","longitude":"decimal:7","first_aid_steps":"array","warning_signs":"array","do_not":"array","call_emergency_now":"boolean","created_at":"datetime","raw_transcript":"encrypted"}
- **Relationships:**
  - `patient()`: **BelongsTo** -> `Patients`
  - `emergencyRequest()`: **HasOne** -> `EmergencyRequest`
  - `medicalSnapshot()`: **HasOne** -> `EmergencyMedicalSnapshot`
  - `breakGlassLogs()`: **HasMany** -> `BreakGlassAccessLog`

---

## Model: Governorate
- **Class:** `Modules\Core\Models\Governorate`
- **Table:** `governorates`
- **Fillable Attributes:** name
- **Casts:** {"id":"int"}
- **Relationships:**
  - `doctors()`: **HasMany** -> `Doctor`
  - `pharmacists()`: **HasMany** -> `Pharmacist`

---

## Model: Pharmacist
- **Class:** `Modules\Core\Models\Pharmacist`
- **Table:** `pharmacists`
- **Fillable Attributes:** user_id, image, governorate_id, pharmacy_name, license_number, clinic_latitude, clinic_longitude, clinic_address, is_verified
- **Casts:** {"id":"int"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`
  - `governorate()`: **BelongsTo** -> `Governorate`

---

## Model: specializations
- **Class:** `Modules\Core\Models\specializations`
- **Table:** `specializations`
- **Fillable Attributes:** name
- **Casts:** {"id":"int"}
- **Relationships:**
  - `doctors()`: **HasMany** -> `Doctor`

---

## Model: AppointmentReschedule
- **Class:** `Modules\Doctors\Models\AppointmentReschedule`
- **Table:** `appointment_reschedules`
- **Fillable Attributes:** appointment_id, old_date, old_time, new_date, new_time, status, reason, created_by
- **Casts:** {"id":"int"}
- **Relationships:**
  - `appointment()`: **BelongsTo** -> `Appointment`

---

## Model: Doctor
- **Class:** `Modules\Doctors\Models\Doctor`
- **Table:** `doctors`
- **Fillable Attributes:** user_id, specialization_id, governorate_id, bio, experience_years, price, image, clinic_name, clinic_latitude, clinic_longitude, clinic_address, is_verified, is_active
- **Casts:** {"id":"int","is_verified":"boolean","is_active":"boolean","price":"decimal:2","clinic_latitude":"float","clinic_longitude":"float"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`
  - `specialization()`: **BelongsTo** -> `specializations`
  - `certificates()`: **HasMany** -> `DoctorCertificate`
  - `governorate()`: **BelongsTo** -> `Governorate`
  - `clinics()`: **HasMany** -> `Clinic`
  - `doctorSchedule()`: **HasMany** -> `DoctorSchedule`
  - `wallet()`: **HasOne** -> `Wallet`
  - `fcmTokens()`: **HasMany** -> `FcmToken`
  - `notifications()`: **MorphMany** -> `Notifications`
  - `videoSubscriptions()`: **HasMany** -> `VideoSubscription`
  - `ratings()`: **MorphMany** -> `Rating`
  - `subscriptions()`: **MorphMany** -> `Subscription`

---

## Model: DoctorCertificate
- **Class:** `Modules\Doctors\Models\DoctorCertificate`
- **Table:** `doctor_certificates`
- **Fillable Attributes:** doctor_id, file_path
- **Casts:** {"id":"int"}
- **Relationships:**
  - `doctor()`: **BelongsTo** -> `Doctor`

---

## Model: DoctorDayAction
- **Class:** `Modules\Doctors\Models\DoctorDayAction`
- **Table:** `doctor_day_actions`
- **Fillable Attributes:** doctor_id, date, action, delay_minutes, reason
- **Casts:** {"id":"int"}

---

## Model: DoctorHoliday
- **Class:** `Modules\Doctors\Models\DoctorHoliday`
- **Table:** `doctor_holidays`
- **Fillable Attributes:** doctor_id, clinic_id, start_date, end_date, reason
- **Casts:** {"id":"int","start_date":"date","end_date":"date"}
- **Relationships:**
  - `doctor()`: **BelongsTo** -> `Doctor`

---

## Model: DoctorMonthlySchedule
- **Class:** `Modules\Doctors\Models\DoctorMonthlySchedule`
- **Table:** `doctor_monthly_schedules`
- **Fillable Attributes:** doctor_id, clinic_id, doctor_schedule_id, date, start_time, end_time, consultation_duration, price, status
- **Casts:** {"id":"int","date":"date"}
- **Relationships:**
  - `doctorSchedule()`: **BelongsTo** -> `DoctorSchedule`
  - `doctor()`: **BelongsTo** -> `Doctor`

---

## Model: DoctorSchedule
- **Class:** `Modules\Doctors\Models\DoctorSchedule`
- **Table:** `doctor_schedules`
- **Fillable Attributes:** doctor_id, clinic_id, consultation_type, day, start_time, end_time, consultation_duration, price, is_booked
- **Casts:** {"id":"int"}
- **Relationships:**
  - `doctor()`: **BelongsTo** -> `Doctor`
  - `appointments()`: **HasMany** -> `Appointment`
  - `monthlySchedules()`: **HasMany** -> `DoctorMonthlySchedule`

---

## Model: Category
- **Class:** `Modules\Drug\Models\Category`
- **Table:** `categories`
- **Fillable Attributes:** category
- **Casts:** {"id":"int"}
- **Relationships:**
  - `drugs()`: **BelongsToMany** -> `Drug`

---

## Model: Dosage
- **Class:** `Modules\Drug\Models\Dosage`
- **Table:** `dosages`
- **Fillable Attributes:** dosage, route, strength, drug_id
- **Casts:** {"id":"int"}
- **Relationships:**
  - `drug()`: **BelongsTo** -> `Drug`

---

## Model: Drug
- **Class:** `Modules\Drug\Models\Drug`
- **Table:** `drugs`
- **Fillable Attributes:** name, description, state, indication, pharmacodynamics, toxicity, half_life, route_of_elimination, clearance, attribute
- **Casts:** {"id":"int"}
- **Relationships:**
  - `pharmacists()`: **BelongsToMany** -> `Pharmacist`
  - `categories()`: **BelongsToMany** -> `Category`
  - `prices()`: **HasMany** -> `Price`
  - `synonyms()`: **HasMany** -> `Synonym`
  - `pharmacies()`: **BelongsToMany** -> `Pharmacy`
  - `products()`: **HasMany** -> `Product`
  - `dosages()`: **HasMany** -> `Dosage`
  - `alternatives()`: **BelongsToMany** -> `Drug`

---

## Model: Price
- **Class:** `Modules\Drug\Models\Price`
- **Table:** `prices`
- **Fillable Attributes:** description, cost, currency, unit, drug_id
- **Casts:** {"id":"int","cost":"decimal:2"}
- **Relationships:**
  - `drug()`: **BelongsTo** -> `Drug`

---

## Model: Product
- **Class:** `Modules\Drug\Models\Product`
- **Table:** `products`
- **Fillable Attributes:** name, labeller, dosage_form, strength, route, generic, otc, drug_id
- **Casts:** {"id":"int","generic":"boolean","otc":"boolean"}
- **Relationships:**
  - `drug()`: **BelongsTo** -> `Drug`

---

## Model: Synonym
- **Class:** `Modules\Drug\Models\Synonym`
- **Table:** `synonyms`
- **Fillable Attributes:** synonym, drug_id
- **Casts:** {"id":"int"}
- **Relationships:**
  - `drug()`: **BelongsTo** -> `Drug`

---

## Model: MedicalFacility
- **Class:** `Modules\MedicalFacility\Models\MedicalFacility`
- **Table:** `medical_facilities`
- **Fillable Attributes:** governorate_id, name, type, latitude, longitude, address, phone, image, is_verified, is_active
- **Casts:** {"id":"int","is_verified":"boolean","is_active":"boolean","latitude":"float","longitude":"float"}
- **Relationships:**
  - `governorate()`: **BelongsTo** -> `Governorate`
  - `doctors()`: **BelongsToMany** -> `Doctor`
  - `pharmacists()`: **BelongsToMany** -> `Pharmacist`
  - `clinic()`: **HasOne** -> `Clinic`
  - `clinics()`: **HasMany** -> `Clinic`
  - `pharmacist()`: **HasOne** -> `Pharmacist`
  - `pharmacy()`: **HasOne** -> `Pharmacy`

---

## Model: MedicalRecord
- **Class:** `Modules\MedicalRecords\Models\MedicalRecord`
- **Table:** `medical_records`
- **Fillable Attributes:** patient_id, medical_visit_id, uploaded_by, title, description, file_path, mime_type, file_name, status, report, extracted_data, analyzed_at, error_message
- **Casts:** {"id":"int","report":"array","extracted_data":"array","analyzed_at":"datetime"}
- **Relationships:**
  - `patient()`: **BelongsTo** -> `User`
  - `uploadedBy()`: **BelongsTo** -> `User`
  - `medicalVisit()`: **BelongsTo** -> `MedicalVisits`

---

## Model: MedicalReferenceRange
- **Class:** `Modules\MedicalRecords\Models\MedicalReferenceRange`
- **Table:** `medical_reference_ranges`
- **Fillable Attributes:** test_name, test_key, unit, min_value, max_value, gender, age_min, age_max, description, advice_low, advice_high, interpretation_low, interpretation_high, interpretation_normal
- **Casts:** {"id":"int","min_value":"float","max_value":"float","age_min":"integer","age_max":"integer"}

---

## Model: Pharmacist
- **Class:** `Modules\Pharmacist\Models\Pharmacist`
- **Table:** `pharmacists`
- **Fillable Attributes:** user_id, pharmacy_name, license_number, image, clinic_latitude, clinic_longitude, clinic_address, governorate_id, is_verified
- **Casts:** {"id":"int"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`
  - `drugs()`: **BelongsToMany** -> `Drug`
  - `orders()`: **HasMany** -> `Order`
  - `order_items()`: **HasMany** -> `OrderItem`
  - `wallet()`: **HasOne** -> `Wallet`
  - `medicalCenter()`: **BelongsTo** -> `MedicalFacility`

---

## Model: Pharmacy
- **Class:** `Modules\Pharmacy\Models\Pharmacy`
- **Table:** `pharmacies`
- **Fillable Attributes:** pharmacist_id, medical_facility_id, governorate_id
- **Casts:** {"id":"int"}
- **Relationships:**
  - `pharmacist()`: **BelongsTo** -> `Pharmacist`
  - `medicalFacility()`: **BelongsTo** -> `MedicalFacility`
  - `medicalCenter()`: **BelongsTo** -> `MedicalFacility`
  - `schedules()`: **HasMany** -> `PharmacySchedule`
  - `drugs()`: **BelongsToMany** -> `Drug`
  - `roles()`: **MorphToMany** -> `Role`
  - `permissions()`: **MorphToMany** -> `Permission`

---

## Model: PharmacyDrug
- **Class:** `Modules\Pharmacy\Models\PharmacyDrug`
- **Table:** `pharmacy_drug`
- **Fillable Attributes:** pharmacy_id, drug_id, price, quantity, is_available
- **Casts:** {"price":"decimal:2","quantity":"integer","is_available":"boolean"}
- **Relationships:**
  - `pharmacy()`: **BelongsTo** -> `Pharmacy`
  - `drug()`: **BelongsTo** -> `Drug`

---

## Model: PharmacySchedule
- **Class:** `Modules\Pharmacy\Models\PharmacySchedule`
- **Table:** `pharmacy_schedules`
- **Fillable Attributes:** pharmacy_id, day, open_time, close_time, is_closed
- **Casts:** {"id":"int"}
- **Relationships:**
  - `pharmacy()`: **BelongsTo** -> `Pharmacy`
  - `roles()`: **MorphToMany** -> `Role`
  - `permissions()`: **MorphToMany** -> `Permission`

---

## Model: FcmToken
- **Class:** `Modules\Users\Models\FcmToken`
- **Table:** `fcm_tokens`
- **Fillable Attributes:** user_id, token
- **Casts:** {"id":"int"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`

---

## Model: Relatives
- **Class:** `Modules\Users\Models\Relatives`
- **Table:** `relatives`
- **Fillable Attributes:** user_id, name, age, gender
- **Casts:** {"id":"int","name":"encrypted","age":"encrypted","gender":"encrypted"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`
  - `patient()`: **MorphOne** -> `Patients`

---

## Model: User
- **Class:** `Modules\Users\Models\User`
- **Table:** `users`
- **Fillable Attributes:** name, email, password, phone, age, gender, is_verified, otp, otp_expires_at, suspension_reason, status
- **Casts:** {"id":"int","email_verified_at":"datetime","password":"hashed"}
- **Relationships:**
  - `doctor()`: **HasOne** -> `Doctor`
  - `pharmacist()`: **HasOne** -> `Pharmacist`
  - `appointments()`: **HasMany** -> `Appointment`
  - `relatives()`: **HasMany** -> `Relatives`
  - `orders()`: **HasMany** -> `Order`
  - `disputesCreated()`: **HasMany** -> `Dispute`
  - `disputesAgainst()`: **HasMany** -> `Dispute`
  - `handledDisputes()`: **HasMany** -> `Dispute`
  - `wallet()`: **HasOne** -> `Wallet`
  - `patient()`: **MorphOne** -> `Patients`
  - `notifications()`: **MorphMany** -> `Notifications`
  - `unreadNotifications()`: **MorphMany** -> `Notifications`
  - `fcmTokens()`: **HasMany** -> `FcmToken`
  - `videoSubscriptions()`: **HasMany** -> `VideoSubscription`
  - `ambulanceCompany()`: **HasOne** -> `AmbulanceCompany`
  - `emergencyRequests()`: **HasMany** -> `EmergencyRequest`
  - `medicalRecords()`: **HasMany** -> `MedicalRecord`
  - `tokens()`: **MorphMany** -> `PersonalAccessToken`
  - `roles()`: **MorphToMany** -> `Role`
  - `permissions()`: **MorphToMany** -> `Permission`

---

## Model: AmbulanceCompany
- **Class:** `Modules\Ambulance\Models\AmbulanceCompany`
- **Table:** `ambulance_companies`
- **Fillable Attributes:** user_id, governorate_id, name, license_number, phone, email, address, latitude, longitude, logo, description, work_from, work_to, is_24_hours, status, is_active
- **Casts:** {"id":"int"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`
  - `units()`: **HasMany** -> `AmbulanceUnit`
  - `drivers()`: **HasMany** -> `AmbulanceDriver`

---

## Model: AmbulanceDriver
- **Class:** `Modules\Ambulance\Models\AmbulanceDriver`
- **Table:** `ambulance_drivers`
- **Fillable Attributes:** ambulance_company_id, name, phone, email, password, is_active
- **Casts:** {"id":"int","password":"hashed"}
- **Relationships:**
  - `company()`: **BelongsTo** -> `AmbulanceCompany`
  - `unit()`: **HasOne** -> `AmbulanceUnit`

---

## Model: AmbulanceUnit
- **Class:** `Modules\Ambulance\Models\AmbulanceUnit`
- **Table:** `ambulance_units`
- **Fillable Attributes:** ambulance_company_id, ambulance_driver_id, plate_number, status, latitude, longitude, last_location_update, pairing_code, pairing_expires_at, device_token, is_connected, is_active
- **Casts:** {"id":"int"}
- **Relationships:**
  - `company()`: **BelongsTo** -> `AmbulanceCompany`
  - `emergencyRequests()`: **HasMany** -> `EmergencyRequest`
  - `driver()`: **BelongsTo** -> `AmbulanceDriver`

---

## Model: EmergencyRequest
- **Class:** `Modules\Ambulance\Models\EmergencyRequest`
- **Table:** `emergency_requests`
- **Fillable Attributes:** user_id, sos_signal_id, ambulance_unit_id, ambulance_company_id, case_number, description, address, latitude, longitude, priority, severity_level, suspected_condition, first_aid_steps, status, cancel_reason, requested_at, escalated_at, accepted_at, arrived_at, completed_at
- **Casts:** {"id":"int","latitude":"decimal:7","longitude":"decimal:7","address":"encrypted","description":"encrypted","suspected_condition":"encrypted","cancel_reason":"encrypted","first_aid_steps":"encrypted:array","requested_at":"datetime","escalated_at":"datetime","accepted_at":"datetime","arrived_at":"datetime","completed_at":"datetime"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`
  - `ambulanceUnit()`: **BelongsTo** -> `AmbulanceUnit`
  - `ambulanceCompany()`: **BelongsTo** -> `AmbulanceCompany`
  - `sosSignal()`: **BelongsTo** -> `EmergencySosSignal`
  - `offers()`: **HasMany** -> `EmergencyRequestOffer`

---

## Model: EmergencyRequestOffer
- **Class:** `Modules\Ambulance\Models\EmergencyRequestOffer`
- **Table:** `emergency_request_offers`
- **Fillable Attributes:** emergency_request_id, ambulance_unit_id, ambulance_driver_id, status, offered_at, responded_at
- **Casts:** {"id":"int","offered_at":"datetime","responded_at":"datetime"}
- **Relationships:**
  - `emergencyRequest()`: **BelongsTo** -> `EmergencyRequest`
  - `ambulanceUnit()`: **BelongsTo** -> `AmbulanceUnit`
  - `driver()`: **BelongsTo** -> `AmbulanceDriver`

---

## Model: Attachments
- **Class:** `Modules\Attachments\Models\Attachments`
- **Table:** `attachments`
- **Fillable Attributes:** medical_visit_id, file, type
- **Casts:** {"id":"int"}
- **Relationships:**
  - `medicalVisit()`: **BelongsTo** -> `MedicalVisits`

---

## Model: Consultation
- **Class:** `Modules\Consultation\Models\Consultation`
- **Table:** `consultations`
- **Fillable Attributes:** appointment_id, room_name, livekit_room_sid, status, started_at, ended_at, rating, doctor_notes, recording_url
- **Casts:** {"id":"int","started_at":"datetime","ended_at":"datetime","rating":"integer","livekit_room_sid":"encrypted","doctor_notes":"encrypted","recording_url":"encrypted"}
- **Relationships:**
  - `appointment()`: **BelongsTo** -> `Appointment`
  - `videoAudits()`: **HasMany** -> `VideoAudit`

---

## Model: VideoAudit
- **Class:** `Modules\Consultation\Models\VideoAudit`
- **Table:** `video_audits`
- **Fillable Attributes:** consultation_id, appointment_id, user_id, user_role, event, ip_address, user_agent, details
- **Casts:** {"id":"int","details":"array"}
- **Relationships:**
  - `consultation()`: **BelongsTo** -> `Consultation`
  - `appointment()`: **BelongsTo** -> `Appointment`
  - `user()`: **BelongsTo** -> `User`

---

## Model: VideoSubscription
- **Class:** `Modules\Consultation\Models\VideoSubscription`
- **Table:** `video_subscriptions`
- **Fillable Attributes:** user_id, doctor_id
- **Casts:** {"id":"int"}
- **Relationships:**
  - `doctor()`: **BelongsTo** -> `Doctor`
  - `user()`: **BelongsTo** -> `User`

---

## Model: Dispute
- **Class:** `Modules\Disputes\Models\Dispute`
- **Table:** `disputes`
- **Fillable Attributes:** complainant_id, defendant_id, handled_by, doctor_id, pharmacist_id, subject, description, priority, status, resolved_at, is_escalated
- **Casts:** {"id":"int","resolved_at":"datetime","is_escalated":"boolean"}
- **Relationships:**
  - `complainant()`: **BelongsTo** -> `User`
  - `defendant()`: **BelongsTo** -> `User`
  - `handler()`: **BelongsTo** -> `User`
  - `doctor()`: **BelongsTo** -> `Doctor`
  - `pharmacist()`: **BelongsTo** -> `Pharmacist`

---

## Model: Governorates
- **Class:** `Modules\Governorates\Models\Governorates`
- **Table:** `governorates`
- **Fillable Attributes:** name
- **Casts:** {"id":"int"}
- **Relationships:**
  - `medicalFacilities()`: **HasMany** -> `MedicalFacility`

---

## Model: FamilyHistory
- **Class:** `Modules\MedicalVisits\Models\FamilyHistory`
- **Table:** `family_histories`
- **Fillable Attributes:** patient_file_id, relative, disease
- **Casts:** {"id":"int"}
- **Relationships:**
  - `patientFile()`: **BelongsTo** -> `PatientFile`

---

## Model: MedicalVisits
- **Class:** `Modules\MedicalVisits\Models\MedicalVisits`
- **Table:** `medical_visits`
- **Fillable Attributes:** patient_file_id, doctor_id, chief_complaint, diagnosis, icd_code, notes, treatment_plan, speciality_data, visit_date, category, visibility
- **Casts:** {"id":"int","chief_complaint":"encrypted","diagnosis":"encrypted","icd_code":"encrypted","notes":"encrypted","treatment_plan":"encrypted","speciality_data":"encrypted:array","visit_date":"datetime"}
- **Relationships:**
  - `doctor()`: **BelongsTo** -> `Doctor`
  - `patientFile()`: **BelongsTo** -> `PatientFile`
  - `accessRequests()`: **HasMany** -> `PatientAccessRequest`
  - `attachments()`: **HasMany** -> `Attachments`
  - `prescriptions()`: **HasMany** -> `Prescriptions`
  - `ophthalmologyRecord()`: **HasOne** -> `OphthalmologyRecord`

---

## Model: OcularHistory
- **Class:** `Modules\MedicalVisits\Models\OcularHistory`
- **Table:** `ocular_histories`
- **Fillable Attributes:** patient_file_id, condition, diagnosed_at, notes
- **Casts:** {"id":"int"}
- **Relationships:**
  - `patientFile()`: **BelongsTo** -> `PatientFile`

---

## Model: OphthalmologyRecord
- **Class:** `Modules\MedicalVisits\Models\OphthalmologyRecord`
- **Table:** `ophthalmology_records`
- **Fillable Attributes:** medical_visit_id, visual_acuity_od, visual_acuity_os, iop_od, iop_os, cornea, lens, retina, optic_disc, slit_lamp_exam, refraction
- **Casts:** {"id":"int","visual_acuity_od":"encrypted","visual_acuity_os":"encrypted","iop_od":"encrypted","iop_os":"encrypted","cornea":"encrypted","lens":"encrypted","retina":"encrypted","optic_disc":"encrypted","slit_lamp_exam":"encrypted","refraction":"encrypted"}
- **Relationships:**
  - `medicalVisit()`: **BelongsTo** -> `MedicalVisits`

---

## Model: Surgery
- **Class:** `Modules\MedicalVisits\Models\Surgery`
- **Table:** `surgeries`
- **Fillable Attributes:** patient_file_id, surgery_name, surgery_date, notes
- **Casts:** {"id":"int","surgery_date":"date"}
- **Relationships:**
  - `patientFile()`: **BelongsTo** -> `PatientFile`

---

## Model: VitalSign
- **Class:** `Modules\MedicalVisits\Models\VitalSign`
- **Table:** `vital_signs`
- **Fillable Attributes:** patient_file_id, blood_type, rh_factor, height, weight, bmi
- **Casts:** {"id":"int"}
- **Relationships:**
  - `patientFile()`: **BelongsTo** -> `PatientFile`

---

## Model: Notifications
- **Class:** `Modules\Notifications\Models\Notifications`
- **Table:** `notifications`
- **Fillable Attributes:** type, notifiable_id, notifiable_type, data, read_at
- **Casts:** {"data":"array","read_at":"datetime"}
- **Relationships:**
  - `notifiable()`: **MorphTo** -> `Notifications`

---

## Model: Order
- **Class:** `Modules\Order\Models\Order`
- **Table:** `orders`
- **Fillable Attributes:** user_id, pharmacist_id, pharmacy_id, quantity, delivery_address, latitude, longitude, status, payment_status, total_price, locked_at
- **Casts:** {"id":"int"}
- **Relationships:**
  - `user()`: **BelongsTo** -> `User`
  - `pharmacy()`: **BelongsTo** -> `Pharmacy`
  - `pharmacist()`: **BelongsTo** -> `Pharmacist`
  - `drug()`: **BelongsTo** -> `Drug`
  - `items()`: **HasMany** -> `OrderItem`
  - `pharmacists()`: **BelongsTo** -> `Pharmacist`

---

## Model: OrderItem
- **Class:** `Modules\Order\Models\OrderItem`
- **Table:** `order_items`
- **Fillable Attributes:** order_id, drug_id, quantity, price, prescription_file
- **Casts:** {"id":"int"}
- **Relationships:**
  - `order()`: **BelongsTo** -> `Order`
  - `drug()`: **BelongsTo** -> `Drug`
  - `pharmacy()`: **BelongsTo** -> `Pharmacist`

---

## Model: PatientAccessRequest
- **Class:** `Modules\Patients\Models\PatientAccessRequest`
- **Table:** `patient_access_requests`
- **Fillable Attributes:** doctor_id, patient_id, patient_file_id, medical_visit_id, status
- **Casts:** {"id":"int"}
- **Relationships:**
  - `patient()`: **BelongsTo** -> `Patients`
  - `doctor()`: **BelongsTo** -> `Doctor`
  - `patientFile()`: **BelongsTo** -> `PatientFile`
  - `visit()`: **BelongsTo** -> `MedicalVisits`

---

## Model: PatientFile
- **Class:** `Modules\Patients\Models\PatientFile`
- **Table:** `patient_files`
- **Fillable Attributes:** patient_id, created_by
- **Casts:** {"id":"int"}
- **Relationships:**
  - `patient()`: **BelongsTo** -> `Patients`
  - `creator()`: **BelongsTo** -> `Doctor`
  - `medicalVisits()`: **HasMany** -> `MedicalVisits`
  - `vitalSigns()`: **HasMany** -> `VitalSign`
  - `surgeries()`: **HasMany** -> `Surgery`
  - `familyHistories()`: **HasMany** -> `FamilyHistory`
  - `ocularHistories()`: **HasMany** -> `OcularHistory`
  - `accessRequests()`: **HasMany** -> `PatientAccessRequest`

---

## Model: Patients
- **Class:** `Modules\Patients\Models\Patients`
- **Table:** `patients`
- **Fillable Attributes:** type, patientable_id, patientable_type
- **Casts:** {"id":"int"}
- **Relationships:**
  - `patientable()`: **MorphTo** -> `Patients`
  - `files()`: **HasMany** -> `PatientFile`
  - `file()`: **HasOne** -> `PatientFile`
  - `visits()`: **HasManyThrough** -> `MedicalVisits`

---

## Model: PrescriptionDispensing
- **Class:** `Modules\Prescriptions\Models\PrescriptionDispensing`
- **Table:** `prescription_dispensings`
- **Fillable Attributes:** prescription_id, pharmacy_id, pharmacist_id, dispensed_at, status, archived_pdf_path, pdf_hash
- **Casts:** {"id":"int","dispensed_at":"datetime"}
- **Relationships:**
  - `prescription()`: **BelongsTo** -> `Prescriptions`
  - `pharmacist()`: **BelongsTo** -> `User`

---

## Model: PrescriptionItem
- **Class:** `Modules\Prescriptions\Models\PrescriptionItem`
- **Table:** `prescription_items`
- **Fillable Attributes:** prescription_id, medicine_name, dose, frequency, duration, instructions
- **Casts:** {"id":"int"}
- **Relationships:**
  - `prescription()`: **BelongsTo** -> `Prescriptions`

---

## Model: Prescriptions
- **Class:** `Modules\Prescriptions\Models\Prescriptions`
- **Table:** `prescriptions`
- **Fillable Attributes:** medical_visit_id, medical_visit_id, dispensing_type
- **Casts:** {"id":"int"}
- **Relationships:**
  - `medicalVisit()`: **BelongsTo** -> `MedicalVisits`
  - `items()`: **HasMany** -> `PrescriptionItem`
  - `visit()`: **BelongsTo** -> `MedicalVisits`
  - `dispensings()`: **HasMany** -> `PrescriptionDispensing`

---

## Model: Price
- **Class:** `Modules\Price\Models\Price`
- **Table:** `prices`
- **Fillable Attributes:** 
- **Casts:** {"id":"int"}
- **Relationships:**
  - `drug()`: **BelongsTo** -> `Drug`

---

## Model: Product
- **Class:** `Modules\Product\Models\Product`
- **Table:** `products`
- **Fillable Attributes:** 
- **Casts:** {"id":"int"}
- **Relationships:**
  - `drug()`: **BelongsTo** -> `Drug`

---

## Model: Subscription
- **Class:** `Modules\Subscription\Models\Subscription`
- **Table:** `subscriptions`
- **Fillable Attributes:** subscription_plan_id, starts_at, ends_at, status, auto_renew, cancel_at_period_end
- **Casts:** {"id":"int","starts_at":"datetime","ends_at":"datetime","auto_renew":"boolean","cancel_at_period_end":"boolean"}
- **Relationships:**
  - `subscriber()`: **MorphTo** -> `Subscription`
  - `plan()`: **BelongsTo** -> `SubscriptionPlan`
  - `payments()`: **HasMany** -> `SubscriptionPayment`

---

## Model: SubscriptionPayment
- **Class:** `Modules\Subscription\Models\SubscriptionPayment`
- **Table:** `subscription_payments`
- **Fillable Attributes:** subscription_id, amount, method, status, receipt, reference, stripe_session_id, stripe_payment_intent_id, paid_at
- **Casts:** {"id":"int","amount":"decimal:2","paid_at":"datetime"}
- **Relationships:**
  - `subscription()`: **BelongsTo** -> `Subscription`

---

## Model: SubscriptionPlan
- **Class:** `Modules\Subscription\Models\SubscriptionPlan`
- **Table:** `subscription_plans`
- **Fillable Attributes:** name, subscriber_type, duration, price, is_active
- **Casts:** {"id":"int","price":"decimal:2","is_active":"boolean"}
- **Relationships:**
  - `subscriptions()`: **HasMany** -> `Subscription`

---

## Model: Wallet
- **Class:** `Modules\Wallet\Models\Wallet`
- **Table:** `wallets`
- **Fillable Attributes:** user_id, balance
- **Casts:** {"id":"int","balance":"decimal:2"}
- **Relationships:**
  - `transactions()`: **HasMany** -> `WalletTransaction`
  - `holds()`: **HasMany** -> `WalletHold`
  - `user()`: **BelongsTo** -> `User`
  - `pharmacist()`: **BelongsTo** -> `Pharmacist`

---

## Model: WalletHold
- **Class:** `Modules\Wallet\Models\WalletHold`
- **Table:** `wallet_holds`
- **Fillable Attributes:** wallet_id, appointment_id, wallet_transaction_id, amount, status, idempotency_key, expires_at, released_at, captured_at
- **Casts:** {"id":"int","amount":"decimal:2","expires_at":"datetime","released_at":"datetime","captured_at":"datetime"}
- **Relationships:**
  - `wallet()`: **BelongsTo** -> `Wallet`
  - `appointment()`: **BelongsTo** -> `Appointment`

---

## Model: WalletTransaction
- **Class:** `Modules\WalletTransaction\Models\WalletTransaction`
- **Table:** `wallet_transactions`
- **Fillable Attributes:** wallet_id, amount, type, status, stripe_session_id, idempotency_key
- **Casts:** {"id":"int"}
- **Relationships:**
  - `wallet()`: **BelongsTo** -> `Wallet`

---

