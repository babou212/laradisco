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
            'link_preview' => ['sometimes', 'nullable', 'array'],
            'link_preview.url' => ['required_with:link_preview', 'string', 'max:2048'],
            'link_preview.title' => ['nullable', 'string', 'max:1024'],
            'link_preview.description' => ['nullable', 'string', 'max:4096'],
            'link_preview.site_name' => ['nullable', 'string', 'max:255'],
            'link_preview.image_url' => ['nullable', 'string', 'max:2048'],
            'link_preview.image_width' => ['nullable', 'integer'],
            'link_preview.image_height' => ['nullable', 'integer'],
            'link_preview.fetched_at' => ['nullable', 'integer'],
            'link_preview.image' => ['nullable', 'array'],
            'link_preview.image.id' => ['nullable', 'uuid'],
            'link_preview.image.mime_type' => ['nullable', 'string', 'max:255'],
            'link_preview.image.size' => ['nullable', 'integer'],
            'link_preview.image.width' => ['nullable', 'integer'],
            'link_preview.image.height' => ['nullable', 'integer'],
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
