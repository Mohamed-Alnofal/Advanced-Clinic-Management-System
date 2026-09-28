# Video Calling Architecture - Clinic Management System

> **حالة الوثيقة:** مسودة معمارية للمراجعة من الفريق التقني
> **آخر تحديث:** May 2026
> **المؤلف:** الفريق التقني
> **الحالة:** مقترح (Proposed)

---

## 1. الملخص التنفيذي (Executive Summary)

تطبيق إدارة العيادة Clinic Management System يحتاج إلى ميزة **مكالمات الفيديو** (Audio/Video Consultations) بين الأطباء والمرضى. هذه الوثيقة تحدد:

- **المتطلبات الوظيفية وغير الوظيفية**
- **مقارنة بين الحلول المتاحة**
- **الحل الموصى به: LiveKit**
- **المعمارية الكاملة (Backend + Frontend)**
- **متطلبات الامتثال HIPAA**
- **خارطة الطريق للتنفيذ**

### القرار النهائي

> ✅ **استخدام LiveKit** كحل لمكالمات الفيديو، عبر:
> - **Build Tier (مجاني)** أثناء التطوير والـ MVP
> - **Scale Tier ($500/شهر)** عند الإنتاج لضمان **HIPAA BAA**

---

## 2. متطلبات المشروع

### 2.1 المتطلبات الوظيفية

| # | المتطلب | الأولوية |
|---|---|---|
| FR-01 | اتصال فيديو ثنائي بين طبيب ومريض | 🔴 عالية |
| FR-02 | اتصال جماعي (طبيب + مريض + مرافق/مترجم) | 🟡 متوسطة |
| FR-03 | تسجيل المكالمات بناءً على موافقة الطرفين | 🟡 متوسطة |
| FR-04 | مشاركة الشاشة (لعرض النتائج/الأشعة) | 🟢 منخفضة |
| FR-05 | محادثة نصية أثناء المكالمة | 🟢 منخفضة |
| FR-06 | معاينة قبل الانضمام (ميكروفون/كاميرا) | 🔴 عالية |
| FR-07 | إعادة الاتصال التلقائي عند انقطاع الشبكة | 🔴 عالية |
| FR-08 | إشعارات Push عند بدء الموعد | 🔴 عالية |

### 2.2 المتطلبات غير الوظيفية

| # | المتطلب | الهدف |
|---|---|---|
| NFR-01 | **HIPAA Compliance** مع BAA رسمي | إلزامي |
| NFR-02 | تشفير الـ media (DTLS-SRTP) أثناء النقل | إلزامي |
| NFR-03 | تشفير التسجيلات at-rest | إلزامي |
| NFR-04 | Latency أقل من 400ms | عالٍ |
| NFR-05 | جودة صوت/فيديو تتأقلم مع الشبكة | عالٍ |
| NFR-06 | Audit Log كامل لكل حدث | إلزامي |
| NFR-07 | Token-based access (لا API keys على العميل) | إلزامي |
| NFR-08 | Scalable لـ 1000+ مكالمة متزامنة | متوسط |

### 2.3 قيود تقنية

- **Backend:** Laravel 11 + MySQL
- **Mobile:** Flutter (Android + iOS)
- **Authentication:** Laravel Sanctum
- **Realtime:** Laravel Reverb (للأحداث، ليس للـ media)

---

## 3. الحلول المتاحة - تحليل تفصيلي

### 3.1 المقارنة العامة

| المعيار | WebRTC P2P | Agora | **LiveKit** | Twilio Video | Daily.co |
|---|---|---|---|---|---|
| HIPAA BAA | ❌ | ❌ | ✅ Scale | ✅ | ✅ |
| Open Source | ✅ | ❌ | ✅ | ❌ | ❌ |
| Self-hosting | ✅ | ❌ | ✅ | ❌ | ❌ |
| Free tier | غير محدود* | محدود | 5K min/mo | لا | محدود |
| Flutter SDK | يدوي | ✅ | ✅ | ✅ | ✅ |
| Laravel SDK | ❌ | غير رسمي | ✅ | ✅ | ❌ |
| TURN Server | تبنيه | مدمج | مدمج | مدمج | مدمج |
| Recording | تبنيه | مدفوع | مدمج | مدفوع | مدمج |
| E2E Encryption | يدوي | محدود | ✅ | محدود | ✅ |
| **التقييم** | ❌ مرفوض | ❌ مرفوض | ✅ **موصى به** | 🟡 بديل غالي | 🟡 بديل |

