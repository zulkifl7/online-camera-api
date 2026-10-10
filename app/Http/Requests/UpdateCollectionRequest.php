<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'event_date' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'uploads_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
