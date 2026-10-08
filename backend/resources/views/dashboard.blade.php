@extends('layouts.app')

@section('title', 'Beranda')
@section('heading', 'Beranda')

@section('content')
    <div class="grid cols-4" style="margin-bottom:18px">
        <div class="stat">
            <div class="label">Status Hari Ini</div>
            <div class="value" style="font-size:19px">
                @php
                    $state = match (true) {
                        $record === null => 'Belum absen',
                        $record->isComplete() => 'Selesai',
                        default => 'Sudah masuk',
                    };
                @endphp
                {{ $state }}
            </div>
            <div class="hint">{{ now()->translatedFormat('l, d F Y') }}</div>
        </div>
        <div class="stat">
            <div class="label">Masuk</div>
            <div class="value">{{ $record?->check_in_at?->format('H:i') ?? '—' }}</div>
            <div class="hint">{{ $record?->location?->name ?? 'Belum check-in' }}</div>
        </div>
        <div class="stat">
            <div class="label">Keluar</div>
            <div class="value">{{ $record?->check_out_at?->format('H:i') ?? '—' }}</div>
            <div class="hint">{{ $record?->work_minutes ? $record->work_minutes.' menit' : 'Belum check-out' }}</div>
        </div>
        <div class="stat">
            <div class="label">Bulan Ini</div>
            <div class="value">{{ $statPresent + $statLate }}</div>
            <div class="hint">{{ $monthLabel }} · {{ $statLate }} terlambat</div>
        </div>
    </div>

    <div class="card">
        <h2>Absensi melalui aplikasi mobile</h2>
        <p style="color:var(--muted);margin-top:0">
            Check-in dan check-out dilakukan dari aplikasi Android/iOS dengan urutan:
            dalam radius lokasi → scan QR code lokasi → check-in.
        </p>
        <p style="margin-bottom:0">
            <span class="badge success">Hadir {{ $statPresent }}</span>
            <span class="badge warning">Terlambat {{ $statLate }}</span>
            <span class="badge info">Total jam {{ round($statMinutes / 60, 1) }} jam</span>
        </p>
    </div>

    <div class="card">
        <h2>Riwayat Terakhir</h2>
        @if ($recent->isEmpty())
            <div class="empty">Belum ada riwayat absensi.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Lokasi</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($recent as $item)
                    <tr>
                        <td>{{ $item->attendance_date?->translatedFormat('d M Y') }}</td>
                        <td>{{ $item->check_in_at?->format('H:i') ?? '—' }}</td>
                        <td>{{ $item->check_out_at?->format('H:i') ?? '—' }}</td>
                        <td>{{ $item->location?->name ?? '—' }}</td>
                        <td><span class="badge {{ $item->status }}">{{ $item->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