> \* WebRTC P2P "مجاني" لكن يتطلب TURN server وبنية تحتية كاملة من جانبكم.

### 3.2 لماذا تم رفض WebRTC الخام (مع Reverb)؟

رغم أن البعض قد يقترح هذا الحل لكونه "مجاني":

| السبب | التفصيل |
|---|---|
| ❌ **لا يوجد BAA** | أي تسريب لبيانات طبية = مسؤولية قانونية كاملة |
| ❌ **P2P لا يصلح > 3 مشاركين** | يحتاج SFU |
| ❌ **TURN Server ضروري** | 60% من الشبكات (NAT) تتطلب TURN |
| ❌ **بنية تحتية كاملة** | Echo cancellation, jitter buffer, bitrate adaptation |
| ❌ **خبرة WebRTC عميقة** | 6-12 شهر تطوير من فريق متخصص |
| ❌ **Reverb للـ Signaling فقط** | لا يحل مشكلة الـ media stream |
| ❌ **لا تسجيل** | متطلب لبعض حالات الـ HIPAA |

### 3.3 لماذا تم رفض Agora؟

| السبب | التفصيل |
|---|---|
| ❌ **لا BAA متاح** | غير مناسب للتطبيقات الطبية |
| ⚠️ Vendor Lock-in | صعب الانتقال منها |
| ⚠️ تسعير غير شفاف | التفاوض مطلوب للـ Enterprise |
| ⚠️ ليس مفتوح المصدر | لا تحكم بالـ stack |

### 3.4 لماذا LiveKit؟

✅ **الأسباب الحاسمة:**

1. **HIPAA BAA متوفر** في خطة Scale - متطلب قانوني
2. **مفتوح المصدر** (Apache 2.0) - يمكن التحكم الكامل
3. **Self-hosting ممكن** - مرونة مستقبلية
4. **SFU Architecture** - يدعم مكالمات جماعية
5. **SDKs ناشطة** لـ Flutter و Laravel
6. **شفافية تسعير** ($0.40-0.50/1000 min)
7. **Free tier سخي** (5,000 دقيقة/شهر للتطوير)
8. **مجتمع نشط** و تحديثات منتظمة

---

## 4. المعمارية المقترحة

### 4.1 Diagram عام

```
┌────────────────────────────────────────────────────────────────────┐
│                        Flutter Application                         │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │ Auth (Sanctum)   │  │ Appointment View │  │ Video Call Screen│  │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘  │
│                                                       │            │
│                  ┌────────────────────────────────────┘            │
│                  ▼ livekit_client SDK                              │
└──────────────────┼─────────────────────────────────────────────────┘
                   │
       ┌───────────┴───────────┐
       │                       │
       ▼ ① Auth + API          ▼ ④ WebRTC Media (DTLS-SRTP)
┌─────────────────┐     ┌─────────────────────────────────┐
│ Laravel API     │     │ LiveKit Cloud / Self-hosted     │
│                 │     │                                 │
│ ┌─────────────┐ │     │ ┌─────────────┐ ┌─────────────┐ │
│ │ Sanctum     │ │     │ │ SFU Server  │ │ TURN Server │ │
│ ├─────────────┤ │     │ └─────────────┘ └─────────────┘ │
│ │ Token Gen   │◄┼──── │ ⑤ Webhooks                      │
│ │ Service     │ │     │                                 │
│ ├─────────────┤ │     └─────────────────────────────────┘
│ │ Audit Log   │ │              │
│ ├─────────────┤ │              │
│ │ Reverb      │◄┼──────────────┘ ⑥ Recording
│ │ (Signaling  │ │                    │
│ │  side data) │ │                    ▼
│ └─────────────┘ │              ┌──────────────┐
└─────────────────┘              │ S3 (KMS enc.)│
       │                         └──────────────┘
       │
       ▼
┌─────────────────┐
│ MySQL DB        │
│ - users         │
│ - appointments  │
│ - consultations │
│ - audit_logs    │
└─────────────────┘
```

