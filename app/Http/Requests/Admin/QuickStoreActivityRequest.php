<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class QuickStoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isStaffOrAdmin();
    }

    public function rules(): array
    {
        return [
            'title'          => 'required|string|max:255',
            'location'       => 'required|string|max:255',
            'category_id'    => 'required|exists:activity_categories,id',
            'activity_date'  => 'required|date',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i',
            'activity_hours' => 'required|numeric|min:0.5',
        ];
    }
}
