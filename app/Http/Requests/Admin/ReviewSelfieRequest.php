<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSelfieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
        ];
    }
}
