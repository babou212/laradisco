<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
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
            'mention_user_ids' => ['sometimes', 'array', 'max:50'],
            'mention_user_ids.*' => ['integer', 'exists:users,id'],
            'mention_everyone' => ['sometimes', 'boolean'],
            'mention_here' => ['sometimes', 'boolean'],
            'content' => ['nullable', 'string', 'max:65535'],
            'thread_name' => ['sometimes', 'string', 'max:100'],
            'attachment_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_ids.*' => ['uuid'],
            'client_temp_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $content = trim((string) $this->input('content', ''));
            $attachments = (array) $this->input('attachment_ids', []);
            if ($content === '' && count($attachments) === 0) {
                $v->errors()->add('content', 'Either content or attachment_ids is required.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'reply_to_id.exists' => 'The message you are replying to does not exist.',
        ];
    }
}
