<?php

namespace App\Actions;

use App\Models\Message;

class CreateThreadReplyResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?Message $reply,
        public readonly ?string $error,
        public readonly bool $duplicate = false,
    ) {}

    public static function success(Message $reply): self
    {
        return new self(true, $reply, null);
    }

    public static function duplicate(Message $reply): self
    {
        return new self(true, $reply, null, true);
    }

    public static function forbidden(string $message): self
    {
        return new self(false, null, $message);
    }
}
