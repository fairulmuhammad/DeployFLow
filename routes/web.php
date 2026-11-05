<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'laravelVersion' => app()->version(),
    'phpVersion' => PHP_VERSION,
]))->name('home');

// Infrastructure readiness (database + Redis). Liveness stays on the framework's `/up` route.
Route::get('/healthz', HealthController::class)->name('healthz');