### 4.2 تدفق المكالمة (Call Flow)

```
Patient                Doctor                Laravel              LiveKit
   │                     │                     │                    │
   │ 1. حجز موعد         │                     │                    │
   ├────────────────────────────────────────►  │                    │
   │                     │                     │                    │
   │                     │                     │ 2. إنشاء consultation
   │                     │                     │   (room_name=UUID) │
   │                     │                     │                    │
   │ 3. وقت الموعد + Push│                     │                    │
   │◄────────────────────┤◄─────────────────── │                    │
   │                     │                     │                    │
   │ 4. طلب JWT Token    │                     │                    │
   ├────────────────────────────────────────►  │                    │
   │                     │ 4. طلب JWT Token    │                    │
   │                     ├──────────────────►  │                    │
   │                     │                     │ 5. validate +      │
   │                     │                     │    audit + create  │
   │                     │                     │    LiveKit token   │
   │ 6. JWT (15 min TTL) │                     │                    │
   │◄────────────────────────────────────────  │                    │
   │                     │ 6. JWT              │                    │
   │                     │◄──────────────────  │                    │
   │                     │                     │                    │
   │ 7. الانضمام للغرفة (room_uuid)            │                    │
   ├────────────────────────────────────────────────────────────►   │
   │                     │ 7. الانضمام         │                    │
   │                     ├────────────────────────────────────►     │
   │                     │                     │                    │
   │ 8. WebRTC P2P (via SFU) - Audio/Video                          │
   │◄─────────────────► │ ◄────────────────────────────────────►    │
   │                     │                     │                    │
   │                     │                     │ 9. Webhook events: │
   │                     │                     │ - participant_joined
   │                     │                     │ - recording_started│
   │                     │                     │◄───────────────────┤
   │                     │                     │                    │
   │                     │                     │ 10. Audit log      │
   │                     │                     │                    │
   │ 11. إنهاء المكالمة                        │                    │
   ├────────────────────►                      │                    │
   │                     │                     │ 12. Webhook:       │
   │                     │                     │   room_finished    │
   │                     │                     │◄───────────────────┤
   │                     │                     │                    │
   │                     │                     │ 13. Update         │
   │                     │                     │   consultation     │
   │                     │                     │   (duration, etc.) │
```

### 4.3 المكونات (Components)

#### 4.3.1 Backend (Laravel)

```
app/
├── Services/
│   └── Video/
│       ├── LiveKitService.php          # التكامل مع LiveKit API
│       ├── TokenGeneratorService.php   # توليد JWT للغرف
│       ├── WebhookHandlerService.php   # معالجة Webhooks
│       └── ConsultationService.php     # منطق الاستشارة
├── Http/Controllers/Api/
│   └── Video/
│       ├── ConsultationController.php  # CRUD للاستشارات
│       ├── TokenController.php         # POST /api/video/token
│       └── WebhookController.php       # POST /api/livekit/webhook
├── Models/
│   ├── Consultation.php
│   ├── ConsultationParticipant.php
│   └── ConsultationAuditLog.php
├── Events/
│   ├── ConsultationStarted.php
│   ├── ConsultationEnded.php
│   ├── ParticipantJoined.php
│   └── ParticipantLeft.php
├── Jobs/
│   ├── ProcessLiveKitWebhook.php
│   ├── DeleteExpiredRecordings.php
│   └── GenerateAuditReport.php
└── Policies/
    └── ConsultationPolicy.php          # ACL للاستشارات
```

#### 4.3.2 Frontend (Flutter)

