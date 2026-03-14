<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_type' => ['required', 'string', 'in:channel,dm'],
            'conversation_id' => ['required', 'integer', 'min:1'],
            'tokens' => ['required', 'array', 'min:1', 'max:10'],
            'tokens.*' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/', 'distinct'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'before_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'tokens.required' => 'At least one search token is required.',
            'tokens.max' => 'Maximum 10 search tokens per query.',
            'tokens.*.size' => 'Each token must be exactly 64 hex characters.',
            'tokens.*.regex' => 'Each token must be a valid hex string.',
            'tokens.*.distinct' => 'Each search token must be unique within the query.',
            'conversation_type.in' => 'Conversation type must be "channel" or "dm".',
        ];
    }
}
