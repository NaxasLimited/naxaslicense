<?php

use App\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::middleware('auth')->group(function (): void {
    Route::get('/settings/license', [LicenseController::class, 'show'])->name('license.show');
    Route::post('/settings/license/request', [LicenseController::class, 'create'])->middleware('throttle:5,60')->name('license.create');
    Route::post('/settings/license/status', [LicenseController::class, 'poll'])->middleware('throttle:20,60')->name('license.poll');
});
