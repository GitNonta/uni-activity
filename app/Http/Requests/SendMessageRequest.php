<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'message'       => 'nullable|string|max:3000',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip,txt',
        ];
    }

    public function messages(): array
    {
        return [
            'message.max'         => 'ข้อความต้องมีความยาวไม่เกิน 3,000 ตัวอักษร',
            'attachments.max'     => 'สามารถแนบไฟล์ได้สูงสุด 5 ไฟล์ต่อครั้ง',
            'attachments.*.max'   => 'ไฟล์แนบแต่ละไฟล์ต้องมีขนาดไม่เกิน 10MB',
            'attachments.*.mimes' => 'รองรับเฉพาะไฟล์รูปภาพ, PDF, เอกสาร Office, หรือไฟล์ Zip เท่านั้น',
        ];
    }
}
