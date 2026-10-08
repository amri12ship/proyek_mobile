@extends('layouts.app')

@section('title', 'Jadwal Kerja')
@section('heading', 'Jadwal Kerja')

@section('content')
    <div class="grid cols-2">
        <div class="card">
            <h2>Jadwal Global (berlaku untuk semua karyawan)</h2>

            <form method="POST" action="{{ route('admin.schedule.store') }}">
                @csrf
                <div class="grid cols-2">
                    <div class="field">
                        <label for="day_of_week">Hari</label>
                        <select id="day_of_week" name="day_of_week" required>
                            @foreach ($days as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="is_workday">Hari Kerja</label>
                        <select id="is_workday" name="is_workday">
                            <option value="1">Ya</option>
                            <option value="0">Libur</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="start_time">Masuk</label>
                        <input id="start_time" type="time" name="start_time">
                    </div>
                    <div class="field">
                        <label for="end_time">Pulang</label>
                        <input id="end_time" type="time" name="end_time">
                    </div>
                </div>
                <input type="hidden" name="employee_id" value="">
                <button type="submit" class="btn">Simpan Jadwal Global</button>
            </form>

            <table style="margin-top:16px">
                <thead>
                <tr><th>Hari</th><th>Hari Kerja</th><th>Masuk</th><th>Pulang</th><th class="no-print">Aksi</th></tr>
                </thead>
                <tbody>
                @forelse ($globalSchedules as $schedule)
                    <tr>
                        <td>{{ $days[$schedule->day_of_week] ?? $schedule->day_of_week }}</td>
                        <td><span class="badge {{ $schedule->is_workday ? 'active' : 'inactive' }}">{{ $schedule->is_workday ? 'Kerja' : 'Libur' }}</span></td>
                        <td>{{ $schedule->start_time ? substr((string) $schedule->start_time, 0, 5) : '—' }}</td>
                        <td>{{ $schedule->end_time ? substr((string) $schedule->end_time, 0, 5) : '—' }}</td>
                        <td class="no-print">
                            <form method="POST" action="{{ route('admin.schedule.destroy', $schedule) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger small" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada jadwal global. Semua hari dianggap hari kerja.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Jadwal Pribadi</h2>

            <form method="POST" action="{{ route('admin.schedule.store') }}">
                @csrf
                <div class="field">
                    <label for="employee_id">Karyawan</label>
                    <select id="employee_id" name="employee_id" required>
                        <option value="">— Pilih karyawan —</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->nik }} — {{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid cols-2">
                    <div class="field">
                        <label for="p_day">Hari</label>
                        <select id="p_day" name="day_of_week" required>
                            @foreach ($days as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="p_is_workday">Hari Kerja</label>
                        <select id="p_is_workday" name="is_workday">
                            <option value="1">Ya</option>
                            <option value="0">Libur</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="p_start">Masuk</label>
                        <input id="p_start" type="time" name="start_time">
                    </div>
                    <div class="field">
                        <label for="p_end">Pulang</label>
                        <input id="p_end" type="time" name="end_time">
                    </div>
                </div>
                <button type="submit" class="btn">Simpan Jadwal Pribadi</button>
            </form>

            <table style="margin-top:16px">
                <thead>
                <tr><th>Karyawan</th><th>Hari</th><th>Masuk</th><th>Pulang</th><th class="no-print">Aksi</th></tr>
                </thead>
                <tbody>
                @forelse ($personalSchedules as $schedule)
                    <tr>
                        <td>{{ $schedule->employee?->name ?? '—' }}</td>
                        <td>{{ $days[$schedule->day_of_week] ?? $schedule->day_of_week }}</td>
                        <td>{{ $schedule->start_time ? substr((string) $schedule->start_time, 0, 5) : '—' }}</td>
                        <td>{{ $schedule->end_time ? substr((string) $schedule->end_time, 0, 5) : '—' }}</td>
                        <td class="no-print">
                            <form method="POST" action="{{ route('admin.schedule.destroy', $schedule) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger small" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada jadwal pribadi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
