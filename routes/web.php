<?php

use App\Http\Controllers\BoardingPassController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\ExtraPlayersController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LegacyEndpointController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\ThanksController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

/*
| Paths match the legacy portal so existing links and bookmarks keep working.
*/

Route::get('/', [EntryController::class, 'show'])->middleware('throttle:portal-entry')->name('portal.entry');

Route::middleware('portal.internal')->group(function () {
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::post('/groups/{bookingId}', [GroupController::class, 'join'])->whereNumber('bookingId')->name('groups.join');
    Route::post('/logout', [EntryController::class, 'logout'])->name('portal.logout');
});

Route::middleware('portal')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/players', PlayerController::class)->name('players');
    Route::get('/rules', RulesController::class)->name('rules');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');
    Route::get('/thanks', ThanksController::class)->name('thanks');
});

Route::middleware('portal:registration')->group(function () {
    Route::get('/verify', [VerificationController::class, 'show'])->name('verify');
    Route::post('/verify', [VerificationController::class, 'store'])->name('verify.store');
    Route::get('/confirmation', [BoardingPassController::class, 'show'])->name('confirmation');
    Route::get('/qr', [BoardingPassController::class, 'qr'])->name('qr');

    Route::get('/extraplayers', [ExtraPlayersController::class, 'create'])->name('extra-players');
    Route::post('/extraplayers', [ExtraPlayersController::class, 'store'])->name('extra-players.store');
    Route::get('/extraplayers/checkout', [ExtraPlayersController::class, 'checkout'])->name('extra-players.checkout');
    Route::post('/extraplayers/checkout', [ExtraPlayersController::class, 'pay'])->middleware('throttle:payments')->name('extra-players.pay');
});

Route::get('/forms/public/regos/index.php', LegacyEndpointController::class)->name('legacy.endpoint');
