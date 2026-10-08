<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\QrValidationController;
use App\Http\Controllers\Api\V1\SelfieController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Stateless JSON API consumed by the Flutter mobile app. Every endpoint
| below /api/v1 except login requires `Authorization: Bearer <token>`
| issued by POST /api/v1/auth/login (Laravel Sanctum).
|
*/

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('api.v1.auth.login');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        Route::middleware('role:employee')->group(function (): void {
            Route::get('locations', [QrValidationController::class, 'locations'])->name('api.v1.locations.index');
            Route::post('qr/validate', [QrValidationController::class, 'store'])->name('api.v1.qr.validate');

            Route::get('attendance/today', [AttendanceController::class, 'today'])->name('api.v1.attendance.today');
            Route::post('attendance/selfie', [SelfieController::class, 'store'])->name('api.v1.attendance.selfie');
            Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('api.v1.attendance.check-in');
            Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->name('api.v1.attendance.check-out');
            Route::get('attendance/history', [AttendanceController::class, 'history'])->name('api.v1.attendance.history');

            Route::get('device', [DeviceController::class, 'index'])->name('api.v1.device.index');
            Route::post('device', [DeviceController::class, 'store'])->name('api.v1.device.store');
        });
    });
});
