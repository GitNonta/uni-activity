<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }

    public function rules(): array
    {
        return [
            'expires_in_hours' => 'nullable|integer|min:1|max:720',
        ];
    }
}
