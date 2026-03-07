<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dmGroup = $this->route('dmGroup');

        return $dmGroup->participants()->where('users.id', $this->user()->id)->exists();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:direct_messages,id'],
        ];
    }
}
