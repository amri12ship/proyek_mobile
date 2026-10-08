<?php

namespace App\Resources\Api\V1;

use App\Models\LocationValidation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LocationValidation
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket' => $this->ticket,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'expires_in_seconds' => max(0, now()->diffInSeconds($this->expires_at, false)),
            'single_use' => true,
            'location' => $this->whenLoaded('location', fn () => $this->location === null ? null : [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'address' => $this->location->address,
                'latitude' => $this->location->latitude,
                'longitude' => $this->location->longitude,
                'radius' => $this->location->radius,
            ]),
        ];
    }
}
