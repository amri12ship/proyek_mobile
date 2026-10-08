@extends('layouts.app')

@section('title', 'Profil')
@section('heading', 'Profil Saya')

@section('content')
    @php $employee = $user->employee; @endphp

    <div class="grid cols-2">
        <div class="card">
            <h2>Informasi Akun</h2>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="name">Nama Lengkap</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="field">
                    <label for="phone">No. HP</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}">
                </div>
                <button type="submit" class="btn">Simpan Profil</button>
            </form>
        </div>

        <div class="card">
            <h2>Data Karyawan</h2>
            <table>
                <tbody>
                <tr><th>NIK</th><td>{{ $employee?->nik ?? '—' }}</td></tr>
                <tr><th>Departemen</th><td>{{ $employee?->department?->name ?? '—' }}</td></tr>
                <tr><th>Posisi</th><td>{{ $employee?->position?->name ?? '—' }}</td></tr>
                <tr><th>Status</th><td><span class="badge {{ $employee?->status }}">{{ $employee?->status ?? '—' }}</span></td></tr>
                <tr><th>Peran</th><td><span class="badge info">{{ $user->role }}</span></td></tr>
                <tr><th>Login terakhir</th><td>{{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Ubah Password</h2>
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="current_password">Password Saat Ini</label>
                    <input id="current_password" type="password" name="current_password" required>
                </div>
                <div class="field">
                    <label for="new_password">Password Baru</label>
                    <input id="new_password" type="password" name="password" required minlength="6">
                </div>
                <div class="field">
                    <label for="new_password_confirmation">Ulangi Password Baru</label>
                    <input id="new_password_confirmation" type="password" name="password_confirmation" required minlength="6">
                </div>
                <button type="submit" class="btn">Ubah Password</button>
            </form>
        </div>
    </div>
@endsection
