@php
    $admin = auth()->user()?->isAdmin() ?? false;
    $current = Route::currentRouteName();
@endphp

<aside class="sidebar">
    <div class="brand">
        {{ config('app.name') }}
        <small>{{ $admin ? 'Panel Admin' : 'Portal Karyawan' }}</small>
    </div>

    <nav>
        @if ($admin)
            <a href="{{ route('admin.dashboard') }}" class="{{ $current === 'admin.dashboard' ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('admin.employees.index') }}" class="{{ str_starts_with((string) $current, 'admin.employees') ? 'active' : '' }}">Karyawan</a>
            <a href="{{ route('admin.locations.index') }}" class="{{ str_starts_with((string) $current, 'admin.locations') ? 'active' : '' }}">Lokasi</a>
            <a href="{{ route('admin.attendance.index') }}" class="{{ str_starts_with((string) $current, 'admin.attendance') ? 'active' : '' }}">Absensi</a>
            <a href="{{ route('admin.schedule.index') }}" class="{{ str_starts_with((string) $current, 'admin.schedule') ? 'active' : '' }}">Jadwal</a>
            <a href="{{ route('admin.holiday.index') }}" class="{{ str_starts_with((string) $current, 'admin.holiday') ? 'active' : '' }}">Hari Libur</a>
            <a href="{{ route('admin.report.index') }}" class="{{ str_starts_with((string) $current, 'admin.report') ? 'active' : '' }}">Laporan</a>
            <a href="{{ route('admin.settings.edit') }}" class="{{ $current === 'admin.settings.edit' ? 'active' : '' }}">Pengaturan</a>
            <a href="{{ route('admin.audit.index') }}" class="{{ $current === 'admin.audit.index' ? 'active' : '' }}">Audit Log</a>
        @else
            <a href="{{ route('dashboard') }}" class="{{ $current === 'dashboard' ? 'active' : '' }}">Beranda</a>
        @endif

        <a href="{{ route('profile.edit') }}" class="{{ $current === 'profile.edit' ? 'active' : '' }}">Profil</a>

        <form method="POST" action="{{ route('logout') }}" style="margin-top:10px">
            @csrf
            <button type="submit" class="btn secondary small" style="width:100%">Keluar</button>
        </form>
    </nav>
</aside>
