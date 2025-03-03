<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('login', 'login');
        Route::post('logout', 'logout')->middleware('jwt.auth');
    });

    Route::middleware('jwt.auth')->group(function () {
        Route::controller(DashboardController::class)->group(function () {
            Route::prefix('dashboard')->group(function () {
                Route::get('parameter', 'dashboard');
                Route::get('account', 'account');
                Route::get('insentif', 'insentif');
                Route::get('profile', 'profile');
                Route::get('sliders', 'sliders');
            });
        });


    });
});
