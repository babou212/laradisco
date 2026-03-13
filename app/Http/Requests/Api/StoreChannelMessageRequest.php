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

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:16000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'is_encrypted' => ['sometimes', 'boolean'],
            'sender_device_id' => ['nullable', 'string', 'uuid'],
            'mention_user_ids' => ['sometimes', 'array', 'max:50'],
            'mention_user_ids.*' => ['integer', 'exists:users,id'],
            'mention_everyone' => ['sometimes', 'boolean'],
            'mention_here' => ['sometimes', 'boolean'],
            'search_tokens' => ['sometimes', 'array', 'max:500'],
            'search_tokens.*' => ['string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Message content is required.',
            'content.max' => 'Message cannot exceed 4000 characters.',
            'reply_to_id.exists' => 'The message you are replying to does not exist.',
        ];
    }
}
