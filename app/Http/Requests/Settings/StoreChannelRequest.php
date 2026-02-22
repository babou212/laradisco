<?php

namespace App\Http\Requests\Settings;

use App\Enums\ChannelType;
use App\Enums\PermissionFlag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreChannelRequest extends FormRequest
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
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'topic' => ['nullable', 'string', 'max:1024'],
            'type' => ['required', Rule::enum(ChannelType::class)],
            'position' => ['integer', 'min:0'],
            'is_private' => ['boolean'],
            'slowmode_seconds' => ['nullable', 'integer', 'min:0', 'max:21600'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->input('slug') ?: Str::slug($this->input('name', '')),
            'is_private' => $this->boolean('is_private'),
            'position' => $this->input('position', 0),
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
            'name.required' => 'Channel name is required.',
            'type.required' => 'Channel type is required.',
        ];
    }
}
