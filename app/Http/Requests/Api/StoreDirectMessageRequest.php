<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\DirectMessageGroup $dmGroup */
        $dmGroup = $this->route('dmGroup');

        return $dmGroup->participants()->where('users.id', $this->user()->id)->exists();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:16000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:direct_messages,id'],
            'is_encrypted' => ['sometimes', 'boolean'],
            'sender_device_id' => ['nullable', 'string', 'uuid'],
            'search_tokens' => ['sometimes', 'array', 'max:500'],
            'search_tokens.*' => ['string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }
}
