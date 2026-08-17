<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportStudentsRequest;
use App\Models\User;
use App\Services\StudentBulkImportService;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentImportController extends Controller
{
    use LogsAdminActivity;

    public function __construct(
        private readonly StudentBulkImportService $importService
    ) {}

    /**
     * หน้าจออัปโหลดไฟล์นำเข้านักศึกษา
     */
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $totalStudents = User::where('role', 'student')->count();
        $faculties = User::whereNotNull('faculty')->where('role', 'student')->distinct()->pluck('faculty')->sort();

        return view('admin.students.import', compact('totalStudents', 'faculties'));
    }

    /**
     * ประมวลผลการนำเข้าไฟล์
     */
    public function import(ImportStudentsRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $file = $request->file('file');
        $result = $this->importService->importFile($file);

        $this->auditAction(
            'import_students',
            'users',
            null,
            "นำเข้านักศึกษาสำเร็จ: สร้างใหม่ {$result['created_count']} คน, อัปเดต {$result['updated_count']} คน, ข้าม {$result['skipped_count']} คน"
        );

        if ($result['created_count'] === 0 && $result['updated_count'] === 0 && !empty($result['errors'])) {
            return back()->with('import_error', $result['errors'][0])->with('import_result', $result);
        }

        $message = "นำเข้าข้อมูลนักศึกษาเรียบร้อยแล้ว: เพิ่มใหม่ {$result['created_count']} คน, อัปเดตข้อมูล {$result['updated_count']} คน";
        if ($result['skipped_count'] > 0) {
            $message .= " (ข้ามแถวที่ไม่สมบูรณ์ {$result['skipped_count']} แถว)";
        }

        return redirect()->route('admin.students.index')->with('success', $message)->with('import_result', $result);
    }

    /**
     * ดาวน์โหลดไฟล์แม่แบบ CSV (Template)
     */
    public function downloadTemplate(): Response
    {
        $csvContent = $this->importService->generateTemplateCsv();

        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="student_import_template.csv"',
        ]);
    }
}
