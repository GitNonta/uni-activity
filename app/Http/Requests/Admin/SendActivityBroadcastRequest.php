<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request สำหรับการส่งบรอดแคสต์ข้อความด่วนไปยังนักศึกษาในกิจกรรม
 */
class SendActivityBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'           => 'required|string|max:255',
            'message'         => 'required|string|max:5000',
            'type'            => 'required|in:urgent,venue_change,time_change,reschedule,reminder,general',
            'target_audience' => 'required|in:all,approved,waitlisted',
            'channels'        => 'required|array|min:1',
            'channels.*'      => 'in:in_app,line',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'           => 'กรุณาระบุหัวข้อประกาศ',
            'message.required'         => 'กรุณาระบุข้อความที่ต้องการส่ง',
            'type.required'            => 'กรุณาเลือกประเภทประกาศ',
            'target_audience.required' => 'กรุณาเลือกกลุ่มเป้าหมายผู้รับข้อความ',
            'channels.required'        => 'กรุณาเลือกอย่างน้อยหนึ่งช่องทางการส่ง',
            'channels.min'             => 'กรุณาเลือกอย่างน้อยหนึ่งช่องทางการส่ง',
        ];
    }
}
