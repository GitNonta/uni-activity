# Implementation Plan
## University Activity Registration System

> **Stack:** Laravel 11 · MySQL 8 · Tailwind CSS · Livewire · Alpine.js  
> **Platform:** Mobile-first PWA · QR Check-in · Role-based Access  
> **Estimated Duration:** 8–10 สัปดาห์

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Implementation Phases](#2-implementation-phases)
3. [Database Schema](#3-database-schema)
4. [Feature Implementation Detail](#4-feature-implementation-detail)
5. [Laravel File Structure](#5-laravel-file-structure)
6. [UI Pages Specification](#6-ui-pages-specification)
7. [Setup & Installation](#7-setup--installation)
8. [Testing Strategy](#8-testing-strategy)
9. [Timeline Summary](#9-timeline-summary)
10. [Risks & Mitigation](#10-risks--mitigation)

---

## 1. Project Overview

ระบบขอเข้าร่วมกิจกรรมมหาวิทยาลัย ให้นักศึกษาสามารถสมัครเข้าร่วมกิจกรรม บันทึกชั่วโมงกิจกรรม และดูสรุปผลการเข้าร่วมทั้งหมด โดยใช้เพียง **รหัสนักศึกษา** ในการเข้าถึงระบบ

### 1.1 วัตถุประสงค์

- นักศึกษาสามารถสมัคร/ลงทะเบียนเข้าร่วมกิจกรรมได้ผ่านมือถือและแท็บเล็ต
- ระบบบันทึกการเข้าร่วมด้วย QR Code ในช่วงเวลาที่กำหนด
- แสดงชั่วโมงกิจกรรม สถานะ และความก้าวหน้าของแต่ละนักศึกษา
- เจ้าหน้าที่สามารถสร้าง จัดการ และรายงานผลกิจกรรมได้

### 1.2 User Roles

| Role | หน้าที่ |
|---|---|
| `student` | สมัครสมาชิก, ดูกิจกรรม, ลงทะเบียน, เช็คอิน, ดูประวัติ |
| `staff` | สร้าง/จัดการกิจกรรม, ดู dashboard, ออกรายงาน |
| `verifier` | อนุมัติการลงทะเบียน, ยืนยันการเข้าร่วม |

### 1.3 Scope

**In Scope (v1.0)**
- ระบบ Auth ด้วยรหัสนักศึกษา
- จัดการกิจกรรมและลงทะเบียน
- QR Check-in ตามช่วงเวลา
- ประวัติและสรุปชั่วโมงกิจกรรม
- Admin dashboard และรายงาน

**Out of Scope (v1.0)**
- Mobile App (iOS/Android native)
- การชำระเงิน/ค่าสมัคร
- Video streaming / online events
- Integration กับระบบ ERP มหาวิทยาลัย

---

## 2. Implementation Phases

| Phase | รายละเอียด | Deliverables | ระยะเวลา |
|---|---|---|---|
| **Phase 1** Project Setup | ติดตั้ง Laravel 11 + Breeze, Config MySQL, ติดตั้ง packages, ตั้งค่า Tailwind + Alpine.js | Laravel project skeleton, Git repo, ระบบ Auth เบื้องต้น | 3–4 วัน |
| **Phase 2** Database | สร้าง Migrations 6 ตาราง, Seeders, Eloquent Models, Relationships | Migration files ครบ, Seeders + Factory, Models พร้อม relations | 4–5 วัน |
| **Phase 3** Core Features | Student pages, Admin CRUD, Activity status logic, QR Check-in, Time-lock | Student pages ครบ, Admin CRUD activities, QR check-in ทำงานได้ | 10–12 วัน |
| **Phase 4** UI/UX Mobile | Responsive Blade components, Mobile navigation, Loading states, PWA manifest | UI ทุกหน้าพร้อมใช้งาน, Mobile UX ผ่าน 360px, PWA installable | 7–8 วัน |
| **Phase 5** Testing & Deploy | Unit Tests, Feature Tests, Manual testing, Performance tuning, Deploy | Test coverage >70%, Bug fixes ครบ, Production deployment | 5–6 วัน |

---

## 3. Database Schema

### 3.1 `users`

เก็บข้อมูลนักศึกษาและเจ้าหน้าที่ ใช้ `student_id` เป็น identifier หลักในการ login

| Column | Type | หมายเหตุ |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key, Auto Increment |
| `student_id` | VARCHAR(20) | UNIQUE — รหัสนักศึกษา ใช้ login |
| `full_name` | VARCHAR(255) | ชื่อ-นามสกุล |
| `faculty` | VARCHAR(100) | คณะ |
| `department` | VARCHAR(100) | สาขาวิชา |
| `year` | TINYINT | ชั้นปี (1–6) |
| `role` | ENUM | `'student'`, `'staff'`, `'verifier'` |
| `is_active` | BOOLEAN | DEFAULT true |
| `created_at` / `updated_at` | TIMESTAMP | Auto managed |

```php
// Migration
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('student_id', 20)->unique();
    $table->string('full_name');
    $table->string('faculty', 100)->nullable();
    $table->string('department', 100)->nullable();
    $table->tinyInteger('year')->nullable();
    $table->enum('role', ['student', 'staff', 'verifier'])->default('student');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

---

### 3.2 `activities`

ตารางหลักสำหรับกิจกรรมทั้งหมด มี status auto-computed จาก dates

| Column | Type | หมายเหตุ |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key |
| `title` | VARCHAR(255) | ชื่อกิจกรรม |
| `description` | TEXT | รายละเอียดกิจกรรม |
| `location` | VARCHAR(255) | สถานที่ |
| `activity_date` | DATE | วันที่จัดกิจกรรม |
| `start_time` / `end_time` | TIME | เวลาเริ่ม–สิ้นสุด |
| `activity_hours` | DECIMAL(4,1) | ชั่วโมงกิจกรรม เช่น 2.5 |
| `max_participants` | INT | จำนวนรับสูงสุด |
| `register_open_at` | DATETIME | เปิดรับลงทะเบียน |
| `register_close_at` | DATETIME | ปิดรับลงทะเบียน |
| `checkin_open_at` | DATETIME | เปิดให้ check-in |
| `checkin_close_at` | DATETIME | ปิด check-in |
| `is_mandatory` | BOOLEAN | กิจกรรมบังคับ |
| `category_id` | BIGINT FK | → `activity_categories` |
| `created_by` | BIGINT FK | → `users` (staff) |
| `qr_token` | VARCHAR(64) | UNIQUE token สำหรับ QR |
| `image_path` | VARCHAR(255) | NULLABLE รูปประกอบ |
| `status` | ENUM | `'upcoming'`,`'open'`,`'full'`,`'ongoing'`,`'done'`,`'cancelled'` |

```php
Schema::create('activities', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('location')->nullable();
    $table->date('activity_date');
    $table->time('start_time');
    $table->time('end_time');
    $table->decimal('activity_hours', 4, 1)->default(0);
    $table->integer('max_participants')->default(0);
    $table->dateTime('register_open_at');
    $table->dateTime('register_close_at');
    $table->dateTime('checkin_open_at');
    $table->dateTime('checkin_close_at');
    $table->boolean('is_mandatory')->default(false);
    $table->foreignId('category_id')->nullable()->constrained('activity_categories')->nullOnDelete();
    $table->foreignId('created_by')->constrained('users');
    $table->string('qr_token', 64)->unique()->nullable();
    $table->string('image_path')->nullable();
    $table->enum('status', ['upcoming','open','full','ongoing','done','cancelled'])->default('upcoming');
    $table->timestamps();

    $table->index(['status', 'activity_date']);
    $table->index('register_open_at');
    $table->index('checkin_open_at');
});
```

---

### 3.3 `registrations`

| Column | Type | หมายเหตุ |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key |
| `user_id` | BIGINT FK | → `users` |
| `activity_id` | BIGINT FK | → `activities` |
| `status` | ENUM | `'pending'`,`'approved'`,`'cancelled'`,`'rejected'` |
| `registered_at` | TIMESTAMP | เวลาที่ลงทะเบียน |
| `cancelled_at` | TIMESTAMP | NULLABLE |
| `note` | TEXT | NULLABLE หมายเหตุ |

```php
Schema::create('registrations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
    $table->enum('status', ['pending','approved','cancelled','rejected'])->default('pending');
    $table->timestamp('registered_at')->useCurrent();
    $table->timestamp('cancelled_at')->nullable();
    $table->text('note')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'activity_id']); // ลงทะเบียนซ้ำไม่ได้
    $table->index(['activity_id', 'status']);
});
```

---

### 3.4 `attendances`

| Column | Type | หมายเหตุ |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key |
| `user_id` | BIGINT FK | → `users` |
| `activity_id` | BIGINT FK | → `activities` |
| `checked_in_at` | TIMESTAMP | เวลาที่เช็คอิน |
| `method` | ENUM | `'qr_scan'`, `'manual'` |
| `verified_by` | BIGINT FK | NULLABLE → `users` (staff) |
| `is_verified` | BOOLEAN | DEFAULT false |
| `ip_address` | VARCHAR(45) | บันทึก IP สำหรับ audit |

```php
Schema::create('attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users');
    $table->foreignId('activity_id')->constrained('activities');
    $table->timestamp('checked_in_at')->useCurrent();
    $table->enum('method', ['qr_scan', 'manual'])->default('qr_scan');
    $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
    $table->boolean('is_verified')->default(false);
    $table->string('ip_address', 45)->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'activity_id']); // เช็คอินซ้ำไม่ได้
});
```

---

### 3.5 `activity_categories`

| Column | Type | หมายเหตุ |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key |
| `name` | VARCHAR(100) | ชื่อประเภท เช่น `'กีฬา'`, `'วิชาการ'` |
| `description` | TEXT | NULLABLE |
| `required_hours` | DECIMAL(4,1) | ชั่วโมงขั้นต่ำที่ต้องทำ |
| `icon` | VARCHAR(50) | ชื่อ icon class |
| `color` | VARCHAR(20) | สี hex สำหรับแสดง UI |

---

### 3.6 `notifications`

| Column | Type | หมายเหตุ |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key |
| `user_id` | BIGINT FK | → `users` |
| `title` | VARCHAR(255) | หัวข้อการแจ้งเตือน |
| `message` | TEXT | รายละเอียด |
| `type` | VARCHAR(50) | `'registration'`,`'reminder'`,`'checkin'`,`'system'` |
| `is_read` | BOOLEAN | DEFAULT false |
| `created_at` | TIMESTAMP | Auto managed |

### Entity Relationship

```
users ──< registrations >── activities
users ──< attendances   >── activities
activities >── activity_categories
users ──< notifications
```

---

## 4. Feature Implementation Detail

### 4.1 Authentication

ระบบ Auth ใช้เพียง `student_id` เท่านั้น ไม่มี email / password ทั่วไป

**Login Flow**
1. นักศึกษากรอก `student_id` ในหน้า `/login`
2. ระบบตรวจสอบ `student_id` ในตาราง `users`
3. ถ้าพบ → สร้าง session และ redirect ตาม role
4. ถ้าไม่พบ → แสดง `'รหัสนักศึกษาไม่ถูกต้อง'`

**Register Flow**
1. กรอก `student_id`, ชื่อ-นามสกุล, คณะ, สาขา, ชั้นปี
2. ระบบตรวจสอบว่า `student_id` ไม่ซ้ำ
3. สร้าง record ใน `users` (role = `'student'`)
4. Redirect ไปหน้า `/activities` ทันที

```php
// StudentAuthController.php
public function login(Request $request)
{
    $request->validate(['student_id' => 'required|string']);

    $user = User::where('student_id', $request->student_id)
                ->where('is_active', true)
                ->first();

    if (!$user) {
        return back()->withErrors(['student_id' => 'รหัสนักศึกษาไม่ถูกต้อง']);
    }

    Auth::login($user);
    return redirect()->intended(route('activities.index'));
}
```

---

### 4.2 Activity Status (Auto-computed)

Status คำนวณอัตโนมัติจาก datetime และจำนวนผู้ลงทะเบียน

```php
// ActivityStatusService.php
public function computeStatus(Activity $activity): string
{
    $now = now();
    $registered = $activity->registrations()->whereIn('status', ['pending','approved'])->count();

    if ($activity->status === 'cancelled') return 'cancelled';
    if ($now > $activity->checkin_close_at)  return 'done';
    if ($now >= $activity->checkin_open_at)  return 'ongoing';
    if ($registered >= $activity->max_participants) return 'full';
    if ($now >= $activity->register_open_at && $now <= $activity->register_close_at) return 'open';
    return 'upcoming';
}
```

**สถานะและความหมาย**

| Status | Badge | ความหมาย |
|---|---|---|
| `upcoming` | 🔵 เร็วๆนี้ | ยังไม่เปิดรับสมัคร |
| `open` | 🟢 เปิดรับสมัคร | รับลงทะเบียนได้ ยังมีที่ว่าง |
| `full` | 🔴 เต็ม | ลงทะเบียนครบตามจำนวน |
| `ongoing` | 🟡 กำลังดำเนินการ | กิจกรรมกำลังเกิดขึ้น |
| `done` | ⚫ เสร็จสิ้น | กิจกรรมจบแล้ว |
| `cancelled` | ⛔ ยกเลิก | ถูกยกเลิก |

---

### 4.3 QR Check-in System

ระบบบันทึกการเข้าร่วมด้วย QR Code ที่มี time-lock และตรวจสอบ registration

**Flow การ Check-in**
1. Admin สร้างกิจกรรม → ระบบ generate `qr_token` unique ต่อ activity
2. QR Code แสดงที่หน้างาน (Admin พิมพ์หรือแสดงบน projector)
3. นักศึกษาแสกน QR → เปิด `/check-in/{token}` ในมือถือ
4. ระบบตรวจสอบ: `checkin_open_at ≤ now ≤ checkin_close_at`
5. ระบบตรวจสอบว่า user มี registration `status = 'approved'`
6. ตรวจสอบว่ายังไม่ได้ check-in ซ้ำ
7. สร้าง record ใน `attendances` + แสดงหน้ายืนยัน

```php
// CheckInService.php
public function processCheckIn(string $token, User $user): array
{
    $activity = Activity::where('qr_token', $token)->firstOrFail();
    $now = now();

    // ตรวจสอบช่วงเวลา
    if ($now < $activity->checkin_open_at) {
        return ['success' => false, 'message' => 'ยังไม่ถึงเวลาเช็คอิน'];
    }
    if ($now > $activity->checkin_close_at) {
        return ['success' => false, 'message' => 'หมดเวลาเช็คอินแล้ว'];
    }

    // ตรวจสอบการลงทะเบียน
    $registration = Registration::where('user_id', $user->id)
        ->where('activity_id', $activity->id)
        ->where('status', 'approved')
        ->first();

    if (!$registration) {
        return ['success' => false, 'message' => 'คุณไม่ได้ลงทะเบียนกิจกรรมนี้'];
    }

    // ตรวจสอบซ้ำ
    if (Attendance::where('user_id', $user->id)->where('activity_id', $activity->id)->exists()) {
        return ['success' => false, 'message' => 'คุณเช็คอินไปแล้ว'];
    }

    // บันทึก
    Attendance::create([
        'user_id'     => $user->id,
        'activity_id' => $activity->id,
        'method'      => 'qr_scan',
        'ip_address'  => request()->ip(),
    ]);

    return ['success' => true, 'activity' => $activity];
}
```

---

### 4.4 Activity Listing

```php
// ActivityController.php
public function index(Request $request)
{
    $activities = Activity::with(['category', 'registrations'])
        ->when($request->status,   fn($q) => $q->where('status', $request->status))
        ->when($request->category, fn($q) => $q->where('category_id', $request->category))
        ->when($request->mandatory, fn($q) => $q->where('is_mandatory', true))
        ->when($request->search,   fn($q) => $q->where('title', 'like', "%{$request->search}%"))
        ->orderBy('activity_date')
        ->paginate(12);

    return view('activities.index', compact('activities'));
}
```

**ฟีเจอร์หน้ารายการ**
- การ์ดกิจกรรมพร้อม status badge และ badge "บังคับ" สีแดง
- แสดงชั่วโมงกิจกรรม, จำนวนที่เหลือ, วันที่
- Filter: ประเภท, สถานะ, บังคับ/ไม่บังคับ
- Search: ค้นหาชื่อหรือสถานที่
- Infinite scroll / pagination สำหรับ mobile

---

### 4.5 History & Summary

```php
// StudentController.php
public function summary(User $user)
{
    $attendances = Attendance::with('activity.category')
        ->where('user_id', $user->id)
        ->get();

    $totalHours = $attendances->sum('activity.activity_hours');

    $byCategory = $attendances->groupBy('activity.category.name')
        ->map(fn($group) => $group->sum('activity.activity_hours'));

    $mandatoryPending = Activity::where('is_mandatory', true)
        ->whereDoesntHave('attendances', fn($q) => $q->where('user_id', $user->id))
        ->get();

    return view('student.summary', compact('totalHours', 'byCategory', 'mandatoryPending'));
}
```

---

### 4.6 Admin: สร้างกิจกรรม

**Fields ที่ต้องกรอก**

| Field | Input Type | Validation |
|---|---|---|
| ชื่อกิจกรรม | text | required, max:255 |
| รายละเอียด | textarea | nullable |
| สถานที่ | text | required |
| วันที่กิจกรรม | date | required, after:today |
| เวลาเริ่ม–สิ้นสุด | time | required |
| ชั่วโมงกิจกรรม | number | required, min:0.5 |
| จำนวนรับสูงสุด | number | required, min:1 |
| เปิด–ปิดลงทะเบียน | datetime | required |
| ช่วงเวลา check-in | datetime | required |
| ประเภทกิจกรรม | select | required |
| กิจกรรมบังคับ | toggle | boolean |
| รูปภาพ | file | nullable, image, max:2048 |

---

## 5. Laravel File Structure

### 5.1 App Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── StudentAuthController.php
│   │   ├── ActivityController.php
│   │   ├── RegistrationController.php
│   │   ├── CheckInController.php
│   │   ├── StudentController.php
│   │   └── Admin/
│   │       ├── ActivityAdminController.php
│   │       ├── ParticipantController.php
│   │       └── ReportController.php
│   ├── Middleware/
│   │   ├── CheckRole.php
│   │   └── CheckActivityStatus.php
│   └── Requests/
│       ├── StoreActivityRequest.php
│       ├── CheckInRequest.php
│       └── RegisterActivityRequest.php
├── Models/
│   ├── User.php
│   ├── Activity.php
│   ├── Registration.php
│   ├── Attendance.php
│   ├── ActivityCategory.php
│   └── Notification.php
└── Services/
    ├── ActivityStatusService.php    ← คำนวณ status อัตโนมัติ
    ├── QrCodeService.php            ← สร้าง/ตรวจสอบ QR token
    ├── CheckInService.php           ← logic check-in + validation
    └── ActivitySummaryService.php   ← คำนวณสรุปชั่วโมง
```

### 5.2 Views Structure

```
resources/views/
├── layouts/
│   ├── app.blade.php          ← Student layout (bottom nav)
│   └── admin.blade.php        ← Admin layout (sidebar)
├── auth/
│   ├── login.blade.php
│   └── register.blade.php
├── activities/
│   ├── index.blade.php        ← รายการกิจกรรม
│   └── show.blade.php         ← รายละเอียดกิจกรรม
├── student/
│   ├── my-activities.blade.php
│   ├── history.blade.php
│   ├── summary.blade.php
│   ├── profile.blade.php
│   └── notifications.blade.php
├── checkin/
│   ├── scan.blade.php
│   └── success.blade.php
├── admin/
│   ├── dashboard.blade.php
│   ├── activities/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   └── participants.blade.php
│   ├── checkin/
│   │   └── monitor.blade.php  ← real-time check-in monitor
│   └── reports/
│       └── index.blade.php
└── components/
    ├── activity-card.blade.php
    ├── status-badge.blade.php
    ├── hours-progress.blade.php
    └── mobile-nav.blade.php
```

### 5.3 Routes (web.php)

```php
<?php

use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\Admin\ActivityAdminController;
use App\Http\Controllers\Admin\ReportController;

// ── Public ──────────────────────────────────────────
Route::get('/',       fn() => redirect()->route('activities.index'));
Route::get('/login',  [StudentAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [StudentAuthController::class, 'login']);
Route::post('/logout',[StudentAuthController::class, 'logout'])->name('logout');
Route::get('/register',  [StudentAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [StudentAuthController::class, 'register']);

// ── Student (Auth Required) ──────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/activities',         [ActivityController::class, 'index'])->name('activities.index');
    Route::get('/activities/{id}',    [ActivityController::class, 'show'])->name('activities.show');
    Route::post('/activities/{id}/register', [RegistrationController::class, 'store']);
    Route::delete('/registrations/{id}',     [RegistrationController::class, 'destroy']);
    Route::get('/check-in/{token}',   [CheckInController::class, 'show'])->name('checkin.show');
    Route::post('/check-in/{token}',  [CheckInController::class, 'store'])->name('checkin.store');
    Route::get('/my-activities',      [StudentController::class, 'myActivities'])->name('student.my');
    Route::get('/history',            [StudentController::class, 'history'])->name('student.history');
    Route::get('/summary',            [StudentController::class, 'summary'])->name('student.summary');
    Route::get('/profile',            [StudentController::class, 'profile'])->name('student.profile');
    Route::get('/notifications',      [StudentController::class, 'notifications'])->name('student.notifications');
    Route::get('/summary/export',     [StudentController::class, 'exportPdf'])->name('student.export');
});

// ── Admin (Auth + Role: staff) ───────────────────────
Route::middleware(['auth', 'role:staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                              fn() => redirect()->route('admin.dashboard'));
    Route::get('/dashboard',                     [ActivityAdminController::class, 'dashboard'])->name('dashboard');
    Route::resource('activities',                ActivityAdminController::class);
    Route::get('activities/{id}/participants',   [ActivityAdminController::class, 'participants'])->name('activities.participants');
    Route::get('activities/{id}/checkin',        [ActivityAdminController::class, 'checkinMonitor'])->name('activities.checkin');
    Route::post('registrations/{id}/approve',    [ActivityAdminController::class, 'approveRegistration']);
    Route::get('reports',                        [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export',                 [ReportController::class, 'export'])->name('reports.export');
    Route::get('users',                          [ActivityAdminController::class, 'users'])->name('users.index');
    Route::get('categories',                     [ActivityAdminController::class, 'categories'])->name('categories.index');
});
```

### 5.4 Key Models

```php
// Activity.php
class Activity extends Model
{
    public function registrations() { return $this->hasMany(Registration::class); }
    public function attendances()   { return $this->hasMany(Attendance::class); }
    public function category()      { return $this->belongsTo(ActivityCategory::class); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }

    public function getComputedStatusAttribute(): string
    {
        return app(ActivityStatusService::class)->computeStatus($this);
    }

    public function getRemainingSlots(): int
    {
        $registered = $this->registrations()->whereIn('status', ['pending','approved'])->count();
        return max(0, $this->max_participants - $registered);
    }
}

// User.php
class User extends Authenticatable
{
    public function registrations() { return $this->hasMany(Registration::class); }
    public function attendances()   { return $this->hasMany(Attendance::class); }
    public function totalHours(): float
    {
        return $this->attendances()->with('activity')->get()
                    ->sum('activity.activity_hours');
    }
}
```

---

## 6. UI Pages Specification

| Route | หน้า | ฟีเจอร์หลัก | Role |
|---|---|---|---|
| `/login` | เข้าสู่ระบบ | กรอก student_id, validation, redirect by role | Public |
| `/register` | สมัครสมาชิก | กรอกข้อมูลส่วนตัว, ตรวจสอบรหัสซ้ำ | Public |
| `/activities` | รายการกิจกรรม | การ์ด, status badge, filter, search, sort | Student |
| `/activities/{id}` | รายละเอียดกิจกรรม | ข้อมูลครบ, ปุ่มลงทะเบียน, จำนวนที่เหลือ | Student |
| `/my-activities` | กิจกรรมของฉัน | รายการที่ลงทะเบียน, สถานะ, ปุ่มยกเลิก | Student |
| `/check-in/{token}` | เช็คอินกิจกรรม | ยืนยันตัวตน, แสดง success/error | Student |
| `/history` | ประวัติกิจกรรม | รายการเข้าร่วมทั้งหมด, filter ตามปี/เดือน | Student |
| `/summary` | สรุปชั่วโมง | Progress bar, แยก category, export PDF | Student |
| `/profile` | โปรไฟล์ | ข้อมูลส่วนตัว, แก้ไขข้อมูล | Student |
| `/notifications` | การแจ้งเตือน | รายการแจ้งเตือน, mark as read | Student |
| `/admin/dashboard` | Admin Dashboard | สถิติรวม, กิจกรรมล่าสุด, quick actions | Staff |
| `/admin/activities` | จัดการกิจกรรม | ตารางกิจกรรม, filter, bulk actions | Staff |
| `/admin/activities/create` | สร้างกิจกรรม | Form ครบทุก field, preview QR | Staff |
| `/admin/activities/{id}/edit` | แก้ไขกิจกรรม | แก้ไขข้อมูล, เปลี่ยนสถานะ | Staff |
| `/admin/activities/{id}/checkin` | Monitor Check-in | QR display, รายชื่อ real-time, manual check-in | Staff |
| `/admin/reports` | รายงาน | สรุปรายกิจกรรม/รายคน, export Excel | Staff |

### Mobile UX Requirements

- Bottom navigation bar (5 icons): Home, กิจกรรม, เช็คอิน, ประวัติ, โปรไฟล์
- Breakpoints: `sm:360px` `md:768px` `lg:1024px`
- Touch targets ขั้นต่ำ 44×44px
- Form inputs แสดง numeric keyboard สำหรับ student_id
- Pull-to-refresh บนหน้า activities
- PWA: installable, offline fallback page

---

## 7. Setup & Installation

### 7.1 Prerequisites

- PHP 8.2+
- Composer 2+
- Node.js 20+ + npm
- MySQL 8+
- Git

### 7.2 Installation

```bash
# 1. สร้าง Laravel Project
composer create-project laravel/laravel uni-activity
cd uni-activity

# 2. Install PHP Packages
composer require laravel/breeze
php artisan breeze:install blade

composer require spatie/laravel-permission
composer require simplesoftwareio/simple-qrcode
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf

# 3. Frontend
npm install
npm install @alpinejs/persist
npm run build

# 4. Environment
cp .env.example .env
php artisan key:generate
# แก้ไข .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 5. Database
php artisan migrate --seed

# 6. Storage Link (สำหรับรูปภาพ)
php artisan storage:link

# 7. Run Development Server
php artisan serve
```

### 7.3 Spatie Permission Setup

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

```php
// DatabaseSeeder.php
use Spatie\Permission\Models\Role;

Role::create(['name' => 'student']);
Role::create(['name' => 'staff']);
Role::create(['name' => 'verifier']);

// สร้าง default staff account
$staff = User::create([
    'student_id' => 'ADMIN001',
    'full_name'  => 'ผู้ดูแลระบบ',
    'role'       => 'staff',
]);
$staff->assignRole('staff');
```

### 7.4 Key .env Variables

```env
APP_NAME="University Activity System"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uni_activity
DB_USERNAME=root
DB_PASSWORD=

# QR Code
QR_CODE_SIZE=300
CHECKIN_GRACE_MINUTES=15

# Session
SESSION_LIFETIME=480
SESSION_DRIVER=database
```

---

## 8. Testing Strategy

### 8.1 Unit Tests

```
tests/Unit/
├── ActivityStatusServiceTest.php   ← ทดสอบ status calculation ทุก case
├── CheckInServiceTest.php          ← time-lock, duplicate, no registration
├── QrCodeServiceTest.php           ← token generation + validation
└── UserModelTest.php               ← totalHours calculation
```

```php
// ActivityStatusServiceTest.php
public function test_status_is_open_when_within_registration_period()
{
    $activity = Activity::factory()->create([
        'register_open_at'  => now()->subHour(),
        'register_close_at' => now()->addHour(),
        'max_participants'  => 50,
    ]);
    $this->assertEquals('open', $this->service->computeStatus($activity));
}

public function test_status_is_full_when_max_reached()
{
    $activity = Activity::factory()->create(['max_participants' => 2]);
    Registration::factory()->count(2)->create(['activity_id' => $activity->id, 'status' => 'approved']);
    $this->assertEquals('full', $this->service->computeStatus($activity));
}
```

### 8.2 Feature Tests

```
tests/Feature/
├── Auth/
│   ├── LoginTest.php               ← login สำเร็จ / fail / inactive user
│   └── RegisterTest.php            ← สมัครใหม่ / duplicate student_id
├── ActivityTest.php                ← index, show, filter, search
├── RegistrationTest.php            ← store / destroy / full activity
├── CheckInTest.php                 ← success / ก่อนเวลา / หลังเวลา / ซ้ำ / ไม่ได้ลงทะเบียน
└── Admin/
    ├── ActivityAdminTest.php       ← CRUD + role middleware
    └── ReportTest.php              ← export Excel/PDF
```

### 8.3 Manual Testing Checklist

**Mobile (Chrome DevTools + Real Device)**
- [ ] ทุกหน้าแสดงถูกต้องที่ 360px, 375px, 414px, 768px
- [ ] Bottom navigation ใช้งานได้สะดวก (thumb zone)
- [ ] Form input แสดง numeric keyboard สำหรับ student_id
- [ ] QR Scan ทำงานได้บน iOS Safari + Android Chrome
- [ ] PWA install prompt แสดงบน mobile
- [ ] Pull-to-refresh ทำงานได้

**Functional**
- [ ] ลงทะเบียนพร้อมกัน 2 คน เมื่อเหลือที่ว่าง 1 → คนใดคนหนึ่งต้องได้รับ error
- [ ] เช็คอินก่อนเวลา `checkin_open_at` → error message
- [ ] เช็คอินหลังเวลา `checkin_close_at` → error message
- [ ] เช็คอินซ้ำ → error message
- [ ] Status กิจกรรม auto-update เมื่อถึงเวลา

### 8.4 Performance

```php
// เพิ่ม DB indexes ที่สำคัญ
Schema::table('activities', function (Blueprint $table) {
    $table->index(['status', 'activity_date']);
    $table->index('register_open_at');
    $table->index('checkin_open_at');
});

Schema::table('registrations', function (Blueprint $table) {
    $table->index(['activity_id', 'status']);
});

// ใช้ Eager Loading เสมอ
Activity::with(['category', 'registrations' => fn($q) => $q->whereIn('status', ['pending','approved'])])->paginate(12);
```

---

## 9. Timeline Summary

| สัปดาห์ | งาน | Phase | ผลลัพธ์ |
|---|---|---|---|
| สัปดาห์ 1 | Project setup, Laravel install, Auth system, ออกแบบ DB | 1–2 | โครงสร้างพร้อม, login ด้วย student_id ได้ |
| สัปดาห์ 2 | Migrations, Models, Relations, Seeders | 2 | Database พร้อม, Seeders ใส่ข้อมูลตัวอย่างได้ |
| สัปดาห์ 3 | Activity CRUD, Registration system, Status logic | 3 | Student ลงทะเบียนได้, Admin จัดการได้ |
| สัปดาห์ 4 | QR Check-in, Time-lock, Verification | 3 | เช็คอินด้วย QR ทำงานครบ |
| สัปดาห์ 5 | History, Summary, Export PDF/Excel | 3–4 | ดูประวัติและสรุปชั่วโมงได้ |
| สัปดาห์ 6 | UI Responsive ทุกหน้า, Tailwind mobile-first | 4 | ทุกหน้าแสดงถูกต้องบน mobile |
| สัปดาห์ 7 | Notifications, Admin reports, PWA manifest | 4 | ระบบแจ้งเตือน + PWA install ได้ |
| สัปดาห์ 8 | Unit/Feature tests, Bug fixes, Performance tuning | 5 | Test coverage >70%, ไม่มี critical bugs |
| สัปดาห์ 9–10 | Staging deploy, UAT, Production deploy | 5 | ระบบ Live พร้อมใช้งาน |

---

## 10. Risks & Mitigation

| Risk | ระดับ | Mitigation |
|---|---|---|
| นักศึกษาลงทะเบียนพร้อมกันเกินจำนวน (race condition) | 🔴 สูง | ใช้ DB transaction + `SELECT FOR UPDATE` เมื่อ check `max_participants` |
| QR ถูก share/screenshot → เช็คอินแทนกัน | 🟡 กลาง | บันทึก IP + timestamp, ตรวจ duplicate ใน `attendances` |
| เวลาเครื่องนักศึกษาไม่ตรงกับ server | 🟡 กลาง | ใช้ server time เท่านั้นในการ validate ไม่ใช้ client time |
| ไม่มีสัญญาณ internet ขณะ check-in | 🟡 กลาง | ทำ offline fallback ใน PWA + sync เมื่อมีสัญญาณ (v1.1) |
| ฐานข้อมูลใหญ่ขึ้น query ช้า | 🟢 ต่ำ | เพิ่ม index บน `student_id`, `activity_id`, `status`, dates |
| Session หมดอายุขณะใช้งาน | 🟢 ต่ำ | ตั้ง `SESSION_LIFETIME=480` (8 ชั่วโมง), แสดง modal แจ้งเตือน |

---

## Appendix: Packages Summary

| Package | Version | หน้าที่ |
|---|---|---|
| `laravel/breeze` | ^2.0 | Authentication scaffolding |
| `spatie/laravel-permission` | ^6.0 | Role & permission management |
| `simplesoftwareio/simple-qrcode` | ^4.0 | QR Code generation |
| `maatwebsite/excel` | ^3.1 | Export Excel reports |
| `barryvdh/laravel-dompdf` | ^2.0 | Export PDF summary |

---

*Implementation Plan — University Activity Registration System · Version 1.0*
