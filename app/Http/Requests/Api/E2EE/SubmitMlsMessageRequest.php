<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMlsMessageRequest extends FormRequest
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
            'device_id' => ['sometimes', 'string', 'uuid'],
            'message_type' => ['required', 'string', 'in:commit,proposal,application'],
            'message_bytes' => ['required', 'string', 'max:65535'],
            'epoch' => ['required', 'integer', 'min:0'],
        ];
    }
}
