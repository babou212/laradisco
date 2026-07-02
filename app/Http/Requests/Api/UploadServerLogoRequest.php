<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadServerLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdministrator() || $user->hasPermissionTo('manage_server'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.required' => 'A logo image is required.',
            'logo.image' => 'The file must be an image.',
            'logo.mimes' => 'The logo must be a JPEG, PNG, GIF, or WebP image.',
            'logo.max' => 'The logo must not exceed 10 MB.',
        ];
    }
}
