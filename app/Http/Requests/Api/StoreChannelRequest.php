<?php

namespace App\Http\Requests\Api;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by ChannelPolicy
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('channel_type') && ! $this->has('type')) {
            $this->merge(['type' => $this->input('channel_type')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $channelId = $this->route('channel')?->id;

        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($isUpdate, $channelId): void {
                    $slug = Str::slug($value);
                    $categoryId = $this->input('category_id');
                    $query = Channel::where('slug', $slug)->where('category_id', $categoryId);
                    if ($isUpdate && $channelId) {
                        $query->where('id', '!=', $channelId);
                    }
                    if ($query->exists()) {
                        $fail('A channel with that name already exists in this category.');
                    }
                },
            ],
            'topic' => ['nullable', 'string', 'max:1024'],
            'type' => [$isUpdate ? 'sometimes' : 'required', 'in:text,voice'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_private' => ['sometimes', 'boolean'],
            'slowmode_seconds' => ['sometimes', 'integer', 'min:0', 'max:21600'],
        ];
    }
}
