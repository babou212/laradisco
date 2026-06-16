<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($this->user()->id),
            ],
            'username' => ['sometimes', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('users')->ignore($this->user()->id),
            ],
            'about_me' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
