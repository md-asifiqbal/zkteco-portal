<?php

use App\Http\Controllers\Api\V1\AccessControls\PunchLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('tenant')->group(function () {
    Route::get('get-punch-logs/{deviceId?}', [PunchLogController::class, 'getLogs']);
    Route::post('access-controls/{employeeId}/disabled', [PunchLogController::class, 'disabledEmployeeAccess']);
    Route::post('access-controls/{employeeId}/enabled', [PunchLogController::class, 'enabledEmployeeAccess']);
});
