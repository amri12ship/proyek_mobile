<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:web-login');
});

Route::post('logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'employee'])->name('dashboard');
    Route::get('profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');

        Route::resource('employees', EmployeeController::class)->except(['show']);
        Route::post('employees/{employee}/toggle', [EmployeeController::class, 'toggle'])->name('employees.toggle');

        Route::resource('locations', LocationController::class)->except(['show']);
        Route::get('locations/{location}/qr', [LocationController::class, 'qr'])->name('locations.qr');
        Route::get('locations/{location}/qr/image', [LocationController::class, 'qrImage'])->name('locations.qr.image');
        Route::post('locations/{location}/rotate-token', [LocationController::class, 'rotateToken'])->name('locations.rotate');

        Route::get('absensi', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('absensi/{record}', [AttendanceController::class, 'show'])->name('attendance.show');
        Route::get('absensi/{record}/selfie/{type}', [AttendanceController::class, 'selfie'])
            ->whereIn('type', ['check-in', 'check-out'])
            ->name('attendance.selfie');
        Route::put('absensi/{record}/status', [AttendanceController::class, 'updateStatus'])->name('attendance.status');

        Route::get('jadwal', [ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('jadwal', [ScheduleController::class, 'store'])->name('schedule.store');
        Route::delete('jadwal/{schedule}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');

        Route::get('libur', [HolidayController::class, 'index'])->name('holiday.index');
        Route::post('libur', [HolidayController::class, 'store'])->name('holiday.store');
        Route::delete('libur/{holiday}', [HolidayController::class, 'destroy'])->name('holiday.destroy');

        Route::get('laporan', [ReportController::class, 'index'])->name('report.index');
        Route::get('laporan/export', [ReportController::class, 'export'])->name('report.export');

        Route::get('pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});
