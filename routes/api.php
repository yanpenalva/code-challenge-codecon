<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/', fn() => response()->json([
        'message' => 'Challenge Codecon API 100K Users API Online'
    ]));

    Route::controller(UserController::class)->prefix('users')
        ->group(function () {
            Route::get('check', 'check');
            Route::get('superusers', 'superusers');
            Route::get('topCountries', 'topCountries');
            Route::get('teamInsights', 'teamInsights');
            Route::get('activeUsersPerDay', 'activeUsersPerDay');
            Route::get('evaluation', 'evaluation');
        });
});
