<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request สำหรับการตรวจสอบกิจกรรมและสถานที่จัดงานชนกันแบบ Real-time
 */
class CheckActivityConflictRequest extends FormRequest
{
    /**
     * สิทธิ์การใช้งาน: เจ้าหน้าที่หรือผู้ดูแลระบบเท่านั้น
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }

    /**
     * กฎการตรวจสอบข้อมูล
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location'      => 'required|string|max:255',
            'activity_date' => 'required|date',
            'start_time'    => 'nullable|string|max:10',
            'end_time'      => 'nullable|string|max:10',
            'end_date'      => 'nullable|date',
            'exclude_id'    => 'nullable|integer',
        ];
    }
}
