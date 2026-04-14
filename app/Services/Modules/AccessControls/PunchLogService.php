<?php

namespace App\Services\Modules\AccessControls;

use App\Models\PunchLog;
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

        $query = $this->model::query()->where('tenant_id', tenant_id());

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 10);
    }
}
