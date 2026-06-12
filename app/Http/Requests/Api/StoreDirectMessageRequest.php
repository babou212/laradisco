<?php

namespace App\Http\Requests\Api;

use App\Models\DirectMessageGroup;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DirectMessageGroup $dmGroup */
        $dmGroup = $this->route('dmGroup');

        return $dmGroup->participants()->where('users.id', $this->user()->id)->exists();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reply_to_id' => ['nullable', 'integer', 'exists:direct_messages,id'],
            'content' => ['nullable', 'string', 'max:65535'],
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
}
