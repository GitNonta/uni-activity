<?php

use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\Admin\ActivityAdminController;
use App\Http\Controllers\Admin\StudentAdminController;
use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\FeedbackAdminController;
use App\Http\Controllers\Admin\ExcelExportController;
use App\Http\Controllers\Admin\AnnouncementAdminController;
use App\Http\Controllers\Admin\ProfileAdminController;
use App\Http\Controllers\Student\StudentAnnouncementController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\Admin\JobAdminController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Admin\AdminInboxController;
use App\Http\Controllers\UserStatusController;
use Illuminate\Support\Facades\Route;

// ── เส้นทางนักศึกษา: เข้าสู่ระบบ / ลงทะเบียนบัญชี / ออกจากระบบ ──
Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->isAdmin() || auth()->user()->isStaff()) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('activities.index');
    }
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [StudentAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [StudentAuthController::class, 'login'])->middleware('throttle:student-login');
    Route::get('/register', [StudentAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [StudentAuthController::class, 'register']);
});

Route::post('/logout', [StudentAuthController::class, 'logout'])->name('logout');

// ── เส้นทางเจ้าหน้าที่: เข้าสู่ระบบด้วย email + password ──
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [StaffAuthController::class, 'showLogin'])->name('admin.login');   // แสดงฟอร์ม login
    Route::post('/admin/login', [StaffAuthController::class, 'login'])->middleware('throttle:staff-login'); // ดำเนินการ login
});
Route::post('/admin/logout', [StaffAuthController::class, 'logout'])->name('admin.logout');   // ออกจากระบบ

