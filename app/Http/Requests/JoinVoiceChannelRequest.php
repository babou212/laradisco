<?php

namespace App\Http\Requests;

use App\Enums\ChannelType;
use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Services\PermissionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class JoinVoiceChannelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Channel $channel */
        $channel = $this->route('channel');
        $permissionService = app(PermissionService::class);

        return $channel->type === ChannelType::Voice
            && $permissionService->userCanInChannel($this->user(), $channel, PermissionFlag::Connect);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }
}
