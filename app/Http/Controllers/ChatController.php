<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\PermissionFlag;
use App\Models\Category;
use App\Models\Channel;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $accessibleChannelIds = $this->permissionService
            ->getAccessibleChannels($user)
            ->pluck('id')
            ->all();

        $categories = Category::with(['channels' => function ($query) use ($accessibleChannelIds) {
            $query->whereIn('id', $accessibleChannelIds)
                ->orderBy('position');
        }])
            ->orderBy('position')
            ->get()
            ->filter(fn (Category $category) => $category->channels->isNotEmpty());

        // Check if viewing specific channel
        $channelId = $request->query('channel');
        $channel = null;

        if ($channelId) {
            $channel = Channel::find($channelId);

            // Ensure the user can view this channel
            if ($channel && ! in_array($channel->id, $accessibleChannelIds, true)) {
                $channel = null;
            }
        }

        if (! $channel) {
            $channel = Channel::whereIn('id', $accessibleChannelIds)
                ->orderBy('position')
                ->first();
        }

        $channelData = null;
        $messages = null;

        if ($channel) {
            $channelData = [
                'id' => $channel->id,
                'name' => $channel->name,
                'topic' => $channel->topic,
                'is_private' => $channel->is_private,
                'permissions' => [
                    'canSendMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::SendMessages),
                    'canManageMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::ManageMessages),
                    'canPinMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::PinMessages),
                    'canAddReactions' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::AddReactions),
                    'canAttachFiles' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::AttachFiles),
                    'canMentionEveryone' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::MentionEveryone),
                ],
            ];

            $messages = Inertia::scroll(fn () => $channel->messages()
                ->with(['user', 'attachments', 'reactions', 'replyTo.user'])
                ->orderBy('created_at', 'asc')
                ->cursorPaginate(50)
            );
        }

        $directMessages = $request->user()
            ?->directMessageGroups()
            ->with(['participants', 'lastMessage'])
            ->orderByDesc('updated_at')
            ->get() ?? [];

        // Gather current voice channel participants from cache
        $voiceParticipants = [];
        foreach ($categories as $category) {
            foreach ($category->channels as $ch) {
                if ($ch->type === ChannelType::Voice) {
                    $cached = Cache::get("voice_channel:{$ch->id}:participants", []);
                    $voiceParticipants[$ch->id] = array_values($cached);
                }
            }
        }

        return Inertia::render('Chat', [
            'categories' => $categories,
            'currentChannel' => $channelData,
            'messages' => $messages,
            'directMessages' => $directMessages,
            'voiceParticipants' => $voiceParticipants,
        ]);
    }
}
