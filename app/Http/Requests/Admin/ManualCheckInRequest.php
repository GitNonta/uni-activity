<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ManualCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }

    public function rules(): array
    {
        return [
            'student_id' => 'required|string|max:50',
        ];
    }
}
