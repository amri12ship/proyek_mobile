@extends('layouts.app')

@section('title', 'Laporan')
@section('heading', 'Laporan Absensi')

@section('content')
    <div class="card">
        <form method="GET" class="toolbar">
            <div>
                <label for="from">Dari</label>
                <input id="from" type="date" name="from" value="{{ $from->toDateString() }}">
            </div>
            <div>
                <label for="to">Sampai</label>
                <input id="to" type="date" name="to" value="{{ $to->toDateString() }}">
            </div>
            <div>
                <label for="department_id">Departemen</label>
                <select id="department_id" name="department_id">
                    <option value="">Semua</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
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
            <a class="btn secondary"
               href="{{ route('admin.report.export', request()->query()) }}">Ekspor CSV</a>
        </form>

        <div class="grid cols-4" style="margin-bottom:16px">
            <div class="stat"><div class="label">Hadir</div><div class="value">{{ $totals['hadir'] }}</div></div>
            <div class="stat"><div class="label">Terlambat</div><div class="value">{{ $totals['terlambat'] }}</div></div>
            <div class="stat"><div class="label">Izin / Sakit</div><div class="value">{{ $totals['izin'] + $totals['sakit'] }}</div></div>
            <div class="stat"><div class="label">Total Jam Kerja</div>
                <div class="value">{{ round($totals['minutes'] / 60, 1) }}</div>
                <div class="hint">jam</div></div>
        </div>

        @if ($rows->isEmpty())
            <div class="empty">Tidak ada data pada periode ini.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Departemen</th>
                    <th class="num">Hadir</th>
                    <th class="num">Terlambat</th>
                    <th class="num">Izin</th>
                    <th class="num">Sakit</th>
                    <th class="num">Alpha</th>
                    <th class="num">Jam Kerja</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['employee']?->name ?? '—' }} <span style="color:var(--muted)">{{ $row['employee']?->nik }}</span></td>
                        <td>{{ $row['employee']?->department?->name ?? '—' }}</td>
                        <td class="num">{{ $row['hadir'] }}</td>
                        <td class="num">{{ $row['terlambat'] }}</td>
                        <td class="num">{{ $row['izin'] }}</td>
                        <td class="num">{{ $row['sakit'] }}</td>
                        <td class="num">{{ $row['alpha'] }}</td>
                        <td class="num">{{ round($row['minutes'] / 60, 1) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
