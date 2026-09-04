<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'is_multiday' => 'boolean',
            'activity_date' => 'required|date',
            'end_date' => 'nullable|required_if:is_multiday,true|date|after_or_equal:activity_date',
            'min_hours_before_checkout' => 'nullable|numeric|min:0',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'activity_hours' => 'required|numeric|min:0.5|max:999',
            'max_participants' => 'required|integer|min:1',
            'register_open_at' => 'required|date',
            'register_close_at' => 'required|date',
            'checkin_open_at' => 'nullable|required_unless:is_multiday,1,true|date',
            'checkin_close_at' => 'nullable|required_unless:is_multiday,1,true|date',
            'checkout_open_at' => 'nullable|date',
            'checkout_close_at' => 'nullable|date',
            'category_id' => 'required|exists:activity_categories,id',
            'scope' => 'required|in:university,faculty,department',
            'faculty' => 'nullable|required_if:scope,faculty,department|string|max:100',
            'department' => 'nullable|required_if:scope,department|string|max:100',
            'status' => 'nullable|in:upcoming,open,full,ongoing,done,cancelled',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'checkin_radius' => 'nullable|integer|min:10|max:5000',
            'require_attendance_approval' => 'boolean',
            'require_selfie_verification' => 'boolean',
            'allow_walkin' => 'boolean',
            'is_mandatory' => 'boolean',
            'require_face_scan' => 'boolean',
            'face_scan_method' => 'nullable|string|in:python,js',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'is_no_checkout' => 'nullable|boolean',
            'days' => 'nullable|array',
            'days.*.date' => 'required_with:days|date',
            'days.*.day_number' => 'nullable|integer|min:1',
            'days.*.start_time' => 'nullable|date_format:H:i',
            'days.*.end_time' => 'nullable|date_format:H:i',
            'days.*.activity_hours' => 'nullable|numeric|min:0|max:999',
            'days.*.checkin_open_at' => 'nullable|date',
            'days.*.checkin_close_at' => 'nullable|date',
            'days.*.checkout_open_at' => 'nullable|date',
            'days.*.checkout_close_at' => 'nullable|date',
        ];
    }
}
