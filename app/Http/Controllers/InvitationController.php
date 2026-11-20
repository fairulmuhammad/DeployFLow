<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Actions\OrganizationActions;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;
use App\Infrastructure\Organizations\InvitationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class InvitationController extends Controller
{
    public function __construct(
        private readonly OrganizationActions $actions,
    ) {}

    /**
     * `GET /invitations/{token}` — accepts an invitation by raw token.
     */
    public function accept(Request $request, string $token): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $invitation = OrganizationInvitation::query()
            ->where('token_hash', InvitationToken::hash($token))
            ->first();

        if ($invitation === null) {
            return Inertia::render('Organizations/Invitation', [
                'reason' => 'unknown',
                'organizationSlug' => null,
            ]);
        }

        $organization = $invitation->organization;

        if ($organization === null) {
            return Inertia::render('Organizations/Invitation', [
                'reason' => 'unknown',
                'organizationSlug' => null,
            ]);
        }

        if ($invitation->accepted_at !== null) {
            return Inertia::render('Organizations/Invitation', [
                'reason' => 'already-accepted',
                'organizationSlug' => $organization->slug,
            ]);
        }

        if ($invitation->isExpired()) {
            return Inertia::render('Organizations/Invitation', [
                'reason' => 'expired',
                'organizationSlug' => $organization->slug,
            ]);
        }

        if (strcasecmp($user->email, $invitation->email) !== 0) {
            return Inertia::render('Organizations/Invitation', [
                'reason' => 'email-mismatch',
                'organizationSlug' => $organization->slug,
            ]);
        }

        $this->actions->acceptInvitation($invitation, $user, $token, $request);

        return redirect()
            ->route('organizations.show', ['organization' => $organization->slug])
            ->with('status', 'Invitation accepted. You are now a member of '.$organization->name.'.');
    }

    /**
     * `POST /organizations/{organization:slug}/invitations`
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('invite', $organization);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'role' => ['required', Rule::enum(MemberRole::class), 'not_in:owner'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $invitation = $this->actions->invite(
            $organization,
            $validated['email'],
            MemberRole::from($validated['role']),
            $user,
            $request,
        );

        return back()->with('status', 'Invitation created for '.$invitation->email.'.');
    }

    /**
     * `DELETE /organizations/{organization:slug}/invitations/{invitation}`
     */
    public function destroy(Request $request, Organization $organization, string $invitationId): RedirectResponse
    {
        $invitation = $organization->invitations()->findOrFail($invitationId);

        $this->authorize('revokeInvitation', $invitation);

        /** @var User $user */
        $user = $request->user();

        $this->actions->revokeInvitation($invitation, $user, $request);

        return back()->with('status', 'Invitation revoked.');
    }
}
