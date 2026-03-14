<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class DistributeSenderKeysRequest extends FormRequest
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
            'distribution_id' => ['required', 'string', 'uuid'],
            'distributions' => ['required', 'array', 'min:1'],
            'distributions.*.recipient_user_id' => ['required', 'integer', 'exists:users,id'],
            'distributions.*.recipient_device_id' => ['required', 'string', 'uuid'],
            'distributions.*.encrypted_distribution' => ['required', 'string', 'min:1'],
            'distributions.*.ephemeral_public_key' => ['required', 'string', 'min:32', 'max:128'],
            'distributions.*.nonce' => ['required', 'string', 'min:12', 'max:32'],
        ];
    }
}
