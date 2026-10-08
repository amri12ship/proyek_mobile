<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AttendanceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'ATTENDANCE_ERROR',
        public readonly int $status = 422,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function make(string $message, string $code, int $status = 422, array $context = []): self
    {
        return new self($message, $code, $status, $context);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'context' => (object) $this->context,
        ], $this->status);
    }
}
