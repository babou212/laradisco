<?php

namespace App\Services;

use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permanently removes a user account from the server while preserving the
 * content they authored.
 *
 * The user row (and all PII: email, password, tokens, avatar, 2FA) is deleted,
 * but their channel messages and direct messages survive — their author link is
 * nulled (ON DELETE SET NULL) and their username is snapshotted onto each row as
 * `deleted_author_name` so the client can render "<username> (deleted)".
 */
class UserDeletionService
{
    /**
     * Hard-delete a user, retaining their messages as deleted-author tombstones.
     */
    public function delete(User $user): void
    {
        $username = $user->username;

        DB::transaction(function () use ($user, $username) {
            // Snapshot the author name onto every message (including soft-deleted
            // ones) so the tombstone label survives the user row being removed.
            Message::withTrashed()
                ->where('user_id', $user->id)
                ->update(['deleted_author_name' => $username]);

            DirectMessage::withTrashed()
                ->where('user_id', $user->id)
                ->update(['deleted_author_name' => $username]);

            // DM groups owned by this user would cascade-delete (taking everyone
            // else's messages with them). Hand ownership to another participant;
            // a solo group has no one left, so we let it cascade away.
            $this->reassignOwnedDirectMessageGroups($user);

            // Purge PII and access.
            $user->tokens()->delete();
            $user->clearMediaCollection('avatar');
            $user->syncRoles([]);

            // Remove the account. Remaining FKs (reactions, mentions, channel and
            // DM-group memberships, thread followers, inbox) resolve via ON DELETE
            // SET NULL / CASCADE as defined in their migrations.
            $user->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::tags([CacheKeys::userTag($user->id)])->flush();
    }

    /**
     * Transfer ownership of any DM groups owned by the user to another
     * participant so the group (and its messages) outlives the deletion.
     */
    private function reassignOwnedDirectMessageGroups(User $user): void
    {
        DirectMessageGroup::where('owner_id', $user->id)
            ->with('participants:id')
            ->get()
            ->each(function (DirectMessageGroup $group) use ($user) {
                $newOwner = $group->participants->firstWhere('id', '!=', $user->id);

                if ($newOwner !== null) {
                    $group->update(['owner_id' => $newOwner->id]);
                }
            });
    }
}
