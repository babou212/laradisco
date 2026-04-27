<?php

namespace App\Http\Requests\Api;

use App\Models\DirectMessageGroup;
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
            'sender_device_id' => ['required', 'string', 'uuid'],
            'message_bytes' => ['required', 'string', 'max:65535'],
            'epoch' => ['sometimes', 'integer', 'min:0'],
            'attachment_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_ids.*' => ['uuid'],
            'client_temp_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
