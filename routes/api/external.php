<?php

use App\Http\Controllers\Api\V1\AccessControls\PunchLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('tenant')->group(function () {

    Route::get('get-punch-logs/{deviceId?}', [PunchLogController::class, 'getLogs']);

});
