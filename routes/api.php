<?php

use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::controller(PaymentController::class)->prefix('payments')->name('payments.')->group(function () {
        Route::post('authorize', 'authorizePayment')->name('authorize');
    });
});
