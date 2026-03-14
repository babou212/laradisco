<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class RotateSignedPrekeyRequest extends FormRequest
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
            'signed_prekey' => ['required', 'string', 'min:32', 'max:128'],
            'signed_prekey_id' => ['required', 'integer', 'min:0'],
            'signed_prekey_signature' => ['required', 'string', 'min:64', 'max:256'],
        ];
    }
}
