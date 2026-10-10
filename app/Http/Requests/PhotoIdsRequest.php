<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhotoIdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('online-camera.max_bulk_photos');

        return [
            'photo_ids' => ['required', 'array', 'min:1', 'max:'.$max],
            'photo_ids.*' => ['required', 'uuid'],
        ];
    }
}
