<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request สำหรับการแก้ไขเกณฑ์การสำเร็จการศึกษา
 */
class UpdateGraduationCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'                 => 'required|string|max:150',
            'min_total_hours'      => 'required|numeric|min:0|max:500',
            'min_mandatory_hours'  => 'required|numeric|min:0|max:200',
            'scope_university'     => 'required|numeric|min:0|max:300',
            'scope_faculty'        => 'required|numeric|min:0|max:300',
            'category_volunteer'   => 'required|numeric|min:0|max:200',
            'description'          => 'nullable|string|max:1000',
        ];
    }
}
