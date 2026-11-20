<?php

declare(strict_types=1);

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationTransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->name('organizations.index');

    Route::post('/organizations', [OrganizationController::class, 'store'])
        ->name('organizations.store');

    Route::get('/organizations/{organization:slug}', [OrganizationController::class, 'show'])
        ->name('organizations.show');

    Route::patch('/organizations/{organization:slug}', [OrganizationController::class, 'update'])
        ->name('organizations.update');

    Route::delete('/organizations/{organization:slug}', [OrganizationController::class, 'destroy'])
        ->name('organizations.destroy');

    Route::post('/organizations/{organization:slug}/transfer', [OrganizationTransferController::class, 'store'])
        ->name('organizations.transfer');

    Route::get('/organizations/{organization:slug}/members', [MemberController::class, 'index'])
        ->name('members.index');

    Route::patch('/organizations/{organization:slug}/members/{member}', [MemberController::class, 'update'])
        ->name('members.update');

    Route::delete('/organizations/{organization:slug}/members/{member}', [MemberController::class, 'destroy'])
        ->name('members.destroy');

    Route::post('/organizations/{organization:slug}/invitations', [InvitationController::class, 'store'])
        ->name('invitations.store');

    Route::delete('/organizations/{organization:slug}/invitations/{invitation}', [InvitationController::class, 'destroy'])
        ->name('invitations.destroy');
});

// Acceptance is allowed for any authenticated user whose email matches.
Route::get('/invitations/{token}', [InvitationController::class, 'accept'])
    ->middleware('auth')
    ->name('invitations.accept');
