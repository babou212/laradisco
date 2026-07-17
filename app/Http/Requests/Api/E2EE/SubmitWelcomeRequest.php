<?php

namespace App\Http\Requests\Api\E2EE;

use Illuminate\Foundation\Http\FormRequest;

class SubmitWelcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->has('recipients')) {
            return [
                'recipients' => ['required', 'array', 'min:1', 'max:100'],
                'recipients.*.user_id' => ['required', 'integer', 'exists:users,id'],
                'recipients.*.device_id' => ['required', 'string', 'uuid'],
                'recipients.*.welcome_bytes' => ['required', 'string', 'max:131072'],
                'ratchet_tree_bytes' => ['required', 'string', 'max:131072'],
            ];
        }

        return [
            'recipient_user_id' => ['required', 'integer', 'exists:users,id'],
            'recipient_device_id' => ['required', 'string', 'uuid'],
            'welcome_bytes' => ['required', 'string', 'max:131072'],
            'ratchet_tree_bytes' => ['required', 'string', 'max:131072'],
        ];
    }
}
