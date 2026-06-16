<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AckInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'max:500'],
            'items.*.message_type' => ['required', 'string', 'in:channel,direct_message'],
            'items.*.message_id' => ['required', 'integer'],
        ];
    }
}
