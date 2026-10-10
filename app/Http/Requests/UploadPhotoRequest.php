<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kilobytes = (int) config('online-camera.max_upload_kilobytes');

        return [
            'photo' => ['required', 'file', 'max:'.$kilobytes],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Choose a photo to upload.',
            'photo.max' => 'That photo is larger than the upload limit.',
        ];
    }
}
