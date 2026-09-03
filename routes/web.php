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
use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\AdminQuickApprovalController;
use App\Http\Controllers\Admin\AdminRegistrationController;
use App\Http\Controllers\Admin\DashboardController;
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
use App\Http\Controllers\LineController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

// ── Diagnostic endpoint สำหรับ Local / Testing เท่านั้น (ห้ามเปิด Public บน Production) ──
if (app()->environment('local', 'testing')) {
    Route::get('/debug-ip', function () {
        return response()->json([
            'ip'                     => request()->ip(),
            'all_ips'                => request()->ips(),
            'header_x_forwarded_for' => request()->header('X-Forwarded-For'),
            'header_x_real_ip'       => request()->header('X-Real-IP'),
            'server_remote_addr'     => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
        ]);
    })->name('debug.ip');
}
// ── Health Check Endpoints สำหรับ Load Balancer, Nginx & Monitoring (ไม่มี CORS) ──
Route::get('/health', \App\Http\Controllers\Api\HealthCheckController::class)->name('health');

Route::get('/up', function () {
    return response()->json([
        'status'    => 'ok',
        'timestamp' => time(),
        'service'   => 'uni-activity',
    ]);
})->name('up');

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
    // Password reset routes for all users
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.update');
    
    // ── Login OTP ──
    Route::get('/login/verify-otp', [\App\Http\Controllers\Auth\LoginOtpController::class, 'showVerifyForm'])->name('login.otp.show');
    Route::post('/login/verify-otp', [\App\Http\Controllers\Auth\LoginOtpController::class, 'verify'])->name('login.otp.verify');
    Route::post('/login/resend-otp', [\App\Http\Controllers\Auth\LoginOtpController::class, 'resend'])->name('login.otp.resend');

    Route::get('/register', [StudentAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [StudentAuthController::class, 'register']);
});

Route::post('/logout', [StudentAuthController::class, 'logout'])->name('logout');

// ── เส้นทางเจ้าหน้าที่: เข้าสู่ระบบด้วย email + password ──
Route::middleware(['guest', 'protect-admin', 'strip-hpp'])->group(function () {
    Route::get('/admin/login', [StaffAuthController::class, 'showLogin'])->name('admin.login');   // แสดงฟอร์ม login
    Route::post('/admin/login', [StaffAuthController::class, 'login'])->middleware('throttle:staff-login'); // ดำเนินการ login
});
Route::post('/admin/logout', [StaffAuthController::class, 'logout'])->name('admin.logout');   // ออกจากระบบ

