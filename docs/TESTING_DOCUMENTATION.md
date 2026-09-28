# دليل الاختبارات الآلية — ACMS

**Advanced Clinic Management System** · Laravel 10+ · nwidart/laravel-modules · JWT (`auth:api`)

---

## جدول المحتويات

1. [نظرة عامة](#1-نظرة-عامة)
2. [البدء السريع](#2-البدء-السريع)
3. [أنواع الاختبارات](#3-أنواع-الاختبارات)
4. [هيكل المجلدات](#4-هيكل-المجلدات)
5. [قاعدة البيانات والبيئة](#5-قاعدة-البيانات-والبيئة)
6. [تشغيل الاختبارات](#6-تشغيل-الاختبارات)
7. [إضافة API أو موديول جديد](#7-إضافة-api-أو-موديول-جديد)
8. [البنية التحتية للاختبار](#8-البنية-التحتية-للاختبار)
9. [المساعدات والقوالب](#9-المساعدات-والقوالب)
10. [تغطية الموديولات](#10-تغطية-الموديولات)
11. [الدمج مع main و CI/CD](#11-الدمج-مع-main-و-cicd)
12. [استكشاف الأخطاء](#12-استكشاف-الأخطاء)
13. [الأسئلة الشائعة](#13-الأسئلة-الشائعة)
14. [مرجع الأوامر](#14-مرجع-الأوامر)

---

## 1. نظرة عامة

### 1.1 الهدف

حماية عقد الـ API بين **Laravel Backend** و **تطبيق Flutter** عبر اكتشاف أي كسر غير مقصود **قبل** الدمج إلى `main`.

### 1.2 لماذا نحتاج اختبارات آلية؟

مشروع ACMS مبني بموديولات منفصلة — كل موديول يملك `routes/api.php` خاصاً. هذا يعني:

| الخطر | مثال |
|-------|------|
| تغيير مسار API | حذف `/api/appointments/book` يكسر Flutter |
| تغيير Validation | حقل مطلوب جديد → `422` بدل `200` |
| حذف Middleware | `auth:api` محذوف → endpoint مفتوح للجميع |
| خطأ في مسار الملف | `Routes/` vs `routes/` — يعمل على Windows ويفشل على Linux/CI |

### 1.3 الوضع الحالي

| المؤشر | القيمة |
|--------|--------|
| إجمالي الاختبارات | **119** |
| Feature (API) | ~100 |
| Unit (Services) | 15 |
| Infrastructure | 5 |
| الأمر الموحّد | `composer test` |

---

## 2. البدء السريع

### 2.1 إعداد أول مرة

```bash
composer install
copy .env.example .env          # Windows — فقط إذا لا يوجد .env
php artisan key:generate
php artisan migrate             # على clinic_management_system_3
composer test:db                # ينشئ acms_testing تلقائياً
composer test
```

**قاعدتان MySQL — لا تخلطوهما:**

| القاعدة | الاستخدام | الملف |
|---------|-----------|-------|
| `clinic_management_system_3` | تطوير يومي | `.env` |
| `acms_testing` | اختبارات فقط | `phpunit.xml` |

إعداد `.env` للتطوير اليومي:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=clinic_management_system_3
DB_USERNAME=root
DB_PASSWORD=
```

**إنشاء قاعدة الاختبار (مرة واحدة)** — اختر طريقة:

```bash
composer test:db
```

أو على Windows:

```bat
setup-test-db.bat
```

أو في phpMyAdmin:

```sql
CREATE DATABASE acms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2.2 بعد كل `git pull` (بعد دمج `main`)

```bash
git pull
composer install
php artisan config:clear
composer test
```

| الخطوة | ليش |
|--------|-----|
| `git pull` | جلب آخر تغييرات main |
| `composer install` | تحديث `vendor` (ما بيجي مع git) |
| `php artisan config:clear` | يمسح config قديم |
| `composer test` | ينشئ/يتحقق من `acms_testing` ويشغّل الاختبارات |

> **لا تنفّذ:** `copy .env.testing .env` — يخرب `.env` المحلي.
> **لا تغيّر:** `DB_DATABASE` في `.env` — يبقى `clinic_management_system_3`.

### 2.3 قبل كل Pull Request

```bash
composer test
```

إذا كل الاختبارات خضراء → افتح PR. إذا CI أخضر → Merge.

---

## 3. أنواع الاختبارات

### 3.1 Feature Test — اختبار الـ API كاملاً

| الخاصية | التفاصيل |
|---------|----------|
| **المجلد** | `tests/Feature/Api/` |
| **ماذا يختبر** | HTTP Request → Middleware → Controller → JSON Response |
| **يحتاج** | JWT، قاعدة بيانات اختبار، Routes |
| **متى تستخدمه** | كل endpoint جديد يستهلكه Flutter |

**السيناريوهات الإلزامية لكل endpoint:**

| # | السيناريو | Status المتوقع |
|---|-----------|----------------|
| 1 | Happy Path — طلب صحيح مع JWT | `200` / `201` |
| 2 | بدون مصادقة | `401` |
| 3 | بيانات ناقصة أو خاطئة | `422` |
| 4 | صلاحية/Role خاطئة (إن وُجد) | `403` |

### 3.2 Unit Test — منطق الأعمال المعزول

| الخاصية | التفاصيل |
|---------|----------|
| **المجلد** | `tests/Unit/Services/` |
| **ماذا يختبر** | Service class مباشرة بدون HTTP |
| **يحتاج** | لا JWT، لا Routes |
| **متى تستخدمه** | OTP، محفظة، حسابات، State machines |

**الميزة:** أسرع بكثير — مناسب للقواعد المالية والحساسة.

### 3.3 Infrastructure Test — حماية البنية

| الخاصية | التفاصيل |
|---------|----------|
| **المجلد** | `tests/Feature/Infrastructure/` |
| **ماذا يختبر** | تسجيل المسارات، اكتشاف أخطاء الملفات، منع 500 |
| **متى يعمل** | تلقائياً مع كل `composer test` |

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│  Unit Tests      │     │  Feature Tests   │     │  Infrastructure  │
│  Services        │     │  API Endpoints   │     │  Routes + Smoke  │
│  (سريع)          │     │  (عقد Flutter)   │     │  (حماية main)    │
└──────────────────┘     └──────────────────┘     └──────────────────┘
         │                         │                         │
         └─────────────────────────┴─────────────────────────┘
                                   │
                          composer test
```

---

## 4. هيكل المجلدات

### 4.1 شجرة المشروع

```
tests/
├── Concerns/
│   └── AcmsTestHelpers.php       # JWT + إنشاء بيانات اختبار
├── CreatesApplication.php        # Bootstrap التطبيق
├── TestCase.php                  # الأساس: RefreshDatabase + Roles + Mocks
│
├── Feature/
│   ├── Api/                      # ← كل اختبارات API هنا
│   │   ├── AuthApiTest.php
│   │   ├── AppointmentBookingTest.php
│   │   ├── WalletApiTest.php
│   │   └── ... (22 ملف)
│   └── Infrastructure/
│       ├── ModuleApiRoutesTest.php
│       └── ApiRouteSmokeTest.php
│
└── Unit/
    └── Services/
        ├── OtpServiceTest.php
        └── WalletServiceTest.php

scripts/
└── check-test-requirements.php   # فحص PHP extensions

phpunit.xml                       # إعدادات PHPUnit + SQLite
.env.testing                      # للـ CI فقط
.github/workflows/laravel-tests.yml
```

### 4.2 توضيح مسارات شائعة

| المسار | موجود؟ | البديل الصحيح |
|--------|--------|---------------|
| `tests/Feature/Appointments/` | ❌ | `tests/Feature/Api/Appointment*.php` |
| `tests/Unit/Wallet/` | ❌ | `tests/Unit/Services/WalletServiceTest.php` |
| `tests/Support/` | ❌ | `tests/Concerns/AcmsTestHelpers.php` |
| `Modules/{Module}/tests/` | مستقبلاً | حالياً: `tests/Feature/Api/` |

### 4.3 مجموعات PHPUnit (`phpunit.xml`)

| المجموعة | المسار | الوصف |
|----------|--------|-------|
| `Unit` | `tests/Unit/` | Unit Tests |
| `Feature` | `tests/Feature/` | Feature + Infrastructure |
| `Modules` | `Modules/**/Test.php` | اختبارات داخل الموديولات (مستقبلاً) |

---

## 5. قاعدة البيانات والبيئة

### 5.1 القاعدة الذهبية — قاعدتان MySQL منفصلتان

```
┌─────────────────────────────────────────────────────────────────┐
│  التطوير اليومي          │  الاختبارات الآلية                │
│  ─────────────────        │  ──────────────────                │
│  .env                     │  phpunit.xml                      │
│  MySQL                    │  MySQL                            │
│  clinic_management_       │  acms_testing                     │
│  system_3                 │  تُمسح/تُعاد مع كل test          │
│  بيانات حقيقية            │  بيانات وهمية — تظهر بـ phpMyAdmin│
└─────────────────────────────────────────────────────────────────┘
```

> **مهم:** الاختبارات على `acms_testing` — **مو** على `clinic_management_system_3`.

### 5.2 كيف يعمل `RefreshDatabase`

كل اختبار يرث من `Tests\TestCase`:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
```

| الخطوة | ماذا يحدث |
|--------|-----------|
| 1 | قبل كل test method: تشغيل كل الـ Migrations |
| 2 | تنفيذ الاختبار (إنشاء users، appointments...) |
| 3 | بعد الاختبار: Rollback — قاعدة نظيفة للاختبار التالي |

> **تحذير:** إذا شغّلت الاختبارات على **نفس** `clinic_management_system_3`، `RefreshDatabase` **يمسح بيانات التطوير**. هذا السبب الشائع لـ «الاختبارات ضربت الداتا».

### 5.3 لماذا تُمس بيانات التطوير أحياناً؟

| السبب | الحل |
|-------|------|
| الاختبارات على `clinic_management_system_3` بدل `acms_testing` | تأكد من `phpunit.xml` → `DB_DATABASE=acms_testing` |
| `php artisan test --env=local` يقرأ `.env` الخاطئ | `php artisan config:clear` ثم `composer test` |
| `php artisan config:cache` ثم تشغيل tests | `php artisan config:clear` |
| نسخ `.env.testing` فوق `.env` | لا تنسخ — `.env` للتطوير فقط |

### 5.4 التحقق: الاختبارات على القاعدة الصحيحة

```bash
composer test:db
composer test --filter=generate_returns_six_digit_otp
```

افتح phpMyAdmin:

| القاعدة | المتوقع |
|---------|---------|
| `clinic_management_system_3` | ✅ بيانات التطوير — **لا** تتغير |
| `acms_testing` | ✅ جداول الاختبار — تُعاد مع كل `composer test` |

### 5.5 إعداد `acms_testing` — مرة واحدة لكل مطوّر

```bash
composer test:db
```

| القاعدة | الاستخدام |
|---------|-----------|
| `clinic_management_system_3` | تطوير يومي — `.env` |
| `acms_testing` | اختبارات — `phpunit.xml` + `composer test` |

### 5.6 ملفات البيئة — ملخص

| الملف | الاستخدام | من يستخدمه |
|-------|-----------|------------|
| `.env` | تطوير يومي + `php artisan serve` | كل مطوّر |
| `.env.testing` | مرجع CI | GitHub Actions فقط |
| `phpunit.xml` | فرض SQLite أثناء الاختبار | `composer test` |

إعدادات `phpunit.xml` الأساسية:

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="acms_testing"/>
<env name="DB_USERNAME" value="root"/>
<env name="DB_PASSWORD" value=""/>
<env name="JWT_SECRET" value="acms-testing-jwt-secret-key-for-ci-only"/>
<env name="MAIL_MAILER" value="array"/>
<env name="CACHE_DRIVER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
```

### 5.7 `--env=local` — متى؟

| الأمر | متى |
|-------|-----|
| `composer test` | **دائماً — الافتراضي** |
| `php artisan test --env=local` | فقط إذا فشل `composer test` بعد `config:clear` |

إذا `--env=local` جعل الاختبارات تلمس MySQL → توقف عن استخدامه.

---

## 6. تشغيل الاختبارات

### 6.1 أوامر Composer (موصى بها)

```bash
composer test         # فحص PHP + config:clear + تشغيل كل الاختبارات
composer test:ci      # كما على GitHub Actions
composer test:setup   # فحص متطلبات PHP + config:clear فقط
```

### 6.2 أوامر Artisan

```bash
# كل الاختبارات
php artisan test

# مجموعة محددة
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# ملف واحد
php artisan test tests/Feature/Api/AuthApiTest.php

# test method واحد
php artisan test --filter=test_login_returns_jwt_for_verified_patient

# تفاصيل عند الفشل
php artisan test --verbose

# متوازي (اختياري — يحتاج paratest)
php artisan test --parallel
```

### 6.3 قراءة نتائج الفشل

| Status | المعنى | الحل |
|--------|--------|------|
| `401` | JWT مفقود أو منتهي | `authenticateAs($user)` |
| `403` | Role أو صلاحية خاطئة | تحقق من `assignRole()` |
| `422` | Validation فشل | راجع الحقول في Controller/Request |
| `404` | Route أو Model غير موجود | قارن URI مع `routes/api.php` |
| `500` | Exception | `storage/logs/laravel.log` |

---

## 7. إضافة API أو موديول جديد

### 7.1 Workflow إلزامي

```
  ┌─────────────────────────────────────────┐
  │ 1. Route في Modules/X/routes/api.php    │
  └──────────────────┬──────────────────────┘
                     ▼
  ┌─────────────────────────────────────────┐
  │ 2. Feature Test في tests/Feature/Api/  │
  └──────────────────┬──────────────────────┘
                     ▼
  ┌─────────────────────────────────────────┐
  │ 3. أربع سيناريوهات: 200 + 401 + 422   │
  │    + 403 إن وُجد                        │
  └──────────────────┬──────────────────────┘
                     ▼
  ┌─────────────────────────────────────────┐
  │ 4. composer test                        │
  └──────────────────┬──────────────────────┘
                     ▼
  ┌─────────────────────────────────────────┐
  │ 5. PR → CI أخضر → Merge                 │
  └─────────────────────────────────────────┘
```

### 7.2 تسمية الملفات

```
tests/Feature/Api/{Resource}{Action}Test.php

أمثلة:
  AppointmentBookingTest.php
  AuthSessionApiTest.php
  WalletApiTest.php
  PrescriptionsApiTest.php
```

### 7.3 مولّد الاختبارات

```bash
php artisan make:api-test AppointmentCancelApiTest \
  --method=POST \
  --uri=/api/appointments/cancel \
  --payload="{\"schedule_id\":1}"
```

ينشئ ملفاً بـ 3 سيناريوهات: Happy Path + 401 + 422.

### 7.4 موديول جديد (nwidart)

```
1. php artisan module:make MyModule
2. أضف routes/api.php
3. تأكد المسار: Routes/api.php أو routes/api.php (كلاهما مدعوم)
4. أضف Feature Test
5. ModuleApiRoutesTest سيتأكد تلقائياً من وجود api.php
```

### 7.5 متى تضيف Unit Test بدل Feature؟

| الحالة | النوع |
|--------|-------|
| Endpoint جديد لـ Flutter | Feature |
| تعديل حسابات المحفظة | Unit + Feature |
| تعديل OTP logic | Unit |
| Stripe webhook | Unit مع Mockery |

---

## 8. البنية التحتية للاختبار

### 8.1 `TestCase.php`

```php
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;      // قاعدة نظيفة لكل اختبار
    use AcmsTestHelpers;      // JWT + factories

    // يُنفَّذ تلقائياً:
    // - seedAcmsRoles()        → admin, doctor, pharmacist, user
    // - configureTestingEnvironment() → cache/mail/queue = array/sync
    // - fakeExternalServices() → Notification, Firebase, FCM mocks
}
```

### 8.2 `ModuleApiRoutesTest`

| الاختبار | الهدف |
|----------|-------|
| `test_every_active_module_exposes_a_discoverable_api_route_file` | كل موديول عنده `api.php` |
| `test_application_registers_api_routes_for_all_modules` | أكثر من 150 route مسجّل |
| `test_module_route_paths_work_on_case_sensitive_filesystems` | يكتشف `Routes/` vs `routes/` |

### 8.3 `ApiRouteSmokeTest`

| الاختبار | الهدف |
|----------|-------|
| `test_jwt_protected_api_routes_return_unauthorized_not_server_error` | مسارات `auth:api` لا ترجع 500 بدون JWT |
| `test_public_get_api_routes_do_not_return_server_error` | GET عامة لا ترجع 500 |

**مستثنى من الفحص:** `api/v1/*` (Sanctum scaffold)، webhooks، `test-fcm`.

### 8.4 `scripts/check-test-requirements.php`

يتحقق قبل التشغيل من: `pdo_sqlite`, `mbstring`, `openssl`, `bcmath`, `curl`...

---

## 9. المساعدات والقوالب

### 9.1 `AcmsTestHelpers` — الدوال المتاحة

| الدالة | الوصف |
|--------|-------|
| `seedAcmsRoles()` | أدوار Spatie: admin, doctor, pharmacist, user |
| `authenticateAs($user)` | Bearer JWT + Accept: application/json |
| `createPatient()` | مستخدم مريض موثّق |
| `createAdmin()` | مستخدم admin |
| `createDoctorWithClinicAndSchedule()` | طبيب + عيادة + جدول |
| `createPharmacistWithPharmacy()` | صيدلي + صيدلية + أدوية |
| `createWalletForUser($user, $balance)` | محفظة |
| `createAppointmentForPatient(...)` | موعد محجوز |
| `createUnverifiedUserWithOtp($otp)` | مستخدم غير موثّق + OTP |
| `uniqueEmail($prefix)` | إيميل فريد `@acms.test` |

### 9.2 قالب Feature Test

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class MyNewApiTest extends TestCase
{
    public function test_happy_path(): void
    {
        $user = $this->createPatient();

        $this->authenticateAs($user)
            ->postJson('/api/my-endpoint', ['key' => 'value'])
            ->assertOk()
            ->assertJsonStructure(['status', 'message']);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/my-endpoint', [])
            ->assertUnauthorized();
    }

    public function test_validates_required_fields(): void
    {
        $user = $this->createPatient();

        $this->authenticateAs($user)
            ->postJson('/api/my-endpoint', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }
}
```

### 9.3 قالب Unit Test

```php
<?php

namespace Tests\Unit\Services;

use Modules\Core\Services\OtpService;
use Tests\TestCase;

class MyServiceTest extends TestCase
{
    public function test_business_rule(): void
    {
        $service = new OtpService();
        $otp = $service->generate();

        $this->assertGreaterThanOrEqual(100000, $otp);
        $this->assertLessThanOrEqual(999999, $otp);
    }
}
```

---

## 10. تغطية الموديولات

### 10.1 Feature Tests — الموديولات المغطاة (JWT / `auth:api`)

| الموديول | ملف الاختبار | السيناريوهات |
|----------|-------------|--------------|
| **Auth** | `AuthApiTest`, `AuthSessionApiTest` | login, register, OTP, logout, refresh, resend |
| **Appointments** | `AppointmentBookingTest`, `AppointmentRescheduleTest`, `AppointmentLifecycleApiTest` | حجز, إعادة جدولة, إلغاء, my-appointments |
| **Doctors** | `DoctorScheduleApiTest` | store/view schedule |
| **Clinics** | `ClinicApiTest` | create, my-clinics |
| **Users** | `UserProfileApiTest` | profile, public doctor |
| **Wallet** | `WalletApiTest` | top-up, balance |
| **Order** | `OrderApiTest`, `OrderPharmacistApiTest` | patient orders, pharmacist orders, cancel |
| **Drug/Pharmacy** | `DrugPharmacyApiTest` | drugs CRUD, pharmacy |
| **Search** | `SearchApiTest`, `SearchExtendedApiTest` | specializations, search-by-name/governorate |
| **Admin** | `AdminApiTest` | users, approve doctor |
| **Notifications** | `NotificationsApiTest`, `NotificationsExtendedApiTest` | index, unread, count |
| **Disputes** | `DisputesApiTest` | create dispute |
| **Patients** | `PatientsApiTest` | history, patient files |
| **MedicalVisits** | `MedicalVisitsApiTest` | CRUD |
| **Prescriptions** | `PrescriptionsApiTest` | create, validate, delete |
| **Attachments** | `AttachmentsApiTest` | upload, validate |
| **Core** | `FcmApiTest` | store FCM token |

### 10.2 Unit Tests

| الخدمة | الملف | يغطي |
|--------|-------|------|
| OTP | `OtpServiceTest.php` | generate, verify, expiry, resend, Mail::fake |
| Wallet | `WalletServiceTest.php` | create, deposit, withdraw, decimal precision |

### 10.3 موديولات `/api/v1/*` — خارج التغطية الحالية

موديولات مثل `AmbulanceCenter`, `Governorates`, `Payment`, `Product`, `Stripe` تستخدم `auth:sanctum` على `/api/v1/*`.

تطبيق Flutter يستخدم **JWT** (`auth:api`) — هذه المسارات **خارج العقد الحالي** ومستثناة من Smoke Test.

### 10.4 أولويات التوسع المستقبلي

| المرحلة | الموديولات | السبب |
|---------|-----------|-------|
| 1 — حرجة | Auth, Appointments, Doctors, Users | Flutter يومياً |
| 2 — مالية | Wallet, Order, Payment, Stripe | أخطاء = خسائر |
| 3 — إدارية | Admin, Disputes, Notifications | حساسة |
| 4 — تكميلية | Analytics, Reports, Audit logs | أقل تكراراً |

---

## 11. الدمج مع main و CI/CD

### 11.1 قائمة التحقق قبل Merge

```
□ composer install نجح
□ composer test → 119+ اختبار خضراء
□ لم أنسخ .env.testing فوق .env
□ .env على clinic_management_system_3 للتطوير
□ Route جديد → Feature Test مضاف
□ Service حساس → Unit Test مضاف/محدّث
□ CI على PR أخضر
```

### 11.2 GitHub Actions

**الملف:** `.github/workflows/laravel-tests.yml`

| الخطوة | ماذا يفعل |
|--------|-----------|
| Trigger | push/PR على `main`, `master`, `feature/**`, `fix/**` |
| PHP | 8.2 + extensions (sqlite, mbstring, bcmath...) |
| إعداد | `cp .env.testing .env` + `config:clear` |
| فحص | `scripts/check-test-requirements.php` |
| تشغيل | `composer test:ci` |
| عند الفشل | رفع `storage/logs/laravel.log` كـ Artifact |

### 11.3 سير العمل الكامل

```
مطوّر → branch → composer test → PR → CI → Merge main → الفريق git pull
```

**لا تدمج إذا CI أحمر.**

---

## 12. استكشاف الأخطاء

### 12.1 مشاكل Composer

| الخطأ | الحل |
|-------|------|
| `vendor/... not found` | `composer install` |
| `corrupted zip archive` | `composer clear-cache` → احذف `vendor` → `composer install --prefer-source` |
| `composer` غير معروف | استخدم `C:\xampp\php\php.exe` مع مسار Composer |

### 12.2 مشاكل PHP

| الخطأ | الحل |
|-------|------|
| `could not find driver` | فعّل `pdo_sqlite` في `php.ini` |
| `Class ... not found` في vendor | `composer install` من جديد |
| كل الاختبارات `⨯` | `composer test:setup` — تحقق من extensions |
| `include_path=C:\php\pear` | استخدم PHP تبع XAMPP: `C:\xampp\php\php.exe` |

### 12.3 مشاكل الداتا

| العَرَض | السبب | الحل |
|--------|-------|------|
| بيانات اختبار في phpMyAdmin | Tests على MySQL | `config:clear` + `composer test` |
| تاب فاضي / بيانات اختفت | RefreshDatabase على داتا التطوير | لا تشغّل tests على `clinic_management_system_3` |
| لا شيء في phpMyAdmin بعد test | طبيعي — SQLite في الذاكرة | الداتا مؤقتة ولا تظهر في MySQL |

### 12.4 مشاكل CI

| العَرَض | الحل |
|--------|------|
| يمر محلياً ويفشل CI | `composer.lock` محدّث؟ مسار `Routes/` vs `routes/`؟ |
| Route not found Linux فقط | `ModuleApiRoutesTest` يكتشفها — صحّح حالة الأحرف |

---

## 13. الأسئلة الشائعة

**س: هل الاختبارات تمس `clinic_management_system_3`؟**
لا — `composer test` يستخدم `acms_testing` عبر `phpunit.xml`.

**س: أين أرى بيانات الاختبار؟**
في phpMyAdmin على قاعدة `acms_testing`.

**س: هل أحتاج إنشاء `acms_testing` يدوياً؟**
مرة واحدة: `composer test:db` أو `setup-test-db.bat`.

**س: لماذا `Mail::fake()`؟**
لمنع إرسال إيميلات حقيقية أثناء الاختبار.

**س: كيف أختبر Stripe Webhook؟**
استخدم `Mockery` لمحاكاة `Stripe\Webhook::constructEvent`.

**س: الفرق بين Feature و Unit؟**
Feature = HTTP + API كامل. Unit = Service class بدون HTTP.

**س: أين أضع اختبار موديول جديد؟**
حالياً: `tests/Feature/Api/`. مستقبلاً: `Modules/{Module}/tests/Feature/`.

---

## 14. مرجع الأوامر

### إعداد

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
```

### يومي

```bash
composer test                              # تشغيل كل الاختبارات
composer test -- --filter=AuthApiTest      # ملف محدد
php artisan test tests/Feature/Api/        # مجلد API
php artisan test --testsuite=Unit          # Unit فقط
composer test:setup                        # فحص PHP فقط
```

### بعد git pull

```bash
composer install
composer test
```

### قبل merge

```bash
composer test
```

### إصلاح بيئة

```bash
php artisan config:clear
php artisan cache:clear
composer clear-cache
composer install --prefer-source
```

### أوامر Composer المعرّفة في `composer.json`

| الأمر | الوظيفة |
|-------|---------|
| `composer test:db` | إنشاء قاعدة `acms_testing` إذا غير موجودة |
| `composer test` | فحص PHP + DB + config:clear + `php artisan test` |
| `composer test:ci` | للـ CI — فحص PHP + DB + `php artisan test` |
| `composer test:setup` | فحص PHP + DB + config:clear |

---


