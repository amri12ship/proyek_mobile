@extends('layouts.app')

@section('title', 'Hari Libur')
@section('heading', 'Hari Libur')

@section('content')
    <div class="card">
        <h2>Tambah Hari Libur</h2>
        <form method="POST" action="{{ route('admin.holiday.store') }}" class="grid cols-3">
            @csrf
            <div class="field">
                <label for="date">Tanggal</label>
                <input id="date" type="date" name="date" value="{{ old('date') }}" required>
            </div>
            <div class="field">
                <label for="name">Nama Libur</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="description">Keterangan</label>
                <input id="description" type="text" name="description" value="{{ old('description') }}">
            </div>
            <div>
                <button type="submit" class="btn">Tambah</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Daftar Hari Libur</h2>
        @if ($holidays->isEmpty())
            <div class="empty">Belum ada hari libur terdaftar.</div>
        @else
            <table>
                <thead>
                <tr><th>Tanggal</th><th>Nama</th><th>Keterangan</th><th class="no-print">Aksi</th></tr>
                </thead>
                <tbody>
                @foreach ($holidays as $holiday)
                    <tr>
                        <td>{{ $holiday->date?->translatedFormat('l, d F Y') }}</td>
                        <td>{{ $holiday->name }}</td>
                        <td>{{ $holiday->description ?? '—' }}</td>
                        <td class="no-print">
                            <form method="POST" action="{{ route('admin.holiday.destroy', $holiday) }}"
                                  onsubmit="return confirm('Hapus hari libur ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger small" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pagination">{{ $holidays->links() }}</div>
        @endif
    </div>
@endsection
