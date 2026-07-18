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
            'content' => ['prohibited'],
            'message_bytes' => ['required', 'string', 'max:262144'],
            'sender_device_id' => ['required', 'string', 'size:36'],
            'epoch' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
