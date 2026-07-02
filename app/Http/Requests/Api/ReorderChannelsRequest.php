<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReorderChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by ChannelPolicy
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.channel_ids' => ['present', 'array'],
            'categories.*.channel_ids.*' => ['integer', 'distinct', 'exists:channels,id'],
        ];
    }
}
