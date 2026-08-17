<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaffOrAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100']];
    }
}
