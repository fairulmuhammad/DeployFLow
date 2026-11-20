<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'A fresh verification link has been sent.');
    }
}
