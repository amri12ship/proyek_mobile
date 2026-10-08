<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LocationIndexRequest;
use App\Http\Requests\Api\V1\ValidateQrRequest;
use App\Resources\Api\V1\TicketResource;
use App\Services\AttendanceService;
use App\Services\LocationValidationService;
use Illuminate\Http\JsonResponse;

class QrValidationController extends ApiController
{
    public function __construct(
        private readonly LocationValidationService $validations,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * POST /api/v1/qr/validate — exchange a scanned QR payload for a single-use ticket.
     */
    public function store(ValidateQrRequest $request): JsonResponse
    {
        $data = $request->validated();

        $validation = $this->validations->issue(
            $this->employee($request),
            $data['code'],
            $data,
        );

        return response()->json([
            'message' => 'Lokasi terverifikasi.',
            'data' => new TicketResource($validation),
        ], 201);
    }

    /**
     * GET /api/v1/locations — locations the employee may check in at.
     */
    public function locations(LocationIndexRequest $request): JsonResponse
    {
        $locations = $this->attendance->availableLocations(
            $this->employee($request),
            $request->validated(),
        );

        return response()->json(['data' => $locations]);
    }
}
