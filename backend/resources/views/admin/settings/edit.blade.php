@extends('layouts.app')

@section('title', 'Pengaturan')
@section('heading', 'Pengaturan Absensi')

@section('content')
    <div class="card">
        <h2>Jam Kerja & Toleransi</h2>

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="grid cols-3">
                <div class="field">
                    <label for="work_start">Jam Masuk</label>
                    <input id="work_start" type="time" name="work_start"
                           value="{{ old('work_start', substr((string) $setting->work_start, 0, 5)) }}" required>
                </div>
                <div class="field">
                    <label for="work_end">Jam Pulang</label>
                    <input id="work_end" type="time" name="work_end"
                           value="{{ old('work_end', substr((string) $setting->work_end, 0, 5)) }}" required>
                </div>
                <div class="field">
                    <label for="tolerance_minutes">Toleransi Terlambat (menit)</label>
                    <input id="tolerance_minutes" type="number" name="tolerance_minutes" min="0" max="240"
                           value="{{ old('tolerance_minutes', $setting->tolerance_minutes) }}" required>
                </div>
            </div>

            <h3 style="margin-top:10px">Validasi Lokasi</h3>
            <div class="grid cols-3">
                <div class="field">
                    <label for="radius_meter">Radius Default (meter)</label>
                    <input id="radius_meter" type="number" name="radius_meter"
                           min="{{ \App\Models\AttendanceLocation::MIN_RADIUS_METERS }}"
                           max="{{ \App\Models\AttendanceLocation::MAX_RADIUS_METERS }}"
                           value="{{ old('radius_meter', $setting->radius_meter) }}" required>
                    <p class="hint">Nilai ini hanya acuan. Radius yang benar-benar dipakai saat cek GPS adalah radius pada masing-masing lokasi.</p>
                </div>
                <div class="field">
                    <label for="max_accuracy_meter">Maks Akurasi GPS (meter)</label>
                    <input id="max_accuracy_meter" type="number" step="0.1" name="max_accuracy_meter" min="1" max="1000"
                           value="{{ old('max_accuracy_meter', $setting->max_accuracy_meter) }}" required>
                </div>
                <div class="field">
                    <label for="timezone">Zona Waktu</label>
                    <input id="timezone" type="text" name="timezone" value="{{ old('timezone', $setting->timezone) }}" required>
                </div>
            </div>

            <h3 style="margin-top:10px">Ticket QR</h3>
            <div class="grid cols-2">
                <div class="field">
                    <label for="ticket_ttl_seconds">Masa Berlaku Ticket (detik)</label>
                    <input id="ticket_ttl_seconds" type="number" name="ticket_ttl_seconds" min="30" max="900"
                           value="{{ old('ticket_ttl_seconds', $setting->ticket_ttl_seconds) }}" required>
                </div>
                <div class="field">
                    <label for="max_ticket_per_day">Maks Ticket per Hari</label>
                    <input id="max_ticket_per_day" type="number" name="max_ticket_per_day" min="1" max="100"
                           value="{{ old('max_ticket_per_day', $setting->max_ticket_per_day) }}" required>
                </div>
            </div>

            <div class="field checkbox">
                <input id="require_selfie" type="checkbox" name="require_selfie" value="1"
                       @checked(old('require_selfie', $setting->require_selfie))>
                <label for="require_selfie">Wajibkan selfie saat check-in dan check-out</label>
            </div>

            <button type="submit" class="btn">Simpan Pengaturan</button>
        </form>
    </div>
@endsection
