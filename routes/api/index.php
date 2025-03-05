<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\OutletController;
use App\Http\Controllers\Api\V1\SiteController;
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

        Route::controller(OutletController::class)->group(function () {
            Route::prefix('outlet')->group(function () {
                Route::get('dropdown-pt', 'dropdown');
                Route::get('locations', 'listOutletLocation');
                Route::get('pt', 'listKecamatanByMc');
                Route::get('list/{pt}/{kecamatan}', 'listOutletByPartnerName');

                Route::prefix('detail')->group(function () {
                    Route::get('{qr_code}', 'outletDetail');
                    Route::get('{qr_code}/ga', 'outletDetailGa');
                    Route::get('{qr_code}/sec', 'outletDetailSec');
                    Route::get('{qr_code}/supply', 'outletDetailSupply');
                    Route::get('{qr_code}/demand', 'outletDetailDemand');
                });
            });

            Route::controller(SiteController::class)->group(function () {
                Route::prefix('sites')->group(function () {
                    Route::get('pt/{mc_id}', 'listKecamatanByMc');
                    Route::get('/', 'listSiteByKecamatan');
                    Route::get('/dashboard', 'getSiteDashboard');
                    Route::get('{site_id}/detail', 'siteDetail');
                    Route::get('{site_id}/detail/revenue', 'siteDetailRevenue');
                    Route::get('{site_id}/detail/rgu', 'siteDetailRgu');
                    Route::get('{site_id}/detail/ga', 'siteDetailGa');
                    Route::get('{site_id}/detail/vlr', 'siteDetailVlr');
                    Route::get('{site_id}/detail/outlet', 'siteDetailOutlet');
                });
            });
        });


    });
});
