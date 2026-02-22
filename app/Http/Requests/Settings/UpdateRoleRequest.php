<?php

namespace App\Http\Requests\Settings;

use App\Enums\PermissionFlag;
use App\Models\Role;
use App\Services\PermissionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! ($this->user()->isAdministrator() || $this->user()->hasPermission(PermissionFlag::ManageRoles))) {
            return false;
        }

        /** @var Role $role */
        $role = $this->route('role');
        $permissionService = app(PermissionService::class);

        return $permissionService->canManageRole($this->user(), $role);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');
        $validPermissions = implode(',', array_map(fn (PermissionFlag $p) => $p->value, PermissionFlag::cases()));

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', "unique:roles,name,{$role->id}"],
            'color' => ['sometimes', 'required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_hoisted' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', "in:{$validPermissions}"],
            'is_mentionable' => ['sometimes', 'boolean'],
        ];
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
