@extends('layouts.app')

@section('title', 'Lokasi')
@section('heading', 'Lokasi Absensi')

@section('content')
    <div class="card">
        <form method="GET" class="toolbar">
            <div>
                <label for="q">Cari lokasi</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nama lokasi">
            </div>
            <button type="submit" class="btn">Cari</button>
            <div class="spacer"></div>
            <a class="btn" href="{{ route('admin.locations.create') }}">+ Tambah Lokasi</a>
        </form>

        @if ($locations->isEmpty())
            <div class="empty">Belum ada lokasi absensi.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>Alamat</th>
                    <th class="num">Latitude</th>
                    <th class="num">Longitude</th>
                    <th class="num">Radius</th>
                    <th class="num">Karyawan</th>
                    <th>Status</th>
                    <th class="no-print">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($locations as $location)
                    <tr>
                        <td>{{ $location->name }}</td>
                        <td>{{ $location->address ?? '—' }}</td>
                        <td class="num">{{ $location->latitude }}</td>
                        <td class="num">{{ $location->longitude }}</td>
                        <td class="num">{{ $location->radius }} m</td>
                        <td class="num">{{ $location->employees_count }}</td>
                        <td><span class="badge {{ $location->status }}">{{ $location->status }}</span></td>
                        <td class="no-print">
                            <div class="toolbar" style="margin:0;gap:6px">
                                <a class="btn secondary small" href="{{ route('admin.locations.qr', $location) }}">QR Code</a>
                                <a class="btn secondary small" href="{{ route('admin.locations.edit', $location) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.locations.destroy', $location) }}"
                                      onsubmit="return confirm('Hapus lokasi ini?')">
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

            <div class="pagination">{{ $locations->links() }}</div>
        @endif
    </div>
@endsection
