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
            'device_identity_key' => ['required', 'string', 'min:32', 'max:128'],
            'identity_signature' => ['required', 'string', 'min:64', 'max:256'],
            'signed_prekey' => ['required', 'string', 'min:32', 'max:128'],
            'signed_prekey_id' => ['required', 'integer', 'min:0'],
            'signed_prekey_signature' => ['required', 'string', 'min:64', 'max:256'],
            'one_time_prekeys' => ['required', 'array', 'min:1', 'max:100'],
            'one_time_prekeys.*.prekey_id' => ['required', 'integer', 'min:0'],
            'one_time_prekeys.*.public_key' => ['required', 'string', 'min:32', 'max:128'],
        ];
    }
}
