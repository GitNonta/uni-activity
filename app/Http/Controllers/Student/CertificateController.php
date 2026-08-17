<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ClaimCertificateRequest;
use App\Models\ActivityCategory;
use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificateService
    ) {}

    /**
     * แสดงรายการใบรับรองของนักศึกษาที่สามารถขอหรือออกได้
     */
    public function index(): View
    {
        $user = Auth::user();
        $certificates = Certificate::where('user_id', $user->id)
            ->with('category')
            ->orderByDesc('issued_at')
            ->get();

        $categories = ActivityCategory::all();

        return view('student.certificates.index', compact('certificates', 'categories', 'user'));
    }

    /**
     * ขอนำส่ง/ออกใบรับรองหมวดหมู่
     */
    public function claim(ClaimCertificateRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $categoryId = $request->validated('category_id');

        if ($categoryId) {
            $category = ActivityCategory::findOrFail($categoryId);
            $cert = $this->certificateService->issueCategoryCertificate($user, $category);
            if (!$cert) {
                return back()->with('error', "คุณยังสะสมชั่วโมงกิจกรรมในหมวด \"{$category->name}\" ไม่ครบตามเกณฑ์");
            }
        } else {
            $cert = $this->certificateService->issueGeneralCertificate($user);
            if (!$cert) {
                return back()->with('error', "ไม่สามารถออกใบรับรองได้");
            }
        }

        return redirect()->route('student.certificates.index')->with('success', "ออกใบรับรอง \"{$cert->title}\" สำเร็จเรียบร้อยแล้ว!");
    }

    /**
     * ดาวน์โหลดไฟล์ PDF ใบรับรอง
     */
    public function download(Certificate $certificate): Response
    {
        $user = Auth::user();

        // ตรวจสอบว่าเป็นเจ้าของใบรับรอง หรือเป็น Staff/Admin
        if ($certificate->user_id !== $user->id && !$user->isStaffOrAdmin()) {
            abort(403, 'คุณไม่มีสิทธิ์ดาวน์โหลดใบรับรองนี้');
        }

        return $this->certificateService->renderPdf($certificate);
    }
}
