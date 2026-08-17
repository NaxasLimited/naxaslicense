<?php

use App\Http\Controllers\Api\ActivationRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('activation.transport')->group(function () {
    Route::post('activation-requests', [ActivationRequestController::class, 'store'])->middleware('throttle:activation.create');
    Route::post('activation-requests/{request_id}/status', [ActivationRequestController::class, 'status'])->middleware('throttle:activation.status');
    Route::post('activation-requests/{request_id}/acknowledge', [ActivationRequestController::class, 'acknowledge'])->middleware('throttle:activation.status');
});
