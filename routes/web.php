<?php

use App\Http\Controllers\Devices\ZKPushController;
use Illuminate\Support\Facades\Route;

Route::any('{path}', [ZKPushController::class, 'handle'])
    ->where('path', '.*');
