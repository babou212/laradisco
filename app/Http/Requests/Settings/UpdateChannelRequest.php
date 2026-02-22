<?php

namespace App\Http\Requests\Settings;

use App\Enums\ChannelType;
use App\Enums\PermissionFlag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateChannelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdministrator()
            || $this->user()->hasPermission(PermissionFlag::ManageChannels);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:100'],
            'topic' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'type' => ['sometimes', 'required', Rule::enum(ChannelType::class)],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_private' => ['sometimes', 'boolean'],
            'slowmode_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:21600'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name') && ! $this->has('slug')) {
            $this->merge([
                'slug' => Str::slug($this->input('name', '')),
            ]);
        }
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Channel name is required.',
        ];
    }
}
