<?php

namespace App\Http\Requests\Api;

use App\Enums\UserStatusType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(UserStatusType::class)],
            'custom_status' => ['nullable', 'string', 'max:128'],
        ];
    }
}
