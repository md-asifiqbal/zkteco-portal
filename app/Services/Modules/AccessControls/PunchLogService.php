<?php

namespace App\Services\Modules\AccessControls;

use App\Models\Device;
use App\Models\PunchLog;
use App\Services\ZKTeco\ZKTecoFactory;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class PunchLogService
{
    protected $model;

    public function __construct()
    {
        $this->model = PunchLog::class;
    }

    public function getLogs(?string $deviceId = null, array $filters = []): LengthAwarePaginator
    {

        $query = $this->model::query()->with('device')->where('tenant_id', tenant_id());

        $query->when(isset($filters['user_id']), function ($q) use ($filters) {
            $q->where('user_id', $filters['user_id']);
        })->when(isset($filters['start_date']) && isset($filters['end_date']), function ($q) use ($filters) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $q->whereBetween('timestamp', [$startDate, $endDate]);
        })->when(isset($filters['source']), function ($q) use ($filters) {
            $q->where('source', $filters['source']);
        })->when(isset($filters['search']), function ($q) use ($filters) {
            $q->where('user_id', 'like', "%{$filters['search']}%");
        });

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 10);
    }

    public function disabledEmployeeAccess(string $employeeId, array $filters = [])
    {
        $factory = app(ZKTecoFactory::class);
        $devices = Device::where('tenant_id', tenant_id())->get();
        $results = [];
        foreach ($devices as $device) {
            try {
                $service = $factory->make($device);
                $data = $service->disableEmployeeAccess($employeeId);
                $results[] = [
                    'device_id' => $device->ip_address,
                    'data' => $data,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'device_id' => $device->ip_address,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function enabledEmployeeAccess(string $employeeId, array $filters = [])
    {
        // Implement logic to enable employee access
        // This could involve updating a database record or calling an external API
        // For example:
        // Employee::where('id', $employeeId)->update(['access_disabled' => false]);
    }
}