```
lib/
├── features/
│   └── video_call/
│       ├── data/
│       │   ├── models/
│       │   │   ├── consultation.dart
│       │   │   └── token_response.dart
│       │   └── repositories/
│       │       └── video_call_repository.dart
│       ├── domain/
│       │   ├── entities/
│       │   └── usecases/
│       │       ├── join_consultation.dart
│       │       └── leave_consultation.dart
│       └── presentation/
│           ├── pages/
│           │   ├── pre_call_page.dart      # اختبار الكاميرا
│           │   ├── consultation_page.dart  # الشاشة الرئيسية
│           │   └── post_call_page.dart     # تقييم/ملاحظات
│           ├── widgets/
│           │   ├── video_renderer.dart
│           │   ├── controls_bar.dart
│           │   ├── network_indicator.dart
│           │   └── permissions_dialog.dart
│           └── providers/
│               └── consultation_state.dart
└── core/
    └── services/
        └── livekit_service.dart
```

### 4.4 المخطط الزمني (DB Schema)

#### `consultations`
```sql
CREATE TABLE consultations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NOT NULL,
    room_name CHAR(36) NOT NULL UNIQUE,        -- UUID, NOT patient name!
    status ENUM('scheduled', 'in_progress', 'completed', 'missed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    duration_seconds INT UNSIGNED NULL,
    recording_enabled BOOLEAN DEFAULT FALSE,
    recording_url TEXT NULL,                   -- مشفّر (Encrypted Cast)
    recording_retention_until TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_room_name (room_name),
    INDEX idx_status (status),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);
```

