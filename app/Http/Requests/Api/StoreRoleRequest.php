<?php

namespace App\Http\Requests\Api;

use App\Enums\PermissionFlag;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by RolePolicy
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $validPerms = implode(',', array_map(
            fn (PermissionFlag $p) => $p->value,
            PermissionFlag::cases()
        ));

        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_hoisted' => ['boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', "in:{$validPerms}"],
            'is_mentionable' => ['boolean'],
        ];
    }
}
