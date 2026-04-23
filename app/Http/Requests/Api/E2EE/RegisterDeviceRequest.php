<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_identity_key' => ['nullable', 'string'],
            'identity_signature' => ['nullable', 'string'],
            'key_packages' => ['sometimes', 'array', 'max:100'],
            'key_packages.*.key_package_bytes' => ['required_with:key_packages', 'string'],
            'key_packages.*.key_package_hash' => ['required_with:key_packages', 'string'],
        ];
    }
}
