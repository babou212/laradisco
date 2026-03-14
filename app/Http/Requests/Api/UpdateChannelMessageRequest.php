<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:16000'],
            'is_encrypted' => ['sometimes', 'boolean'],
            'sender_device_id' => ['nullable', 'string', 'uuid'],
            'search_tokens' => ['sometimes', 'array', 'max:500'],
            'search_tokens.*' => ['string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }
}
