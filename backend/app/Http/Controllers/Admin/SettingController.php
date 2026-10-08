<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Models\AttendanceSetting;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(): View
    {
        return view('admin.settings.edit', ['setting' => AttendanceSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i', 'after:work_start'],
            'tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'radius_meter' => ['required', 'integer', 'min:'.AttendanceLocation::MIN_RADIUS_METERS, 'max:'.AttendanceLocation::MAX_RADIUS_METERS],
            'max_accuracy_meter' => ['required', 'numeric', 'min:1', 'max:1000'],
            'ticket_ttl_seconds' => ['required', 'integer', 'min:30', 'max:900'],
            'max_ticket_per_day' => ['required', 'integer', 'min:1', 'max:100'],
            'require_selfie' => ['nullable', 'boolean'],
            'timezone' => ['required', 'string', 'max:60', 'timezone'],
        ]);

        $setting = AttendanceSetting::current();
        $setting->fill([
            ...$data,
            'work_start' => $data['work_start'].':00',
            'work_end' => $data['work_end'].':00',
            'require_selfie' => $request->boolean('require_selfie'),
        ])->save();

        $this->audit->success('settings.update', $request->user(), null, 'Pengaturan absensi diperbarui');

        return back()->with('success', 'Pengaturan absensi disimpan.');
    }
}
