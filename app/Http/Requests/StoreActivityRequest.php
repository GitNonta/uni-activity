<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request สำหรับสร้างกิจกรรมใหม่
 * ตรวจสอบสิทธิ์และ validation ข้อมูลทั้งหมดที่ต้องกรอก
 */
class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }


    /** กฎ validation สำหรับแต่ละฟิลด์ */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'activity_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'activity_hours' => 'required|numeric|min:0.5|max:24',
            'max_participants' => 'required|integer|min:1',
            'register_open_at' => 'required|date',
            'register_close_at' => 'required|date|after:register_open_at',
            'checkin_open_at' => 'required|date',
            'checkin_close_at' => 'required|date|after:checkin_open_at',
            'checkout_open_at' => 'required|date|after_or_equal:checkin_open_at',
            'checkout_close_at' => 'required|date|after:checkout_open_at',
            'category_id' => 'required|exists:activity_categories,id',
            'scope' => 'required|in:university,faculty,department',
            'faculty' => 'nullable|required_if:scope,faculty,department|string|max:100',
            'department' => 'nullable|required_if:scope,department|string|max:100',
            'is_mandatory' => 'boolean',
            'require_attendance_approval' => 'boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'checkin_radius' => 'nullable|integer|min:10|max:5000',
        ];
    }

    /** ข้อความแสดงข้อผิดพลาดเป็นภาษาไทย */
    public function messages(): array
    {
        return [
            'title.required' => 'กรุณากรอกชื่อกิจกรรม',
            'location.required' => 'กรุณากรอกสถานที่',
            'activity_date.required' => 'กรุณาระบุวันที่จัดกิจกรรม',
            'activity_date.after_or_equal' => 'วันที่จัดกิจกรรมต้องเป็นวันนี้หรือหลังจากนี้',
            'max_participants.min' => 'จำนวนผู้เข้าร่วมต้องอย่างน้อย 1 คน',
            'activity_hours.min' => 'ชั่วโมงกิจกรรมต้องอย่างน้อย 0.5 ชั่วโมง',
        ];
    }
}
