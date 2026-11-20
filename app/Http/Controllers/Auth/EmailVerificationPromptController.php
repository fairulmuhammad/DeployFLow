<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]);
    }
}
