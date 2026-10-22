<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$trans = json_decode(file_get_contents(__DIR__ . '/lang/en.json'), true);
if (json_last_error() !== JSON_ERROR_NONE) { echo "JSON BROKEN\n"; exit(1); }
echo "key ลบบันทึกกิจกรรมนี้?: ", isset($trans['ลบบันทึกกิจกรรมนี้?']) ? 'OK' : 'MISSING', "\n";

$u = App\Models\User::where('role', 'staff')->first() ?: App\Models\User::first();
if (!$u) { echo "NO USER\n"; exit(1); }
auth()->login($u);
view()->share('errors', new Illuminate\Support\ViewErrorBag);

$student = App\Models\User::where('role', 'student')->first();
if (!$student) { echo "NO STUDENT\n"; exit(1); }

$summaryService = app(App\Services\ActivitySummaryService::class);
$summary = $summaryService->getSummary($student);
$attendances = App\Models\Attendance::with('activity.category', 'verifier')
    ->where('user_id', $student->id)->orderByDesc('checked_in_at')->get();
$activities = App\Models\Activity::orderByDesc('activity_date')->get(['id', 'title', 'activity_hours', 'activity_date']);

foreach (['en', 'th'] as $loc) {
    app()->setLocale($loc);
    try {
        $html = view('admin.students.show', [
            'student'       => $student,
            'totalHours'    => $summary['totalHours'],
            'totalRequired' => $summary['totalRequired'],
            'byCategory'    => $summary['byCategory'],
            'attendances'   => $attendances,
            'activities'    => $activities,
        ])->render();
        echo strtoupper($loc), " show: OK len=", strlen($html), " thai-text=", ($loc === 'en' ? preg_match_all('/[ก-๙]/u', strip_tags($html)) : 'n/a'), "\n";
    } catch (Throwable $e) {
        echo strtoupper($loc), " show: EXC ", substr($e->getMessage(), 0, 140), "\n";
    }
}
echo "flash TH sample: ", __('flash.เพิ่มบันทึกกิจกรรมเรียบร้อยแล้ว'), "\n";
