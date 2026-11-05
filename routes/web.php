<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'laravelVersion' => app()->version(),
    'phpVersion' => PHP_VERSION,
]))->name('home');