// ── ระบบลืมรหัสผ่าน Staff ──
Route::middleware('guest')->group(function () {
    Route::get('/admin/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('admin.password.request');
    Route::post('/admin/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:password-reset')->name('admin.password.email');
    Route::get('/admin/reset-password/{token}', [NewPasswordController::class, 'create'])->name('admin.password.reset');
    Route::post('/admin/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('admin.password.update');
});

// ── เส้นทาง Walk-in Check-in (ไม่ต้อง login กรอกรหัสนักศึกษาเอง) ──
Route::get('/walkin/{token}', [CheckInController::class, 'walkInPage'])->name('checkin.walkin');             // หน้า walk-in check-in
Route::post('/walkin/{token}', [CheckInController::class, 'walkInStore'])->middleware('throttle:walkin')->name('checkin.walkin.store'); // บันทึก walk-in check-in
Route::get('/walkin/{token}/attendees', [CheckInController::class, 'walkInAttendees'])->middleware('throttle:status')->name('checkin.walkin.attendees'); // API รายชื่อ real-time

// ── เส้นทางนักศึกษา (ต้อง login ก่อน) ──────────────────
Route::middleware('auth')->group(function () {
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');                       // รายการกิจกรรมทั้งหมด
    Route::get('/activities/{id}', [ActivityController::class, 'show'])->name('activities.show');                    // รายละเอียดกิจกรรม
    Route::post('/activities/{id}/register', [RegistrationController::class, 'store'])->name('activities.register'); // ลงทะเบียนกิจกรรม
    Route::delete('/registrations/{id}', [RegistrationController::class, 'destroy'])->name('registrations.destroy'); // ยกเลิกการลงทะเบียน
    Route::get('/check-in/{token}', [CheckInController::class, 'show'])->name('checkin.show');                       // หน้าเช็คอินจาก QR
    Route::post('/check-in/{token}', [CheckInController::class, 'store'])->name('checkin.store');                    // ดำเนินการเช็คอิน QR
    Route::post('/activities/{id}/self-checkin', [CheckInController::class, 'selfCheckIn'])->name('activities.self-checkin'); // บันทึกกิจกรรมด้วยตัวเอง
    Route::get('/my-activities', [StudentController::class, 'myActivities'])->name('student.my');                    // กิจกรรมของฉัน
    Route::get('/history', [StudentController::class, 'history'])->name('student.history');                          // ประวัติการเข้าร่วม
    Route::get('/summary', [StudentController::class, 'summary'])->name('student.summary');                          // สรุปชั่วโมง
    Route::get('/summary/pdf', [StudentController::class, 'downloadPdf'])->name('student.summary.pdf');              // ดาวน์โหลด PDF ใบแสดงผลกิจกรรม
    Route::get('/profile', [StudentController::class, 'profile'])->name('student.profile');                          // หน้าโปรไฟล์นักศึกษา
    // ── ปฏิทินกิจกรรม ──
    Route::get('/calendar', [StudentController::class, 'calendar'])->name('student.calendar');                       // หน้าปฏิทิน
    Route::get('/calendar/events', [StudentController::class, 'calendarEvents'])->name('student.calendar.events');   // JSON feed
    // ── แจ้งเตือน ──
    Route::get('/student/notifications', [StudentController::class, 'notifications'])->middleware('throttle:status')->name('student.notifications'); // JSON alerts
    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])->middleware('throttle:upload')->name('profile.photo.upload');

    // ── ประเมินกิจกรรม ──
    Route::get('activities/{id}/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('activities/{id}/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    
    // ── ประกาศข่าวสารสำหรับนักศึกษา ──
    Route::get('/announcements', [StudentAnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/{id}', [StudentAnnouncementController::class, 'show'])->name('announcements.show');
    
    Route::delete('/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');        // ลบรูปโปรไฟล์

    // ── ประกาศรับสมัครงาน (นักศึกษา) ──
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');                                             // รายการงานทั้งหมด
    Route::get('/jobs/{id}', [JobController::class, 'show'])->name('jobs.show');                                          // รายละเอียดงาน
    Route::post('/jobs/{id}/apply', [JobController::class, 'apply'])->name('jobs.apply');                                 // สมัครงาน
    Route::post('/jobs/{id}/comment', [JobController::class, 'comment'])->name('jobs.comment');                           // เพิ่มคอมเมนต์
    Route::delete('/jobs/comments/{id}', [JobController::class, 'deleteComment'])->name('jobs.comment.delete');           // ลบคอมเมนต์
    // ── ระบบแชทสด (MongoDB) ──
    Route::get('/chat/threads', [ChatController::class, 'myThreads'])->name('chat.threads');
    Route::get('/jobs/{id}/chat/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::get('/jobs/{id}/chat', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/jobs/{id}/chat', [ChatController::class, 'send'])->middleware('throttle:chat-send')->name('chat.send');
    Route::post('/jobs/{id}/chat/read', [ChatController::class, 'markRead'])->name('chat.read');
    Route::get('/jobs/{id}/admin-online', [ChatController::class, 'adminOnlineStatus'])->middleware('throttle:status')->name('chat.admin-online');

    // ── User status (online/last seen) ──
    Route::middleware('auth')->post('/user/ping', [UserStatusController::class, 'ping'])->middleware('throttle:status')->name('user.ping');
    Route::get('/users/{id}/status', [UserStatusController::class, 'status'])->middleware('throttle:status')->name('user.status');
});

// ── เส้นทางหลังบ้าน (staff + admin เข้าได้) ───────────
Route::middleware(['auth', 'role:staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn() => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [ActivityAdminController::class, 'dashboard'])->name('dashboard');

    // ── กิจกรรม ──
    Route::resource('activities', ActivityAdminController::class);
    Route::get('activities/{id}/participants', [ActivityAdminController::class, 'participants'])->name('activities.participants');
    Route::get('activities/{id}/checkin', [ActivityAdminController::class, 'checkinMonitor'])->name('activities.checkin');
    Route::get('activities/{id}/pending-requests', [ActivityAdminController::class, 'pendingRequests'])->name('activities.pending-requests');
    Route::post('registrations/{id}/approve', [ActivityAdminController::class, 'approveRegistration'])->name('registrations.approve');
    Route::post('registrations/{id}/reject', [ActivityAdminController::class, 'rejectRegistration'])->name('registrations.reject');
    Route::post('activities/{id}/manual-checkin', [ActivityAdminController::class, 'manualCheckIn'])->name('activities.manual-checkin');
    Route::post('attendances/{id}/approve', [ActivityAdminController::class, 'approveAttendance'])->name('attendances.approve');
    Route::post('attendances/{id}/reject', [ActivityAdminController::class, 'rejectAttendance'])->name('attendances.reject');
    Route::post('activities/quick-store', [ActivityAdminController::class, 'quickStore'])->name('activities.quick-store');
    Route::post('activities/{id}/toggle-early-checkin', [ActivityAdminController::class, 'toggleEarlyCheckin'])->name('activities.toggle-early-checkin');
    // ── AJAX: approve/reject จาก Dashboard unified queue ──
    Route::post('quick-approve', [ActivityAdminController::class, 'quickApprove'])->name('quick.approve');
    Route::post('quick-reject', [ActivityAdminController::class, 'quickReject'])->name('quick.reject');
    
    // ── QR Code ──
    Route::post('activities/{id}/regenerate-qr', [ActivityAdminController::class, 'regenerateQr'])->name('activities.regenerate-qr');

    // ── ประกาศ ──
    Route::resource('announcements', AnnouncementAdminController::class);
    Route::patch('announcements/{id}/toggle-active', [AnnouncementAdminController::class, 'toggleActive'])->name('announcements.toggle-active');

    // ── ประกาศรับสมัครงาน ──
    Route::resource('jobs', JobAdminController::class);
    Route::patch('jobs/{id}/status', [JobAdminController::class, 'updateStatus'])->name('jobs.update-status');
    Route::patch('jobs/{id}/applicants/{aid}', [JobAdminController::class, 'updateApplicant'])->name('jobs.update-applicant');
    Route::delete('jobs/comments/{cid}', [JobAdminController::class, 'deleteComment'])->name('jobs.admin-comment-delete');
    Route::get('jobs/{id}/export-applicants', [JobAdminController::class, 'exportApplicants'])->name('jobs.export-applicants');

    // ── กล่องข้อความ (Inbox) ──
    Route::get('inbox', [AdminInboxController::class, 'index'])->name('inbox.index');
    Route::get('inbox/{jobId}/{userId}', [AdminInboxController::class, 'show'])->name('inbox.show');
    Route::post('inbox/{jobId}/{userId}', [AdminInboxController::class, 'send'])->middleware('throttle:chat-send')->name('inbox.send');
    Route::post('inbox/{jobId}/{userId}/read', [AdminInboxController::class, 'markRead'])->name('inbox.read');

    // ── นักศึกษา (ดูได้ทั้ง staff + admin) ──
    Route::get('students', [StudentAdminController::class, 'index'])->name('students.index');
    Route::get('students/{id}', [StudentAdminController::class, 'show'])->name('students.show');

    // ── ส่งออกรายงาน Excel ──
    Route::get('exports', [ExcelExportController::class, 'index'])->name('exports.index');
    Route::post('exports/students', [ExcelExportController::class, 'exportStudents'])->middleware('throttle:exports')->name('exports.students');
    Route::post('exports/activities', [ExcelExportController::class, 'exportActivities'])->middleware('throttle:exports')->name('exports.activities');
    Route::post('exports/statistics', [ExcelExportController::class, 'exportStatistics'])->middleware('throttle:exports')->name('exports.statistics');
    Route::post('exports/student-attendances', [ExcelExportController::class, 'exportStudentAttendances'])->middleware('throttle:exports')->name('exports.student-attendances');
    Route::post('exports/activity-details', [ExcelExportController::class, 'exportActivityDetails'])->middleware('throttle:exports')->name('exports.activity-details');

    // ── ผลการประเมิน ──
    Route::get('feedbacks', [FeedbackAdminController::class, 'index'])->name('feedbacks.index');
    Route::get('feedbacks/activity/{id}', [FeedbackAdminController::class, 'show'])->name('feedbacks.show');

    // ── โปรไฟล์ส่วนตัว ──
    Route::get('profile', [ProfileAdminController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileAdminController::class, 'update'])->name('profile.update');
});

// ── เส้นทางเฉพาะ admin เท่านั้น ───────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // ── จัดการหมวดหมู่กิจกรรม + เกณฑ์ชั่วโมง ──
    Route::get('categories', [CategoryAdminController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryAdminController::class, 'store'])->name('categories.store');
    Route::patch('categories/{id}', [CategoryAdminController::class, 'update'])->name('categories.update');
    Route::delete('categories/{id}', [CategoryAdminController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/required-hours', [CategoryAdminController::class, 'saveRequiredHours'])->name('categories.required-hours');
    Route::delete('categories/required-hours/reset', [CategoryAdminController::class, 'resetRequiredHours'])->name('categories.required-hours.reset');

    // ── จัดการผู้ใช้งาน ──
    Route::get('users', [UserAdminController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserAdminController::class, 'create'])->name('users.create');
    Route::post('users', [UserAdminController::class, 'store'])->name('users.store');
    Route::get('users/{id}/edit', [UserAdminController::class, 'edit'])->name('users.edit');
    Route::patch('users/{id}', [UserAdminController::class, 'update'])->name('users.update');
    Route::delete('users/{id}', [UserAdminController::class, 'destroy'])->name('users.destroy');
    Route::patch('users/{id}/toggle-active', [UserAdminController::class, 'toggleActive'])->name('users.toggle-active');

    // ── จัดการบันทึกนักศึกษา ──
    Route::post('students/{id}/attendances', [StudentAdminController::class, 'addAttendance'])->name('students.attendances.add');
    Route::patch('students/{id}/attendances/{aid}', [StudentAdminController::class, 'updateAttendance'])->name('students.attendances.update');
    Route::delete('students/{id}/attendances/{aid}', [StudentAdminController::class, 'deleteAttendance'])->name('students.attendances.delete');

    // ── Audit Log ──
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{id}', [AuditLogController::class, 'show'])->name('audit-logs.show');
});
