<?php

namespace App\Resources\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->attendance_date?->toDateString(),
            'status' => $this->status,
            'check_in' => $this->check_in_at?->toIso8601String(),
            'check_out' => $this->check_out_at?->toIso8601String(),
            'check_in_distance' => $this->check_in_distance,
            'check_in_accuracy' => $this->check_in_accuracy,
            'check_out_distance' => $this->check_out_distance,
            'check_out_accuracy' => $this->check_out_accuracy,
            'has_selfie' => $this->check_in_selfie !== null,
            'work_minutes' => $this->work_minutes,
            'notes' => $this->notes,
            'location' => $this->whenLoaded('location', fn () => $this->location === null ? null : [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'address' => $this->location->address,
            ]),
        ];
    }
}
