<?php

namespace App\Http\Resources\Modules\AccessControls\PunchLogs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PunchLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'deviceId' => $this->device_id,
            'userId' => $this->user_id,
            'timestamp' => $this->timestamp,
            'status' => $this->status,
            'verifyType' => $this->verify_type,
            'stamp' => $this->stamp,
            'source' => $this->source === 1 ? 'Push' : 'Pull',
            'device' => $this->whenLoaded('device', function () {
                return [
                    'id' => $this->device?->id,
                    'name' => $this->device?->name,
                    'model' => $this->device?->model,
                    'ip' => $this->device?->ip_address,
                ];
            }),
        ];
    }
}
