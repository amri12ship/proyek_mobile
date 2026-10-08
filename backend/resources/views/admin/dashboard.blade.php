@extends('layouts.app')

@section('title', 'Dashboard Admin')
@section('heading', 'Dashboard Admin')

@section('content')
    <p style="color:var(--muted);margin-top:0">{{ $todayLabel }}</p>

    <div class="grid cols-4" style="margin-bottom:18px">
        <div class="stat">
            <div class="label">Karyawan Aktif</div>
            <div class="value">{{ $totalEmployees }}</div>
            <div class="hint">{{ $inactiveEmployees }} nonaktif</div>
        </div>
        <div class="stat">
            <div class="label">Hadir Hari Ini</div>
            <div class="value">{{ $presentToday }}</div>
            <div class="hint">{{ $absentToday }} belum hadir</div>
        </div>
        <div class="stat">
            <div class="label">Terlambat</div>
            <div class="value">{{ $lateToday }}</div>
            <div class="hint">Melewati toleransi</div>
        </div>
        <div class="stat">
            <div class="label">Sudah Pulang</div>
            <div class="value">{{ $checkedOutToday }}</div>
            <div class="hint">Check-out tercatat</div>
        </div>
    </div>

    <div class="card">
        <h2>Aksi Cepat</h2>
        <div class="toolbar" style="margin-bottom:0">
            <a class="btn" href="{{ route('admin.attendance.index') }}">Lihat Absensi Hari Ini</a>
            <a class="btn secondary" href="{{ route('admin.locations.index') }}">Kelola Lokasi &amp; QR</a>
            <a class="btn secondary" href="{{ route('admin.report.index') }}">Buka Laporan</a>
        </div>
    </div>

    <div class="card">
        <h2>Absensi Terbaru</h2>
        @if ($recentRecords->isEmpty())
            <div class="empty">Belum ada aktivitas absensi.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Tanggal</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Lokasi</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($recentRecords as $record)
                    <tr>
                        <td>{{ $record->employee?->name ?? '—' }}</td>
                        <td>{{ $record->attendance_date?->translatedFormat('d M Y') }}</td>
                        <td>{{ $record->check_in_at?->format('H:i') ?? '—' }}</td>
                        <td>{{ $record->check_out_at?->format('H:i') ?? '—' }}</td>
                        <td>{{ $record->location?->name ?? '—' }}</td>
                        <td><span class="badge {{ $record->status }}">{{ $record->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
