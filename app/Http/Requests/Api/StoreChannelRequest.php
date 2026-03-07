<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by ChannelPolicy
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'topic' => ['nullable', 'string', 'max:1024'],
            'type' => ['required', 'in:text,voice'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_private' => ['sometimes', 'boolean'],
            'slowmode_seconds' => ['sometimes', 'integer', 'min:0', 'max:21600'],
        ];
    }
}
