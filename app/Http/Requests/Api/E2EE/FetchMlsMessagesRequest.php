<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class FetchMlsMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'since_epoch' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'since_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'message_type' => ['sometimes', 'nullable', 'string', 'in:commit,proposal,application'],
        ];
    }
}
