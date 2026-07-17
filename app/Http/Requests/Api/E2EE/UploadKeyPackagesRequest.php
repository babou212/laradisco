<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class UploadKeyPackagesRequest extends FormRequest
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
            'device_id' => ['sometimes', 'string', 'uuid'],
            'key_packages' => ['required', 'array', 'min:1', 'max:100'],
            'key_packages.*.key_package_bytes' => ['required', 'string'],
            'key_packages.*.key_package_hash' => ['required', 'string', 'size:64'],
            'key_packages.*.identity_signature' => ['nullable', 'string'],
        ];
    }
}
