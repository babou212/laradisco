<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avatar.required' => 'An avatar image is required.',
            'avatar.image' => 'The file must be an image.',
            'avatar.mimes' => 'The avatar must be a JPEG, PNG, GIF, or WebP image.',
            'avatar.max' => 'The avatar must not exceed 10 MB.',
        ];
    }
}
