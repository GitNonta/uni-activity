<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class QuickApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }

    public function rules(): array
    {
        return [
            'type'   => 'required|in:registration,attendance',
            'id'     => 'required|integer',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
