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
            'content' => ['required', 'string', 'min:1', 'max:4000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
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
