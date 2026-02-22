<?php

namespace App\Http\Requests\Settings;

use App\Enums\PermissionFlag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdministrator()
            || $this->user()->hasPermission(PermissionFlag::ManageRoles);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $validPermissions = implode(',', array_map(fn (PermissionFlag $p) => $p->value, PermissionFlag::cases()));

        return [
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_hoisted' => ['boolean'],
            'position' => ['integer', 'min:0'],
            'permissions' => ['array'],
            'permissions.*' => ['string', "in:{$validPermissions}"],
            'is_mentionable' => ['boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_hoisted' => $this->boolean('is_hoisted'),
            'is_mentionable' => $this->boolean('is_mentionable', true),
            'position' => $this->input('position', 1),
            'permissions' => $this->input('permissions', []),
        ]);
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required.',
            'name.unique' => 'A role with this name already exists.',
            'color.regex' => 'Color must be a valid hex color (e.g. #FF0000).',
        ];
    }
}
