<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /**
     * The employee profile that owns the current token.
     */
    protected function employee(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user === null
            ? null
            : Employee::query()->where('user_id', $user->id)->first();

        if ($employee === null) {
            throw AttendanceException::make(
                'Akun ini tidak terhubung dengan data karyawan.',
                'EMPLOYEE_PROFILE_MISSING',
                403,
            );
        }

        return $employee;
    }

    protected function user(Request $request): User
    {
        return $request->user();
    }
}
