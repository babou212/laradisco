<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class StoreKeyBackupRequest extends FormRequest
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
            'encrypted_bundle' => ['required', 'string'],
            'salt' => ['required', 'string', 'min:32', 'max:128'],
            'nonce' => ['required', 'string', 'min:12', 'max:64'],
            'argon2_params' => ['required', 'array'],
            'argon2_params.memory' => ['required', 'integer', 'min:65536', 'max:1048576'],
            'argon2_params.iterations' => ['required', 'integer', 'min:2', 'max:20'],
            'argon2_params.parallelism' => ['required', 'integer', 'min:1', 'max:8'],
        ];
    }
}
