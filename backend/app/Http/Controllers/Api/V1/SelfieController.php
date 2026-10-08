<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UploadSelfieRequest;
use App\Models\Employee;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SelfieController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * POST /api/v1/attendance/selfie
     *
     * Stores the selfie on the private disk and returns the opaque path that
     * the mobile app sends back as `selfie` on check-in / check-out.
     */
    public function store(UploadSelfieRequest $request): JsonResponse
    {
        $employee = $this->employee($request);
        $file = $request->file('selfie');
        $directory = self::directoryFor($employee);
        $path = $file->store($directory, 'local');

        $this->audit->success('attendance.selfie', $employee->user, $employee, "Selfie diunggah: {$path}");

        return response()->json([
            'message' => 'Selfie berhasil diunggah.',
            'data' => [
                'path' => $path,
                'size' => Storage::disk('local')->size($path),
                'mime_type' => $file->getMimeType(),
                'uploaded_at' => now()->toIso8601String(),
            ],
        ], 201);
    }

    public static function directoryFor(Employee $employee): string
    {
        return 'selfies/'.$employee->user_id;
    }

    /**
     * A selfie path may only be attached by the employee who owns it.
     */
    public static function ownsPath(Employee $employee, mixed $path): bool
    {
        return is_string($path)
            && Str::startsWith(ltrim($path, '/'), self::directoryFor($employee).'/');
    }
}
