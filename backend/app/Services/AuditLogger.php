<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(
        string $action,
        ?User $user = null,
        ?Employee $employee = null,
        ?string $description = null,
        string $status = AuditLog::STATUS_SUCCESS,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'user_id' => $user?->id,
            'employee_id' => $employee?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
            'device_info' => substr((string) $request->userAgent(), 0, 255) ?: null,
            'status' => $status,
        ]);
    }

    public function success(string $action, ?User $user = null, ?Employee $employee = null, ?string $description = null): AuditLog
    {
        return $this->log($action, $user, $employee, $description, AuditLog::STATUS_SUCCESS);
    }

    public function failed(string $action, ?User $user = null, ?Employee $employee = null, ?string $description = null): AuditLog
    {
        return $this->log($action, $user, $employee, $description, AuditLog::STATUS_FAILED);
    }
}
