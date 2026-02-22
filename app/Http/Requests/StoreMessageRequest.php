<?php

namespace App\Http\Requests;

use App\Enums\PermissionFlag;
use App\Services\PermissionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $channel = $this->route('channel');
        $permissionService = app(PermissionService::class);

        return $permissionService->userCanInChannel($this->user(), $channel, PermissionFlag::SendMessages);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
            'reply_to_id' => ['nullable', 'exists:messages,id'],
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
            'content.required' => 'Message content is required.',
            'content.max' => 'Message cannot exceed 2000 characters.',
            'reply_to_id.exists' => 'The message you are replying to does not exist.',
        ];
    }
}
