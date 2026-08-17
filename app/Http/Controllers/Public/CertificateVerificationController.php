<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    /**
     * หน้าตรวจสอบความถูกต้องของใบรับรอง/เกียรติบัตร (Public Verification Page)
     */
    public function verify(string $code): View
    {
        $certificate = Certificate::where('certificate_code', $code)
            ->orWhere('verification_token', $code)
            ->with(['user', 'category'])
            ->first();

        $isValid = $certificate !== null;

        return view('certificates.verify', compact('certificate', 'isValid', 'code'));
    }
}
