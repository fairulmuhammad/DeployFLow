<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Identity\UpdateProfile;
use App\Application\DTOs\Identity\UpdateProfileData;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/Profile', [
            // `User` implements `MustVerifyEmail`, so every dashboard account is
            // subject to the verification gate (docs/SECURITY.md §4).
            'mustVerifyEmail' => true,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $emailChanged = $user->email !== $request->string('email')->toString();

        $updateProfile->execute($user, new UpdateProfileData(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->filled('password')
                ? $request->string('password')->toString()
                : null,
        ));

        if ($emailChanged) {
            return redirect()
                ->route('verification.notice')
                ->with('status', 'Profile updated. Verify your new email address to continue.');
        }

        return back()->with('status', 'Profile updated.');
    }
}
