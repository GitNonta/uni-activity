<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:3000',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'กรุณากรอกข้อความที่ต้องการแก้ไข',
            'message.max'      => 'ข้อความต้องมีความยาวไม่เกิน 3,000 ตัวอักษร',
        ];
    }
}
