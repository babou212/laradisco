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
