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

    public function disabledEmployeeAccess(Request $request, string $employeeId)
    {
        return $this->handleRequest(function () use ($employeeId, $request) {

            $data = $this->punchLogService->disabledEmployeeAccess($employeeId, $request->all());

            return ApiResponse::success($data);
        });
    }

    public function enabledEmployeeAccess(Request $request, string $employeeId)
    {
        return $this->handleRequest(function () use ($employeeId, $request) {
            $data = $this->punchLogService->enabledEmployeeAccess($employeeId, $request->all());

            return ApiResponse::success($data);
        });
    }
}
