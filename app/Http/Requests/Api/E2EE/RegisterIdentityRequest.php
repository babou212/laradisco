<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class RegisterIdentityRequest extends FormRequest
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
            'identity_key' => ['required', 'string', 'min:32', 'max:128'],
        ];
    }
}
