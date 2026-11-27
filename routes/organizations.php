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
        ->middleware('ensure.role:view_organization')
        ->name('organizations.show');

    Route::patch('/organizations/{organization:slug}', [OrganizationController::class, 'update'])
        ->middleware('ensure.role:update_organization')
        ->name('organizations.update');

    Route::delete('/organizations/{organization:slug}', [OrganizationController::class, 'destroy'])
        ->middleware('ensure.role:archive_organization')
        ->name('organizations.destroy');

    Route::post('/organizations/{organization:slug}/transfer', [OrganizationTransferController::class, 'store'])
        ->middleware('ensure.role:transfer_ownership')
        ->name('organizations.transfer');

    Route::get('/organizations/{organization:slug}/members', [MemberController::class, 'index'])
        ->middleware('ensure.role:view_organization')
        ->name('members.index');

    Route::patch('/organizations/{organization:slug}/members/{member}', [MemberController::class, 'update'])
        ->middleware('ensure.role:manage_members')
        ->name('members.update');

    Route::delete('/organizations/{organization:slug}/members/{member}', [MemberController::class, 'destroy'])
        ->middleware('ensure.role:manage_members')
        ->name('members.destroy');

    Route::post('/organizations/{organization:slug}/invitations', [InvitationController::class, 'store'])
        ->middleware('ensure.role:invite_members')
        ->name('invitations.store');

    Route::delete('/organizations/{organization:slug}/invitations/{invitation}', [InvitationController::class, 'destroy'])
        ->middleware('ensure.role:revoke_invitation')
        ->name('invitations.destroy');
});

// Acceptance is allowed for any authenticated user whose email matches.
Route::get('/invitations/{token}', [InvitationController::class, 'accept'])
    ->middleware('auth')
    ->name('invitations.accept');
