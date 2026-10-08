<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends ApiController
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * POST /api/v1/device — register or refresh this phone for the employee.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:190'],
            'platform' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        $device = $this->attendance->registerDevice($this->employee($request), $validated);

        return response()->json([
            'message' => 'Perangkat terdaftar.',
            'data' => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
                'registered_at' => $device->registered_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/v1/device — devices currently registered by the employee.
     */
    public function index(Request $request): JsonResponse
    {
        $devices = $this->employee($request)->devices()->orderByDesc('last_used_at')->get();

        return response()->json([
            'data' => $devices->map(static fn ($device) => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
                'status' => $device->status,
                'last_used_at' => $device->last_used_at?->toIso8601String(),
            ]),
        ]);
    }
}
