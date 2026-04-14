<?php

namespace App\Policies;

use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function viewChannel(User $user, Channel $channel): Response
    {
        return $this->permissionService->userCanViewChannel($user, $channel)
            ? Response::allow()
            : Response::deny('You do not have access to this channel.');
    }

    public function send(User $user, Channel $channel): Response
    {
        return $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::SendMessages)
            ? Response::allow()
            : Response::deny('You do not have permission to send messages in this channel.');
    }

    public function update(User $user, Message $message): Response
    {
        return $user->id === $message->user_id
            ? Response::allow()
            : Response::deny('You can only edit your own messages.');
    }

    public function delete(User $user, Message $message, Channel $channel): Response
    {
        if ($user->id === $message->user_id) {
            return Response::allow();
        }

        return $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::ManageMessages)
            ? Response::allow()
            : Response::deny('You do not have permission to delete this message.');
    }
}
