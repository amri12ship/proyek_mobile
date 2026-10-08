<?php

namespace App\Resources\Api\V1;

use App\Models\AttendanceLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Location payload for the mobile app.
 *
 * The QR secret (`public_token`) is deliberately never exposed here. The mobile
 * app only receives the plain QR payload when an admin generates it on the web
 * panel, so a leaked API response can never be used to forge a check-in.
 *
 * @mixin AttendanceLocation
 */
class LocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius' => $this->radius,
            'status' => $this->status,
        ];
    }
}
