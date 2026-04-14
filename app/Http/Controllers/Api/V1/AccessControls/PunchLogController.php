<?php

namespace App\Http\Controllers\Api\V1\AccessControls;

use App\Http\Controllers\Controller;
use App\Services\Modules\AccessControls\PunchLogService;
use Illuminate\Http\Request;

class PunchLogController extends Controller
{
    public function __construct(protected PunchLogService $punchLogService) {}

    public function getLogs(Request $request, ?string $deviceId = null)
    {

    }
}
