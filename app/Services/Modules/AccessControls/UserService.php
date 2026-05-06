<?php

namespace App\Services\Modules\AccessControls;

use App\Models\Device;
use App\Services\ZKTeco\ZKTecoFactory;

class UserService
{
    protected $factory;

    public function __construct(ZKTecoFactory $factory)
    {
        $this->factory = $factory;
    }

    public function getUsers(array $filters = [])
    {
        $devices = Device::where('tenant_id', tenant_id())->get();
        $results = [];
        foreach ($devices as $device) {
            try {
                $service = $this->factory->make($device);
                $data = $service->syncUsers();
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

    public function createUser(array $attributes = [])
    {
        $device = Device::where('tenant_id', tenant_id())->where('id', $attributes['device_id'])->firstOrFail();

        try {
            $service = $this->factory->make($device);
            $data = $service->createUser($attributes['user_id'], $attributes['name'], $attributes['role'] ?? 0);
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

        return $results;
    }

    public function disabledUser(string $employeeId, array $filters = [])
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

    public function enabledUser(string $employeeId, array $filters = [])
    {
        // Implement logic to enable employee access
        // This could involve updating a database record or calling an external API
        // For example:
        // Employee::where('id', $employeeId)->update(['access_disabled' => false]);
    }
}
