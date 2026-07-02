<?php

namespace App\Http\Requests;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MoveVoiceMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdministrator() || $this->user()->hasPermissionTo('move_members');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Channel $channel */
        $channel = $this->route('channel');

        return [
            'to_channel_id' => ['required', 'integer', 'different:'.$channel->id, 'exists:channels,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Confirm the destination channel is actually a voice channel.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $toChannelId = $this->input('to_channel_id');
            if ($toChannelId === null) {
                return;
            }

            /** @var Channel|null $toChannel */
            $toChannel = Channel::find($toChannelId);
            if ($toChannel && $toChannel->type !== ChannelType::Voice) {
                $validator->errors()->add('to_channel_id', 'The destination channel must be a voice channel.');
            }
        });
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_channel_id.different' => 'The destination channel must be different from the source channel.',
        ];
    }
}
