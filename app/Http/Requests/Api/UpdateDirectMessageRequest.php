<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sender_device_id' => ['nullable', 'string', 'uuid'],
            'history_ciphertext' => ['nullable', 'string', 'max:32000'],
            'message_bytes' => ['nullable', 'string', 'max:65535'],
            'epoch' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
