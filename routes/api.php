<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Memberships Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('web')
    ->prefix('api/memberships')
    ->name('memberships.api.')
    ->group(function (): void {
        // API routes will be added here
    });
