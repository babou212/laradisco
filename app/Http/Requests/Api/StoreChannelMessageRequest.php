<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Channel send permission is checked via PermissionService in controller
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'sender_device_id' => ['required', 'string', 'uuid'],
            'mention_user_ids' => ['sometimes', 'array', 'max:50'],
            'mention_user_ids.*' => ['integer', 'exists:users,id'],
            'mention_everyone' => ['sometimes', 'boolean'],
            'mention_here' => ['sometimes', 'boolean'],
            'history_ciphertext' => ['nullable', 'string', 'max:32000'],
            'message_bytes' => ['required', 'string', 'max:65535'],
            'epoch' => ['sometimes', 'integer', 'min:0'],
            'thread_name' => ['sometimes', 'string', 'max:100'],
            'attachment_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'message_bytes.required' => 'Encrypted message payload is required.',
            'reply_to_id.exists' => 'The message you are replying to does not exist.',
        ];
    }
}
