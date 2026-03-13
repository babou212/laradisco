<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class ReplenishPrekeysRequest extends FormRequest
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
            'device_id' => ['required', 'string', 'uuid'],
            'prekeys' => ['required', 'array', 'min:1', 'max:100'],
            'prekeys.*.prekey_id' => ['required', 'integer', 'min:0'],
            'prekeys.*.public_key' => ['required', 'string', 'min:32', 'max:128'],
        ];
    }
}
