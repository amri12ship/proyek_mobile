@extends('layouts.app')

@section('title', 'Detail Absensi')
@section('heading', 'Detail Absensi')

@section('content')
    <div class="grid cols-2">
        <div class="card">
            <h2>Informasi Absensi</h2>
            <table>
                <tbody>
                <tr><th>Karyawan</th><td>{{ $record->employee?->name ?? '—' }}</td></tr>
                <tr><th>NIK</th><td>{{ $record->employee?->nik ?? '—' }}</td></tr>
                <tr><th>Departemen</th><td>{{ $record->employee?->department?->name ?? '—' }}</td></tr>
                <tr><th>Tanggal</th><td>{{ $record->attendance_date?->translatedFormat('l, d F Y') }}</td></tr>
                <tr><th>Status</th><td><span class="badge {{ $record->status }}">{{ $statuses[$record->status] ?? $record->status }}</span></td></tr>
                <tr><th>Check-in</th><td>{{ $record->check_in_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                <tr><th>Check-out</th><td>{{ $record->check_out_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                <tr><th>Durasi Kerja</th><td>{{ $record->work_minutes ? round($record->work_minutes / 60, 1).' jam' : '—' }}</td></tr>
                <tr><th>Lokasi</th><td>{{ $record->location?->name ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Data Verifikasi</h2>
            <table>
                <tbody>
                <tr><th>Ticket Validasi</th><td>{{ $record->validation?->ticket ?? '—' }}</td></tr>
                <tr><th>Ticket Terbit</th><td>{{ $record->validation?->issued_at?->format('d M Y H:i:s') ?? '—' }}</td></tr>
                <tr><th>Ticket Dipakai</th><td>{{ $record->validation?->consumed_at?->format('d M Y H:i:s') ?? '—' }}</td></tr>
                <tr><th>Jarak Check-in</th><td>{{ $record->check_in_distance ? $record->check_in_distance.' m' : '—' }}</td></tr>
                <tr><th>Akurasi GPS</th><td>{{ $record->check_in_accuracy ? $record->check_in_accuracy.' m' : '—' }}</td></tr>
                <tr><th>Jarak Check-out</th><td>{{ $record->check_out_distance ? $record->check_out_distance.' m' : '—' }}</td></tr>
                <tr><th>Selfie</th>
                    <td>
                        @if ($record->check_in_selfie || $record->check_out_selfie)
                            <span class="badge success">Tersimpan</span>
                        @else
                            <span class="badge inactive">Tidak ada</span>
                        @endif
                    </td>
                </tr>
                <tr><th>Catatan</th><td>{{ $record->notes ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card span-2">
            <h2>Bukti Selfie</h2>
            <div class="selfie-grid">
                @foreach (['check_in' => ['check-in', 'Selfie Check-in'], 'check_out' => ['check-out', 'Selfie Check-out']] as $field => $meta)
                    <figure>
                        <figcaption>{{ $meta[1] }}</figcaption>
                        @if ($record->{$field.'_selfie'})
                            <img src="{{ route('admin.attendance.selfie', [$record, $meta[0]]) }}"
                                 alt="{{ $meta[1] }} {{ $record->employee?->name }}"
                                 loading="lazy">
                        @else
                            <div class="empty">Tidak ada</div>
                        @endif
                    </figure>
                @endforeach
            </div>
            <p class="hint">Gambar disimpan di disk privat dan hanya dapat diakses oleh admin. Setiap pembukaan dicatat pada audit log.</p>
        </div>

        <div class="card">
            <h2>Koreksi Status (Izin / Sakit / Alpha)</h2>
            <form method="POST" action="{{ route('admin.attendance.status', $record) }}">
                @csrf
                @method('PUT')
                <div class="grid cols-2">
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($record->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="notes">Catatan</label>
                        <input id="notes" type="text" name="notes" value="{{ old('notes', $record->notes) }}">
                    </div>
                </div>
                <button type="submit" class="btn">Simpan</button>
                <a class="btn secondary" href="{{ route('admin.attendance.index') }}">Kembali</a>
            </form>
        </div>
    </div>
@endsection
