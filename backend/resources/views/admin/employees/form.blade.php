@extends('layouts.app')

@section('title', $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan')
@section('heading', $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan')

@section('content')
    <div class="card">
        <form method="POST"
              action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}">
            @csrf
            @if ($employee->exists)
                @method('PUT')
            @endif

            <div class="grid cols-2">
                <div>
                    <div class="field">
                        <label for="nik">NIK</label>
                        <input id="nik" type="text" name="nik" value="{{ old('nik', $employee->nik) }}" required>
                    </div>
                    <div class="field">
                        <label for="name">Nama Lengkap</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $employee->name) }}" required>
                    </div>
                    <div class="field">
                        <label for="email">Email Login</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $employee->user?->email) }}" required>
                    </div>
                    <div class="field">
                        <label for="username">Username (opsional)</label>
                        <input id="username" type="text" name="username" value="{{ old('username', $employee->user?->username) }}">
                    </div>
                    <div class="field">
                        <label for="password">Password {{ $employee->exists ? '(kosongkan jika tidak diubah)' : '' }}</label>
                        <input id="password" type="password" name="password" {{ $employee->exists ? '' : 'required' }} minlength="6">
                    </div>
                </div>

                <div>
                    <div class="field">
                        <label for="department_id">Departemen</label>
                        <select id="department_id" name="department_id">
                            <option value="">—</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id', $employee->department_id) == $department->id)>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="position_id">Posisi</label>
                        <select id="position_id" name="position_id">
                            <option value="">—</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" @selected(old('position_id', $employee->position_id) == $position->id)>
                                    {{ $position->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="phone">No. HP</label>
                        <input id="phone" type="text" name="phone" value="{{ old('phone', $employee->phone) }}">
                    </div>
                    <div class="field">
                        <label for="gender">Jenis Kelamin</label>
                        <select id="gender" name="gender">
                            <option value="">—</option>
                            <option value="L" @selected(old('gender', $employee->gender) === 'L')>Laki-laki</option>
                            <option value="P" @selected(old('gender', $employee->gender) === 'P')>Perempuan</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="hire_date">Tanggal Bergabung</label>
                        <input id="hire_date" type="date" name="hire_date" value="{{ old('hire_date', $employee->hire_date?->toDateString()) }}">
                    </div>
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" @selected(old('status', $employee->status) === 'active')>Aktif</option>
                            <option value="inactive" @selected(old('status', $employee->status) === 'inactive')>Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="field">
                <label>Lokasi Absensi yang Diizinkan</label>
                <div class="grid cols-3">
                    @foreach ($locations as $location)
                        <div class="checkbox">
                            <input type="checkbox" id="loc{{ $location->id }}" name="locations[]" value="{{ $location->id }}"
                                   @checked(in_array($location->id, old('locations', $selectedLocations), false))>
                            <label for="loc{{ $location->id }}">{{ $location->name }} ({{ $location->radius }} m)</label>
                        </div>
                    @endforeach
                </div>
                <p style="color:var(--muted);font-size:12.5px;margin:6px 0 0">
                    Jika tidak ada lokasi yang dipilih, karyawan boleh absen di semua lokasi aktif.
                </p>
            </div>

            <div class="toolbar" style="margin-bottom:0">
                <button type="submit" class="btn">Simpan</button>
                <a class="btn secondary" href="{{ route('admin.employees.index') }}">Batal</a>
            </div>
        </form>
    </div>
@endsection