// ── ระบบลืมรหัสผ่าน Staff ──
Route::middleware(['guest', 'protect-admin'])->group(function () {
    Route::get('/admin/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('admin.password.request');
    Route::post('/admin/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:password-reset')->name('admin.password.email');
    
    // ── OTP Verification ──
    Route::get('/admin/verify-otp', [\App\Http\Controllers\Auth\OtpVerificationController::class, 'showVerifyForm'])->name('admin.password.otp.show');
    Route::post('/admin/verify-otp', [\App\Http\Controllers\Auth\OtpVerificationController::class, 'verify'])->name('admin.password.otp.verify');

    Route::get('/admin/reset-password/{token}', [NewPasswordController::class, 'create'])->name('admin.password.reset');
    Route::post('/admin/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('admin.password.update');
});

// ── เส้นทาง Walk-in Check-in (สำหรับ staff/admin หน้างานเท่านั้น) ──
Route::middleware(['auth', 'role:staff'])->group(function () {
    Route::get('/walkin/{token}', [CheckInController::class, 'walkInPage'])->name('checkin.walkin');             // หน้า walk-in check-in
    Route::post('/walkin/{token}', [CheckInController::class, 'walkInStore'])->middleware('throttle:walkin')->name('checkin.walkin.store'); // บันทึก walk-in check-in
    Route::get('/walkin/{token}/attendees', [CheckInController::class, 'walkInAttendees'])->middleware('throttle:status')->name('checkin.walkin.attendees'); // API รายชื่อ real-time
});

// ── เส้นทางนักศึกษาที่เข้าดูได้โดยไม่ต้องเข้าสู่ระบบ ──
Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
Route::get('/activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
Route::get('/announcements', [StudentAnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{announcement}', [StudentAnnouncementController::class, 'show'])->name('announcements.show');
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
Route::get('/map', [MapController::class, 'index'])->name('map.index');
// ✅ FIXED V2: Public map pins for logged-out visitors (activities/jobs/landmarks are public data,
// identical to what /activities & /jobs pages already expose). Rate-limited to prevent scraping.
// Live user coordinates stay protected in the auth-only update-location route below.
Route::middleware('throttle:api-general')->get('/api/map/locations', [MapController::class, 'locationsApi'])->name('api.map.locations');

// ── เส้นทางนักศึกษา (ต้อง login ก่อน) ──────────────────
Route::middleware('auth')->group(function () {
    Route::post('/api/map/update-location', [MapController::class, 'updateLocation'])->name('api.map.update_location');
    Route::post('/activities/{activity}/register', [RegistrationController::class, 'store'])->name('activities.register'); // ลงทะเบียนกิจกรรม
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy'])->name('registrations.destroy'); // ยกเลิกการลงทะเบียน
    Route::get('/check-in/{token}', [CheckInController::class, 'show'])->name('checkin.show');                       // หน้าเช็คอินจาก QR
    Route::post('/check-in/{token}', [CheckInController::class, 'store'])->name('checkin.store');                    // ดำเนินการเช็คอิน QR
    Route::post('/check-in/{token}/verify-frame', [CheckInController::class, 'verifyFrame'])->name('checkin.verify_frame'); // สแกนหน้าแบบเรียวไทม์
    
    // Optimized face verification API endpoints
    Route::prefix('api/face')->middleware('face-verify')->group(function () {
        Route::post('/verify', [App\Http\Controllers\Api\FaceVerificationController::class, 'verify'])->name('api.face.verify');
        Route::get('/metrics', [App\Http\Controllers\Api\FaceVerificationController::class, 'metrics'])->name('api.face.metrics');
    });

    Route::post('/activities/{id}/self-checkin', [CheckInController::class, 'selfCheckIn'])->name('activities.self-checkin'); // ปิด self check-in: ให้สแกน QR หน้างานเท่านั้น
    Route::get('/my-activities', [StudentController::class, 'myActivities'])->name('student.my');                    // กิจกรรมของฉัน
    Route::get('/history', [StudentController::class, 'history'])->name('student.history');                          // ประวัติการเข้าร่วม
    Route::get('/summary', [StudentController::class, 'summary'])->name('student.summary');                          // สรุปชั่วโมง
    Route::get('/summary/pdf', [StudentController::class, 'downloadPdf'])->name('student.summary.pdf');              // ดาวน์โหลด PDF ใบแสดงผลกิจกรรม
    Route::get('/profile', [StudentController::class, 'profile'])->name('student.profile');                          // หน้าโปรไฟล์นักศึกษา
    Route::post('/profile/english-name', [StudentController::class, 'updateEnglishName'])->name('student.profile.english_name'); // แก้ไขชื่อภาษาอังกฤษ

    Route::get('/scan', [StudentController::class, 'scanner'])->name('student.scanner');                             // หน้าสแกน QR สำหรับนักศึกษา
    // ── ปฏิทินกิจกรรม ──
    Route::get('/calendar', [StudentController::class, 'calendar'])->name('student.calendar');                       // หน้าปฏิทิน
    Route::get('/calendar/events', [StudentController::class, 'calendarEvents'])->name('student.calendar.events');   // JSON feed
    // ── แจ้งเตือน ──
    Route::get('/student/notifications', [StudentController::class, 'notifications'])->middleware('throttle:status')->name('student.notifications'); // JSON alerts
    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])->middleware('throttle:upload')->name('profile.photo.upload');
    Route::post('/profile/save-js-descriptor', [ProfilePhotoController::class, 'saveJsDescriptor'])->name('profile.save_js_descriptor');

    // ── ประเมินกิจกรรม ──
    Route::get('activities/{activity}/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('activities/{activity}/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    
    Route::delete('/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');        // ลบรูปโปรไฟล์

    // ── ประกาศรับสมัครงาน (นักศึกษา) ──
    Route::post('/jobs/{job}/apply', [JobController::class, 'apply'])->name('jobs.apply');                                 // สมัครงาน
    Route::post('/jobs/{job}/comment', [JobController::class, 'comment'])->name('jobs.comment');                           // เพิ่มคอมเมนต์
    Route::delete('/jobs/comments/{comment}', [JobController::class, 'deleteComment'])->name('jobs.comment.delete');           // ลบคอมเมนต์
    // ── ระบบแชทสด (MongoDB) ──
    Route::get('/chat/threads', [ChatController::class, 'myThreads'])->name('chat.threads');
    Route::get('/jobs/{id}/chat/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::get('/jobs/{id}/chat', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/jobs/{id}/chat', [ChatController::class, 'send'])->middleware('throttle:chat-send')->name('chat.send');
    Route::post('/jobs/{id}/chat/read', [ChatController::class, 'markRead'])->name('chat.read');
    Route::get('/jobs/{id}/chat/read-status', [ChatController::class, 'readStatus'])->name('chat.read-status');
    Route::get('/jobs/{id}/admin-online', [ChatController::class, 'adminOnlineStatus'])->middleware('throttle:status')->name('chat.admin-online');
    Route::delete('/chat/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.messages.delete');
    Route::get('/chat/messages/{message}', [ChatController::class, 'showMessage'])->name('chat.messages.show');
    Route::put('/chat/messages/{message}', [ChatController::class, 'editMessage'])->name('chat.messages.edit');
    // ── User status (online/last seen) ──
    Route::middleware('auth')->post('/user/ping', [UserStatusController::class, 'ping'])->middleware('throttle:status')->name('user.ping');
    Route::get('/users/{user}/status', [UserStatusController::class, 'status'])->middleware('throttle:status')->name('user.status');

    // ── ใบรับรองชั่วโมงกิจกรรม (Certificates) ──
    Route::get('/student/certificates', [\App\Http\Controllers\Student\CertificateController::class, 'index'])->name('student.certificates.index');
    Route::post('/student/certificates/claim', [\App\Http\Controllers\Student\CertificateController::class, 'claim'])->name('student.certificates.claim');
    Route::get('/student/certificates/{certificate}/download', [\App\Http\Controllers\Student\CertificateController::class, 'download'])->name('student.certificates.download');

    // ── LINE OAuth ──
    Route::get('/line/redirect', [LineController::class, 'redirect'])->name('line.redirect');
    Route::get('/line/callback', [LineController::class, 'callback'])->name('line.callback');
    Route::post('/line/unlink', [LineController::class, 'unlink'])->name('line.unlink');
    Route::post('/line/toggle-notify', [LineController::class, 'toggleNotify'])->name('line.toggle-notify');
});

// ── ตรวจสอบใบรับรองกิจกรรมออนไลน์ (Public Verification, ไม่ต้อง Auth) ──
Route::get('/certificates/verify/{code}', [\App\Http\Controllers\Public\CertificateVerificationController::class, 'verify'])->name('certificates.verify');

// ── LINE Webhook (ไม่ต้อง auth) ──
Route::match(['get', 'post'], '/line/webhook', [LineController::class, 'webhook'])->name('line.webhook');

// ── เส้นทางหลังบ้าน (staff + admin เข้าได้) ───────────
Route::middleware(['auth', 'role:staff', 'strip-hpp'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn() => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── กิจกรรม ──
    Route::resource('activities', ActivityAdminController::class);
    Route::get('activities/{activity}/participants', [AdminAttendanceController::class, 'participants'])->name('activities.participants');
    Route::get('activities/{activity}/checkin', [AdminAttendanceController::class, 'monitor'])->name('activities.checkin');
    Route::get('activities/{activity}/pending-requests', [AdminRegistrationController::class, 'pendingRequests'])->name('activities.pending-requests');
    Route::post('registrations/{registration}/approve', [AdminRegistrationController::class, 'approveRegistration'])->name('registrations.approve');
    Route::post('registrations/{registration}/reject', [AdminRegistrationController::class, 'rejectRegistration'])->name('registrations.reject');
    Route::post('activities/{activity}/manual-checkin', [AdminAttendanceController::class, 'manualCheckIn'])->name('activities.manual-checkin');
    Route::post('attendances/{attendance}/approve', [AdminAttendanceController::class, 'approve'])->name('attendances.approve');
    Route::post('attendances/{attendance}/reject', [AdminAttendanceController::class, 'reject'])->name('attendances.reject');
    Route::post('activities/quick-store', [ActivityAdminController::class, 'quickStore'])->name('activities.quick-store');
    Route::post('activities/{activity}/toggle-early-checkin', [ActivityAdminController::class, 'toggleEarlyCheckin'])->name('activities.toggle-early-checkin');
    Route::post('attendances/{attendance}/review-selfie', [AdminAttendanceController::class, 'reviewSelfie'])->name('attendances.review-selfie');
    // ── AJAX: approve/reject จาก Dashboard unified queue ──
    Route::post('quick-approve', [AdminQuickApprovalController::class, 'approve'])->name('quick.approve');
    Route::post('quick-reject', [AdminQuickApprovalController::class, 'reject'])->name('quick.reject');
    
    // ── QR Code ──
    Route::post('activities/{activity}/regenerate-qr', [ActivityAdminController::class, 'regenerateQr'])->name('activities.regenerate-qr');
    Route::post('activities/{activity}/regenerate-checkout-qr', [ActivityAdminController::class, 'regenerateCheckoutQr'])->name('activities.regenerate-checkout-qr');

    // ── Clone / Duplicate Activity ──
    Route::post('activities/{activity}/clone', [ActivityAdminController::class, 'duplicate'])->name('activities.clone');

    // ── Bulk Student Import ──
    Route::get('students/import', [\App\Http\Controllers\Admin\StudentImportController::class, 'index'])->name('students.import');
    Route::post('students/import', [\App\Http\Controllers\Admin\StudentImportController::class, 'import'])->name('students.import.upload');
    Route::get('students/import/template', [\App\Http\Controllers\Admin\StudentImportController::class, 'downloadTemplate'])->name('students.import.template');


    // ── ประกาศ ──
    Route::resource('announcements', AnnouncementAdminController::class);
    Route::patch('announcements/{announcement}/toggle-active', [AnnouncementAdminController::class, 'toggleActive'])->name('announcements.toggle-active');

    // ── ประกาศรับสมัครงาน ──
    Route::resource('jobs', JobAdminController::class);
    Route::patch('jobs/{job}/status', [JobAdminController::class, 'updateStatus'])->name('jobs.update-status');
    Route::patch('jobs/{job}/applicants/{aid}', [JobAdminController::class, 'updateApplicant'])->name('jobs.update-applicant');
    Route::delete('jobs/comments/{cid}', [JobAdminController::class, 'deleteComment'])->name('jobs.admin-comment-delete');
    Route::get('jobs/{job}/export-applicants', [JobAdminController::class, 'exportApplicants'])->name('jobs.export-applicants');

    // ── กล่องข้อความ (Inbox) ──
    Route::get('inbox', [AdminInboxController::class, 'index'])->name('inbox.index');
    Route::get('inbox/unread-count', [AdminInboxController::class, 'unreadCount'])->name('inbox.unread-count');
    Route::get('inbox/{jobId}/{userId}', [AdminInboxController::class, 'show'])->name('inbox.show');
    // Archived thread (job deleted) — history-only page for staff
    Route::get('inbox/archived/{jobId}/{userId}', [AdminInboxController::class, 'archived'])->name('inbox.archived');
    Route::post('inbox/{jobId}/{userId}', [AdminInboxController::class, 'send'])->middleware('throttle:chat-send')->name('inbox.send');
    Route::post('inbox/{jobId}/{userId}/read', [AdminInboxController::class, 'markRead'])->name('inbox.read');
    Route::get('inbox/{jobId}/{userId}/read-status', [AdminInboxController::class, 'readStatus'])->name('inbox.read-status');
    Route::get('inbox/{jobId}/{userId}/messages', [AdminInboxController::class, 'messages'])->name('inbox.messages');
    Route::delete('inbox/messages/{message}', [AdminInboxController::class, 'deleteMessage'])->name('inbox.messages.delete');
    Route::get('inbox/messages/{message}', [AdminInboxController::class, 'showMessage'])->name('inbox.messages.show');
    Route::put('inbox/messages/{message}', [AdminInboxController::class, 'editMessage'])->name('inbox.messages.edit');
    Route::delete('inbox/{jobId}/{userId}', [AdminInboxController::class, 'deleteChat'])->name('inbox.delete');
    Route::get('students', [StudentAdminController::class, 'index'])->name('students.index');
    Route::get('students/{student}', [StudentAdminController::class, 'show'])->name('students.show');
    Route::post('students/{student}/send-message', [StudentAdminController::class, 'sendMessage'])->name('students.send-message');
    Route::post('students/{student}/attendances', [StudentAdminController::class, 'addAttendance'])->name('students.attendances.add');
    Route::patch('students/{student}/attendances/{aid}', [StudentAdminController::class, 'updateAttendance'])->name('students.attendances.update');
    Route::delete('students/{student}/attendances/{aid}', [StudentAdminController::class, 'deleteAttendance'])->name('students.attendances.delete');

    // ── ส่งออกรายงาน Excel ──
    Route::get('exports', [ExcelExportController::class, 'index'])->name('exports.index');
    Route::post('exports/students', [ExcelExportController::class, 'exportStudents'])->middleware('throttle:exports')->name('exports.students');
    Route::post('exports/activities', [ExcelExportController::class, 'exportActivities'])->middleware('throttle:exports')->name('exports.activities');
    Route::post('exports/statistics', [ExcelExportController::class, 'exportStatistics'])->middleware('throttle:exports')->name('exports.statistics');
    Route::post('exports/student-attendances', [ExcelExportController::class, 'exportStudentAttendances'])->middleware('throttle:exports')->name('exports.student-attendances');
    Route::post('exports/activity-details', [ExcelExportController::class, 'exportActivityDetails'])->middleware('throttle:exports')->name('exports.activity-details');

    // ── ผลการประเมิน ──
    Route::get('feedbacks', [FeedbackAdminController::class, 'index'])->name('feedbacks.index');
    Route::get('feedbacks/activity/{activity}', [FeedbackAdminController::class, 'show'])->name('feedbacks.show');

    // ── โปรไฟล์ส่วนตัว ──
    Route::get('profile', [ProfileAdminController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileAdminController::class, 'update'])->name('profile.update');

    // ── Global Omnisearch (Ctrl+K) ──
    Route::get('api/search', [\App\Http\Controllers\Admin\GlobalSearchController::class, 'search'])->name('global.search');
});

// ── เส้นทางเฉพาะ admin เท่านั้น ───────────
Route::middleware(['auth', 'role:admin,super-admin'])->prefix('admin')->name('admin.')->group(function () {
    // ── จัดการหมวดหมู่กิจกรรม + เกณฑ์ชั่วโมง ──
    Route::get('categories', [CategoryAdminController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryAdminController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryAdminController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryAdminController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/required-hours', [CategoryAdminController::class, 'saveRequiredHours'])->name('categories.required-hours');
    Route::delete('categories/required-hours/reset', [CategoryAdminController::class, 'resetRequiredHours'])->name('categories.required-hours.reset');

    // ── จัดการผู้ใช้งาน ──
    Route::get('users', [UserAdminController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserAdminController::class, 'create'])->name('users.create');
    Route::post('users', [UserAdminController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserAdminController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [UserAdminController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserAdminController::class, 'destroy'])->name('users.destroy');
    Route::patch('users/{user}/toggle-active', [UserAdminController::class, 'toggleActive'])->name('users.toggle-active');

    // ── Audit Log ──
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    // ── Security Logs (ตรวจจับ multi-account / suspicious check-in) ──
    Route::get('security-logs', [\App\Http\Controllers\Admin\SecurityLogController::class, 'index'])->name('security-logs.index');
    Route::get('security-logs/{securityLog}', [\App\Http\Controllers\Admin\SecurityLogController::class, 'show'])->name('security-logs.show');
    Route::post('security-logs/{securityLog}/review', [\App\Http\Controllers\Admin\SecurityLogController::class, 'markReviewed'])->name('security-logs.review');
    Route::post('security-logs/review-all', [\App\Http\Controllers\Admin\SecurityLogController::class, 'markAllReviewed'])->name('security-logs.review-all');

    // ── ตั้งค่าระบบ ──
    Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');
    // ── API Keys ──
    Route::get('api-keys', [\App\Http\Controllers\Admin\ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('api-keys', [\App\Http\Controllers\Admin\ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('api-keys/{apiKey}', [\App\Http\Controllers\Admin\ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    // ── สำรองและกู้คืนข้อมูลระบบ (Automated & Manual Backups) ──
    Route::get('backups', [\App\Http\Controllers\Admin\BackupAdminController::class, 'index'])->name('backups.index');
    Route::post('backups', [\App\Http\Controllers\Admin\BackupAdminController::class, 'store'])->name('backups.store');
    Route::get('backups/{filename}/download', [\App\Http\Controllers\Admin\BackupAdminController::class, 'download'])->name('backups.download');
    Route::delete('backups/{filename}', [\App\Http\Controllers\Admin\BackupAdminController::class, 'destroy'])->name('backups.destroy');
    Route::post('backups/clean', [\App\Http\Controllers\Admin\BackupAdminController::class, 'clean'])->name('backups.clean');

    // ── Distributed Cluster Control & Observability ──
    Route::get('system/cluster', [\App\Http\Controllers\Admin\ClusterMonitoringController::class, 'index'])->name('system.cluster');
    Route::get('api/cluster/metrics', [\App\Http\Controllers\Admin\ClusterMonitoringController::class, 'metrics'])->name('api.cluster.metrics');

    // ── Failed Queue Jobs Management ──
    Route::get('system/failed-jobs', [\App\Http\Controllers\Admin\FailedJobsController::class, 'index'])->name('system.failed-jobs.index');
    Route::get('system/failed-jobs/{uuid}', [\App\Http\Controllers\Admin\FailedJobsController::class, 'show'])->name('system.failed-jobs.show');
    Route::post('system/failed-jobs/retry-all', [\App\Http\Controllers\Admin\FailedJobsController::class, 'retryAll'])->name('system.failed-jobs.retry-all');
    Route::post('system/failed-jobs/{id}/retry', [\App\Http\Controllers\Admin\FailedJobsController::class, 'retry'])->name('system.failed-jobs.retry');
    Route::delete('system/failed-jobs/flush', [\App\Http\Controllers\Admin\FailedJobsController::class, 'flush'])->name('system.failed-jobs.flush');
    Route::delete('system/failed-jobs/{id}', [\App\Http\Controllers\Admin\FailedJobsController::class, 'destroy'])->name('system.failed-jobs.destroy');

    // ── Diagnostic / IP Debug (เฉพาะ Admin และ Super-Admin ภายใต้สิทธิ์จัดการ) ──
    Route::get('diagnostics/ip', function () {
        return response()->json([
            'ip'                     => request()->ip(),
            'all_ips'                => request()->ips(),
            'header_x_forwarded_for' => request()->header('X-Forwarded-For'),
            'header_x_real_ip'       => request()->header('X-Real-IP'),
            'server_remote_addr'     => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
        ]);
    })->name('diagnostics.ip');

    // API routes for optimized face verification
    Route::prefix('api')->middleware('auth:sanctum')->group(function () {
        Route::post('/face/verify', [App\Http\Controllers\Api\FaceVerificationController::class, 'verify'])->name('api.face.verify');
        Route::get('/face/metrics', [App\Http\Controllers\Api\FaceVerificationController::class, 'metrics'])->name('api.face.metrics');
    });

    // Existing API routes
    Route::prefix('api')->group(function () {
        Route::get('/activities', [App\Http\Controllers\Api\ActivityController::class, 'index']);
    });
});
Route::get('/robots.txt', [\App\Http\Controllers\SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap'])->name('sitemap');
