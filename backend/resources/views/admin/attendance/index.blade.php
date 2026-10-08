@extends('layouts.app')

@section('title', 'Absensi')
@section('heading', 'Rekap Absensi Harian')

@section('content')
    <div class="card">
        <form method="GET" class="toolbar">
            <div>
                <label for="date">Tanggal</label>
                <input id="date" type="date" name="date" value="{{ $date->toDateString() }}">
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn">Terapkan</button>
        </form>

        <div class="grid cols-4" style="margin-bottom:16px">
            <div class="stat"><div class="label">Hadir</div><div class="value">{{ $present }}</div></div>
            <div class="stat"><div class="label">Terlambat</div><div class="value">{{ $late }}</div></div>
            <div class="stat"><div class="label">Sudah Pulang</div><div class="value">{{ $complete }}</div></div>
            <div class="stat"><div class="label">Belum Absen</div><div class="value">{{ $absent }}</div>
                <div class="hint">dari {{ $totalEmployees }} karyawan</div></div>
        </div>

        @if ($records->isEmpty())
            <div class="empty">Tidak ada data absensi pada tanggal ini.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>NIK</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th class="num">Durasi</th>
                    <th>Lokasi</th>
                    <th>Status</th>
                    <th class="no-print">Detail</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($records as $record)
                    <tr>
                        <td>{{ $record->employee?->name ?? '—' }}</td>
                        <td>{{ $record->employee?->nik ?? '—' }}</td>
                        <td>{{ $record->check_in_at?->format('H:i') ?? '—' }}</td>
                        <td>{{ $record->check_out_at?->format('H:i') ?? '—' }}</td>
                        <td class="num">{{ $record->work_minutes ? round($record->work_minutes / 60, 1).' j' : '—' }}</td>
                        <td>{{ $record->location?->name ?? '—' }}</td>
                        <td><span class="badge {{ $record->status }}">{{ $statuses[$record->status] ?? $record->status }}</span></td>
                        <td class="no-print">
                            <a class="btn secondary small" href="{{ route('admin.attendance.show', $record) }}">Detail</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="pagination">{{ $records->links() }}</div>
        @endif
    </div>
@endsection
