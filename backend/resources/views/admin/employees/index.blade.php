@extends('layouts.app')

@section('title', 'Karyawan')
@section('heading', 'Data Karyawan')

@section('content')
    <div class="card">
        <form method="GET" class="toolbar">
            <div>
                <label for="q">Cari</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nama / NIK / email">
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn">Terapkan</button>
            <div class="spacer"></div>
            <a class="btn" href="{{ route('admin.employees.create') }}">+ Tambah Karyawan</a>
        </form>

        @if ($employees->isEmpty())
            <div class="empty">Belum ada karyawan.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>NIK</th>
                    <th>Nama</th>
                    <th>Departemen</th>
                    <th>Posisi</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th class="no-print">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($employees as $employee)
                    <tr>
                        <td>{{ $employee->nik }}</td>
                        <td>{{ $employee->name }}</td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td>{{ $employee->position?->name ?? '—' }}</td>
                        <td>{{ $employee->user?->email ?? '—' }}</td>
                        <td><span class="badge {{ $employee->status }}">{{ $statuses[$employee->status] ?? $employee->status }}</span></td>
                        <td class="no-print">
                            <div class="toolbar" style="margin:0;gap:6px">
                                <a class="btn secondary small" href="{{ route('admin.employees.edit', $employee) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.employees.toggle', $employee) }}">
                                    @csrf
                                    <button class="btn secondary small" type="submit">
                                        {{ $employee->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}"
                                      onsubmit="return confirm('Hapus karyawan ini beserta akunnya?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger small" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $employees->links() }}
            </div>
        @endif
    </div>
@endsection
