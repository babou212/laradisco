<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\AttachmentStatus;
use App\Enums\PermissionFlag;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CursorPaginateRequest;
use App\Http\Requests\Api\StoreChannelMessageRequest;
use App\Http\Requests\Api\UpdateChannelMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Channel;
use App\Models\EncryptedAttachment;
use App\Models\Message;
use App\Services\MentionService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MentionService $mentionService,
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Get cursor-paginated messages for a channel.
     */
    public function index(CursorPaginateRequest $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $messages = $channel->messages()
            ->whereNull('thread_id')
            ->with([
                'user:id,username,name,nickname,status,custom_status',
                'attachments',
                'encryptedAttachments',
                'reactions',
                'replyTo.user:id,username,name,nickname,status,custom_status',
                'threadStarted.latestReply.user:id,username,name,nickname,status,custom_status',
                'threadStarted.followers',
            ])
            ->orderBy('created_at', 'asc')
            ->cursorPaginate(50);

        $messages->through(fn ($message) => new MessageResource($message));

        return $this->successResponse($messages);
    }

    /**
     * Store a new channel message.
     */
    public function store(StoreChannelMessageRequest $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::SendMessages)) {
            return $this->forbiddenResponse('You do not have permission to send messages in this channel.');
        }

        $message = $channel->messages()->create([
            'user_id' => $user->id,
            'reply_to_id' => $request->validated('reply_to_id'),
            'sender_device_id' => $request->validated('sender_device_id'),
            'history_ciphertext' => $request->validated('history_ciphertext'),
            'message_bytes' => $request->validated('message_bytes'),
            'epoch' => $request->validated('epoch', 0),
        ]);

        $attachmentIds = $request->validated('attachment_ids', []);
        if (! empty($attachmentIds)) {
            EncryptedAttachment::where('user_id', $user->id)
                ->where('status', AttachmentStatus::Pending)
                ->whereIn('id', $attachmentIds)
                ->update([
                    'attachable_type' => Message::class,
                    'attachable_id' => $message->id,
                    'status' => AttachmentStatus::Attached,
                    'expires_at' => null,
                ]);
        }

        $message->load(['user:id,username,name,nickname,status,custom_status', 'replyTo.user:id,username,name,nickname,status,custom_status']);

        broadcast(new MessageSent($message))->toOthers();

        $this->mentionService->processMentionsFromMetadata(
            $message,
            $request->validated('mention_user_ids', []),
            $request->validated('mention_everyone', false),
            $request->validated('mention_here', false),
        );

        return $this->createdResponse(
            new MessageResource($message),
            'Created successfully',
            route('api.channels.messages.store', $channel).'/'.$message->id,
        );
    }

    /**
     * Update a channel message (owner only).
     */
    public function update(UpdateChannelMessageRequest $request, Channel $channel, Message $message): JsonResponse
    {
        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        if ($request->user()->id !== $message->user_id) {
            return $this->forbiddenResponse('You can only edit your own messages.');
        }

        $message->update([
            'sender_device_id' => $request->validated('sender_device_id', $message->sender_device_id),
            'history_ciphertext' => $request->validated('history_ciphertext', $message->history_ciphertext),
            'message_bytes' => $request->validated('message_bytes', $message->message_bytes),
            'epoch' => $request->validated('epoch', $message->epoch),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        broadcast(new MessageEdited($message))->toOthers();

        return $this->successResponse(
            new MessageResource($message),
            'Message updated successfully',
        );
    }

    /**
     * Delete a channel message (owner or permission-holder).
     */
    public function destroy(Request $request, Channel $channel, Message $message): JsonResponse|Response
    {
        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        $isOwner = $request->user()->id === $message->user_id;
        $canManage = $this->permissionService->userCanInChannel(
            $request->user(), $channel, PermissionFlag::ManageMessages
        );

        if (! $isOwner && ! $canManage) {
            return $this->forbiddenResponse('You do not have permission to delete this message.');
        }

        $messageId = $message->id;

        $message->update(['history_ciphertext' => null, 'message_bytes' => null]);
        $message->delete();

        broadcast(new MessageDeleted($messageId, $channel->id))->toOthers();

        return $this->noContentResponse();
    }
}
