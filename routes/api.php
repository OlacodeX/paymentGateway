<?php

use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::controller(PaymentController::class)->prefix('payments')->name('payments.')->group(function () {
        Route::middleware(EnsureIdempotency::class)->group(function () {
            Route::post('authorize', 'authorizePayment')->name('authorize');
            Route::post('{payment}/capture', 'capture')->name('capture');
            Route::post('{payment}/void', 'void')->name('void');
            Route::post('{payment}/refund', 'refund')->name('refund');
        });
    });
});
