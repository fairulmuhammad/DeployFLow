<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn (): ?array => $request->user()?->only([
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                ]),
            ],
            'status' => fn (): ?string => $request->session()->get('status'),
            'organizations' => fn (): array => $request->user() === null
                ? []
                : $this->organizationList($request->user()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function organizationList(User $user): array
    {
        return $user->organizations()
            ->orderBy('organizations.name')
            ->get()
            ->map(function (Organization $organization): array {
                $pivot = $organization->getRelation('pivot');

                return [
                    'id' => $organization->getKey(),
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'role' => $pivot instanceof Pivot
                        ? (string) $pivot->getAttribute('role')
                        : null,
                ];
            })
            ->all();
    }
}
