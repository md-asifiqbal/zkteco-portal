<?php

use App\Http\Controllers\Api\V1\AccessControls\PunchLogController;
use App\Http\Controllers\Api\V1\AccessControls\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('tenant')->group(function () {
    Route::get('get-punch-logs/{deviceId?}', [PunchLogController::class, 'getLogs']);

    Route::prefix('access-controls')->group(function () {
        Route::post('{employeeId}/disabled', [UserController::class, 'disabledUser']);
        Route::post('{employeeId}/enabled', [UserController::class, 'enabledUser']);
        Route::post('create-user', [UserController::class, 'createUser']);
        Route::get('users', [UserController::class, 'index']);
    });

});
