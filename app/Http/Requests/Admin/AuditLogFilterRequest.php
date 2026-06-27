<?php
declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy already restricts access; allow if authenticated
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'   => ['nullable', 'integer', 'exists:users,id'],
            'action'    => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
