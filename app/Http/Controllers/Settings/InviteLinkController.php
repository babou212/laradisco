<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Models\InviteLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InviteLinkController extends Controller
{
    /**
     * Display the invite links management page.
     */
    public function index(Request $request): Response
    {
        $this->authorizeInviteAccess($request);

        $inviteLinks = InviteLink::query()
            ->with(['creator:id,name,username', 'usedByUser:id,name,username'])
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('settings/InviteLinks', [
            'inviteLinks' => $inviteLinks,
        ]);
    }

    /**
     * Generate a new invite link.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInviteAccess($request);

        InviteLink::create([
            'token' => Str::random(64),
            'created_by' => $request->user()->id,
            'expires_at' => now()->addHour(),
        ]);

        return redirect()->route('invite-links.index');
    }

    /**
     * Delete an unused invite link.
     */
    public function destroy(Request $request, InviteLink $inviteLink): RedirectResponse
    {
        $this->authorizeInviteAccess($request);

        abort_if($inviteLink->used_at !== null, HttpResponse::HTTP_FORBIDDEN, 'Cannot delete a used invite link.');

        $inviteLink->delete();

        return redirect()->route('invite-links.index');
    }

    /**
     * Ensure the user has the InviteMembers permission.
     */
    private function authorizeInviteAccess(Request $request): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $request->user()->hasPermission(PermissionFlag::InviteMembers),
            HttpResponse::HTTP_FORBIDDEN,
            'You do not have permission to manage invite links.',
        );
    }
}
