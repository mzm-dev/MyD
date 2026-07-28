<?php

use Illuminate\Support\Facades\Route;
use Mzm\MyDigitalId\Http\Controllers\MyDigitalIdController;

// Kumpulan Route untuk MyDigital ID
Route::prefix('mydigitalid') // Semua URL akan bermula dengan /mydigitalid/...
    ->name('mydigitalid.') // Semua nama route bermula dengan mydigitalid.login, dsb.
    ->controller(MyDigitalIdController::class) // Tetapkan Controller utama
    ->group(function () {

        // --- ZON TETAMU (GUEST) ---
        // Hanya boleh diakses jika pengguna BELUM login
        Route::middleware(['guest'])->group(function () {

            Route::get('/notfound', function () {
                if ($route = config('mydigitalid.notfound_route')) {
                    return redirect()->route($route);
                }
                if ($message = config('mydigitalid.notfound_message')) {
                    return abort('403', $message);
                }

                return abort('404', 'icnumber notfound');
            })->name('notfound');

            Route::get('/link', function () {
                return redirect()->route('login');
            })->name('link');

            // 1. Mula Login: Redirect ke MIMOS
            // URL: /mydigitalid/login
            // Usage: route('mydigitalid.login')
            Route::get('/login', 'redirectToKeycloak')->name('login');

            // 2. Callback: Terima data dari MIMOS
            // URL: /mydigitalid/callback
            // Usage: route('mydigitalid.callback')
            Route::get('/callback', 'handleKeycloakCallback')->name('callback');
        });

        // --- ZON PENGGUNA (AUTH) ---
        // Hanya boleh diakses jika pengguna SUDAH login
        Route::middleware(['auth'])->group(function () {

            Route::get('/home', function () {
                return redirect()->route(config('mydigitalid.home_route', 'home'));
            })->name('home');

            // 3. Logout: Keluar sistem & Redirect ke Logout MIMOS
            // URL: /mydigitalid/logout
            // Usage: route('mydigitalid.logout')
            Route::post('/logout', 'logout')->name('logout');
        });

    });
// End My DigitalId
