<?php

namespace App\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
            'phone' => $this->phone,
            'photo' => $this->photo,
            'employee' => $employee === null ? null : [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'photo' => $employee->photo,
                'status' => $employee->status,
            ],
        ];
    }
}
