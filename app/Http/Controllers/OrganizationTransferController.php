<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTOs\Organizations\TransferOwnershipData;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Actions\OrganizationActions;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OrganizationTransferController extends Controller
{
    public function __construct(
        private readonly OrganizationActions $actions,
    ) {}

    /**
     * `POST /organizations/{organization:slug}/transfer` — transfers the
     * owner role to the referenced member.
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('transferOwnership', $organization);

        $validated = $request->validate([
            'member_id' => [
                'required',
                'uuid',
                Rule::exists('organization_members', 'id')
                    ->where('organization_id', $organization->getKey())
                    ->whereNot('role', MemberRole::Owner->value),
            ],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $member = $organization->members()->where('id', $validated['member_id'])->first();

        if ($member === null) {
            abort(404);
        }

        $target = $member->user;

        if ($target === null) {
            return back()->with('status', 'Member has no user account.');
        }

        $this->actions->transferOwnership(
            $organization,
            new TransferOwnershipData(newOwner: $target),
            $actor,
            $request,
        );

        return back()->with('status', 'Ownership transferred to '.$target->name.'.');
    }
}
