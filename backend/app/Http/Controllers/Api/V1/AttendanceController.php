<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CheckInRequest;
use App\Http\Requests\Api\V1\CheckOutRequest;
use App\Resources\Api\V1\AttendanceRecordResource;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends ApiController
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * GET /api/v1/attendance/today
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $snapshot = $this->attendance->today($employee);

        return response()->json([
            'data' => [
                'date' => $snapshot['date'],
                'server_time' => $snapshot['server_time'],
                'timezone' => $snapshot['timezone'],
                'status' => $snapshot['status'],
                'can_check_in' => $snapshot['can_check_in'],
                'can_check_out' => $snapshot['can_check_out'],
                'is_workday' => $snapshot['is_workday'],
                'is_holiday' => $snapshot['is_holiday'],
                'schedule' => $snapshot['schedule'],
                'work_hours' => $snapshot['work_hours'],
                'validation' => $snapshot['validation'],
                'record' => $snapshot['record'] === null
                    ? null
                    : new AttendanceRecordResource($snapshot['record']->load('location')),
                'locations' => $this->attendance->availableLocations($employee, $request->query()),
            ],
        ]);
    }

    /**
     * POST /api/v1/attendance/check-in
     */
    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $record = $this->attendance->checkIn($this->employee($request), $request->validated());

        return response()->json([
            'message' => 'Check-in berhasil dicatat.',
            'data' => new AttendanceRecordResource($record),
        ], 201);
    }

    /**
     * POST /api/v1/attendance/check-out
     */
    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $record = $this->attendance->checkOut($this->employee($request), $request->validated());

        return response()->json([
            'message' => 'Check-out berhasil dicatat.',
            'data' => new AttendanceRecordResource($record),
        ]);
    }

    /**
     * GET /api/v1/attendance/history
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $history = $this->attendance->history($this->employee($request), $validated);

        return response()->json([
            'data' => AttendanceRecordResource::collection($history['records']),
            'meta' => [
                'from' => $history['from'],
                'to' => $history['to'],
                'summary' => $history['summary'],
                'current_page' => $history['records']->currentPage(),
                'last_page' => $history['records']->lastPage(),
                'per_page' => $history['records']->perPage(),
                'total' => $history['records']->total(),
            ],
        ]);
    }
}
