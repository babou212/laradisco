<?php

namespace App\Http\Requests\Api;

use App\Enums\PermissionFlag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChannelOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by ChannelPolicy
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $validPerms = implode(',', array_map(
            fn (PermissionFlag $p) => $p->value,
            PermissionFlag::cases()
        ));

        return [
            'role_id' => ['nullable', 'exists:roles,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'allow' => ['array'],
            'allow.*' => ['string', "in:{$validPerms}"],
            'deny' => ['array'],
            'deny.*' => ['string', "in:{$validPerms}"],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->input('role_id')) && empty($this->input('user_id'))) {
                $validator->errors()->add('role_id', 'Either a role or user must be specified.');
            }
        });
    }
}
