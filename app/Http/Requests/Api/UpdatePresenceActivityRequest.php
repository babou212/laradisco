<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresenceActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // A null activity clears the user's current rich presence.
            'activity' => ['present', 'nullable', 'array'],
            'activity.type' => ['required_with:activity', Rule::in(['game', 'music', 'app'])],
            'activity.name' => ['required_with:activity', 'string', 'max:128'],
            'activity.application_id' => ['required_with:activity', 'string', 'max:128'],
            'activity.details' => ['sometimes', 'nullable', 'string', 'max:128'],
            'activity.icon' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }
}