#### `consultation_participants`
```sql
CREATE TABLE consultation_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consultation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('host', 'participant', 'observer') NOT NULL DEFAULT 'participant',
    joined_at TIMESTAMP NULL,
    left_at TIMESTAMP NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    device_id VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_consultation_user (consultation_id, user_id),
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### `consultation_audit_logs` (مطلب HIPAA)
```sql
CREATE TABLE consultation_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consultation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(50) NOT NULL,
    -- 'token_issued', 'joined', 'left', 'recording_started',
    -- 'recording_stopped', 'permission_denied', 'connection_lost'
    ip_address VARCHAR(45) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_consultation (consultation_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

---

## 5. الامتثال للـ HIPAA

### 5.1 المتطلبات الإلزامية

| المتطلب | التطبيق |
|---|---|
| 🔐 **BAA رسمي** | LiveKit Scale Plan ($500/شهر) |
| 🔒 **Encryption in Transit** | DTLS-SRTP من LiveKit (تلقائي) |
| 🔒 **Encryption at Rest** | S3 + KMS للتسجيلات |
| 📝 **Audit Trail كامل** | جدول `consultation_audit_logs` |
| 🎯 **Access Control** | Sanctum + Policies + Token short-lived |
| 🚫 **No PHI in Metadata** | استخدام UUID للـ room names |
| 🗑️ **Data Retention Policy** | حذف تلقائي للتسجيلات بعد المدة |
| 🔑 **API Secrets على Server فقط** | لا تظهر على Flutter |

### 5.2 ممارسات يجب تجنبها (Anti-patterns)

❌ **لا تفعل:**
- وضع اسم المريض في `room_name` (مثل `dr-ahmad-and-mona-call`)
- تخزين تسجيلات بدون تشفير
- إرسال PHI في log messages
- استخدام Firebase Analytics في شاشة المكالمة
- توليد LiveKit Token من الـ Flutter مباشرة
- Token صلاحيته ساعات أو أيام (يجب ≤ 15 دقيقة)
- تخزين رقم الهاتف/الاسم في Webhook metadata
- Webhooks بدون توقيع تحقق

✅ **افعل:**
- استخدم UUID للغرف
- شفّر التسجيلات قبل الرفع لـ S3
- اعمل sanitize للـ logs (`[PATIENT_REDACTED]`)
- استثنِ شاشات المكالمة من Analytics
- توليد Token من Laravel فقط مع validation
- Token قصير المدة (10-15 دقيقة)
- استخدم opaque IDs في metadata
- تحقق من توقيع كل Webhook

---

## 6. الأمان (Security)

### 6.1 طبقات الحماية

```
┌────────────────────────────────────────────┐
│ Layer 1: Network                           │
│ - HTTPS/TLS for all REST                   │
│ - WSS for WebSocket                        │
│ - DTLS-SRTP for media                      │
└────────────────────────────────────────────┘
┌────────────────────────────────────────────┐
│ Layer 2: Authentication                    │
│ - Sanctum tokens (rotated)                 │
│ - LiveKit JWT (short-lived)                │
└────────────────────────────────────────────┘
┌────────────────────────────────────────────┐
│ Layer 3: Authorization                     │
│ - Policies (Laravel)                       │
│ - Per-room permissions in JWT              │
└────────────────────────────────────────────┘
┌────────────────────────────────────────────┐
│ Layer 4: Application                       │
│ - Rate limiting on token endpoint          │
│ - Webhook signature verification           │
│ - Pre-call validation                      │
└────────────────────────────────────────────┘
┌────────────────────────────────────────────┐
│ Layer 5: Data                              │
│ - Encrypted casts (Laravel)                │
│ - KMS encryption (S3)                      │
│ - DB encryption at rest                    │
└────────────────────────────────────────────┘
```

### 6.2 Token Generation - Best Practice

```php
// مثال على Service مع كل البروتوكولات
class TokenGeneratorService
{
    public function generateForConsultation(
        Consultation $consultation,
        User $user
    ): string {
        // 1. Authorization check
        Gate::authorize('join', $consultation);

        // 2. Time window validation (5 min before until end)
        $this->validateTimeWindow($consultation);

        // 3. Generate JWT with minimal claims
        $token = LiveKitToken::create()
            ->withIdentity((string) $user->id)
            ->withName($this->getAnonymizedName($user))
            ->withGrants([
                'room' => $consultation->room_name,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ])
            ->withTtl(now()->addMinutes(15));

        // 4. Audit log
        $this->auditLogger->log(
            $consultation,
            $user,
            'token_issued',
            ['ip' => request()->ip()]
        );

        return $token->toJwt();
    }
}
```

### 6.3 Rate Limiting

| Endpoint | Rate Limit |
|---|---|
| `POST /api/video/token` | 10/min/user |
| `POST /api/livekit/webhook` | 100/min/IP (LiveKit IPs) |
| `GET /api/consultations/{id}` | 60/min/user |

---

## 7. تجربة المستخدم (UX)

### 7.1 شاشات Flutter

#### Pre-Call Screen
- معاينة الكاميرا
- اختبار الميكروفون (مع indicator)
- اختيار جهاز (إذا متعدد)
- مؤشر جودة الشبكة
- زر "انضم الآن"

#### In-Call Screen
- Local video (Picture-in-Picture)
- Remote video (full screen)
- Controls bar:
  - 🎤 Mute/Unmute
  - 📹 Camera On/Off
  - 🔄 Switch Camera
  - 💬 Chat
  - 📺 Share Screen
  - ☎️ End Call
- Network quality indicator
- Recording indicator (إذا مفعّل)
- Connection state messages

#### Post-Call Screen
- ملخص المكالمة (المدة، الجودة)
- تقييم (1-5 نجوم)
- ملاحظات اختيارية
- زر "العودة للوحة التحكم"

### 7.2 إدارة الأذونات

```dart
// طلب الأذونات بشكل واضح
Future<void> requestPermissions() async {
  final camera = await Permission.camera.request();
  final mic = await Permission.microphone.request();

  if (camera.isPermanentlyDenied || mic.isPermanentlyDenied) {
    // افتح الإعدادات
  }
}
```

### 7.3 معالجة انقطاع الشبكة

```dart
room.events.listen((event) {
  if (event is RoomReconnectingEvent) {
    showOverlay('جاري إعادة الاتصال...');
  }
  if (event is RoomReconnectedEvent) {
    hideOverlay();
  }
  if (event is RoomDisconnectedEvent) {
    if (event.reason == DisconnectReason.networkError) {
      retryConnection();
    }
  }
});
```

---

## 8. خطة التنفيذ (Roadmap)

### المرحلة 1: الأساس (Week 1-2)
- [ ] إعداد LiveKit Cloud (Build Tier)
- [ ] إنشاء جداول `consultations`, `participants`, `audit_logs`
- [ ] `LiveKitService` و `TokenGeneratorService`
- [ ] Endpoint توليد Tokens
- [ ] Webhook handler

### المرحلة 2: Flutter MVP (Week 3-4)
- [ ] إضافة `livekit_client` SDK
- [ ] Pre-call screen
- [ ] In-call screen أساسي (audio + video)
- [ ] التكامل مع API

### المرحلة 3: ميزات متقدمة (Week 5-6)
- [ ] Reconnection logic
- [ ] Network quality indicators
- [ ] Screen sharing
- [ ] Chat in-call
- [ ] Push notifications للمواعيد

### المرحلة 4: HIPAA Hardening (Week 7-8)
- [ ] تطبيق Encrypted Casts
- [ ] Audit logging شامل
- [ ] Recording (اختياري) + S3 KMS
- [ ] الترقية لـ Scale Plan + توقيع BAA
- [ ] اختبار اختراق

### المرحلة 5: الإطلاق (Week 9-10)
- [ ] Beta مع 10 أطباء
- [ ] مراقبة Logs
- [ ] تحسين الأداء
- [ ] الإطلاق الكامل

---

## 9. التكاليف المتوقعة

| البند | المرحلة | التكلفة الشهرية |
|---|---|---|
| LiveKit Build (Free) | التطوير + MVP | $0 |
| LiveKit Ship | Beta (50-200 مريض) | $50 |
| LiveKit Scale + BAA | الإنتاج | $500 |
| AWS S3 (تسجيلات) | عند تفعيل | ~$20-50 |
| Twilio (SMS) | إشعارات | ~$30 |
| **الإجمالي عند الإنتاج** | | **~$580-630/شهر** |

---

## 10. المخاطر (Risks)

| المخاطرة | الاحتمال | التأثير | الخطة |
|---|---|---|---|
| تأخر توقيع BAA | منخفض | عالٍ | تواصل مبكر مع LiveKit |
| تجاوز Free Tier بسرعة | متوسط | متوسط | مراقبة الاستهلاك + تنبيهات |
| مشاكل اتصال على شبكات الجوال | عالٍ | متوسط | Reconnection logic + TURN |
| تسريب بيانات | منخفض | كارثي | Audit + Penetration Testing |
| تكاليف تتجاوز الميزانية | متوسط | متوسط | Optimize bitrate + duration limits |

---

## 11. مراجع تقنية

### LiveKit
- [Documentation](https://docs.livekit.io)
- [Flutter SDK](https://pub.dev/packages/livekit_client)
- [PHP/Laravel SDK](https://github.com/agence104/livekit-server-sdk-php)
- [Webhooks](https://docs.livekit.io/realtime/server/webhooks/)
- [HIPAA & BAA](https://livekit.io/blog/hipaa-livekit-cloud)

### HIPAA
- [HHS HIPAA Guidance](https://www.hhs.gov/hipaa/index.html)
- [HIPAA Journal - Telehealth](https://www.hipaajournal.com/hipaa-and-telehealth/)

### Laravel
- [Sanctum](https://laravel.com/docs/sanctum)
- [Encrypted Casts](https://laravel.com/docs/eloquent-mutators#encrypted-casting)
- [Reverb](https://laravel.com/docs/reverb)

### Flutter
- [LiveKit Flutter Quickstart](https://docs.livekit.io/realtime/quickstarts/flutter/)
- [CallKit (iOS)](https://pub.dev/packages/flutter_callkit_incoming)

---

## 12. الموافقات (Approvals)

| الدور | الاسم | التاريخ | التوقيع |
|---|---|---|---|
| Tech Lead | _____ | _____ | _____ |
| Backend Lead | _____ | _____ | _____ |
| Mobile Lead | _____ | _____ | _____ |
| Security/Compliance | _____ | _____ | _____ |
| Project Manager | _____ | _____ | _____ |

---

## ملاحظة ختامية

هذه الوثيقة **حية** (Living Document) - يجب تحديثها مع كل تغيير معماري.
أي اقتراح تعديل: افتح Pull Request على هذا الملف.
