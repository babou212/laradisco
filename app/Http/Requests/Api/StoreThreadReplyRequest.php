<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreThreadReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sender_device_id' => ['required', 'string', 'uuid'],
            'message_bytes' => ['required', 'string', 'max:65535'],
            'epoch' => ['sometimes', 'integer', 'min:0'],
            'mention_user_ids' => ['sometimes', 'array', 'max:50'],
            'mention_user_ids.*' => ['integer', 'exists:users,id'],
            'mention_everyone' => ['sometimes', 'boolean'],
            'mention_here' => ['sometimes', 'boolean'],
            'thread_name' => ['sometimes', 'string', 'max:100'],
            'client_temp_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'message_bytes.required' => 'Encrypted message payload is required.',
        ];
    }
}
