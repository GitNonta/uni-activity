<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'student_email_prefix'              => 'nullable|string|max:10',
            'student_email_domain'              => 'nullable|string|starts_with:@|max:50',
            'auto_approve_enabled'              => 'nullable|boolean',
            'auto_approve_min_face_score'       => 'nullable|numeric|min:50|max:100',
            'auto_approve_max_distance'         => 'nullable|numeric|min:5|max:1000',
            'auto_approve_require_liveness'     => 'nullable|boolean',
            'auto_approve_prevent_shared_device'=> 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'student_email_domain.starts_with'   => 'โดเมนอีเมลสถาบันต้องขึ้นต้นด้วยเครื่องหมาย @ เสมอ',
            'auto_approve_min_face_score.min'    => 'เกณฑ์คะแนนใบหน้าขั้นต่ำต้องไม่น้อยกว่า 50%',
            'auto_approve_min_face_score.max'    => 'เกณฑ์คะแนนใบหน้าขั้นต่ำต้องไม่เกิน 100%',
            'auto_approve_max_distance.min'      => 'เกณฑ์ระยะห่าง GPS ต้องไม่น้อยกว่า 5 เมตร',
            'auto_approve_max_distance.max'      => 'เกณฑ์ระยะห่าง GPS ต้องไม่เกิน 1,000 เมตร',
        ];
    }
}
