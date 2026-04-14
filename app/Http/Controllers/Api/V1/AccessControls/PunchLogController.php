<?php

namespace App\Http\Controllers\Api\V1\AccessControls;

use App\Http\Controllers\Controller;
use App\Http\Resources\Modules\AccessControls\PunchLogs\PunchLogResource;
use App\Services\Applications\Api\ApiResponse;
use App\Services\Modules\AccessControls\PunchLogService;
use Illuminate\Http\Request;

class PunchLogController extends Controller
{
    public function __construct(protected PunchLogService $punchLogService) {}

    public function getLogs(Request $request, ?string $deviceId = null)
    {
        return $this->handleRequest(function () use ($request, $deviceId) {
            $data = $this->punchLogService->getLogs($deviceId, $request->all());

            return ApiResponse::success(PunchLogResource::collection($data));
        });
    }
}
