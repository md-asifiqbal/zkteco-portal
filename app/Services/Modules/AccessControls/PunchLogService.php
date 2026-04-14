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

        $query = $this->model::query()->with('device')->where('tenant_id', tenant_id());

        $query->when(isset($filters['user_id']), function ($q) use ($filters) {
            $q->where('user_id', $filters['user_id']);
        })->when(isset($filters['start_date']) && isset($filters['end_date']), function ($q) use ($filters) {
            $q->whereBetween('timestamp', [$filters['start_date'], $filters['end_date']]);
        })->when(isset($filters['source']), function ($q) use ($filters) {
            $q->where('source', $filters['source']);
        });

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 10);
    }
}
