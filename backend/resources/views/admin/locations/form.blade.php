@extends('layouts.app')

@section('title', $location->exists ? 'Edit Lokasi' : 'Tambah Lokasi')
@section('heading', $location->exists ? 'Edit Lokasi' : 'Tambah Lokasi')

@section('content')
    <div class="card">
        <form method="POST"
              action="{{ $location->exists ? route('admin.locations.update', $location) : route('admin.locations.store') }}">
            @csrf
            @if ($location->exists)
                @method('PUT')
            @endif

            <div class="grid cols-2">
                <div class="field">
                    <label for="name">Nama Lokasi</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $location->name) }}" required>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="active" @selected(old('status', $location->status) === 'active')>Aktif</option>
                        <option value="inactive" @selected(old('status', $location->status) === 'inactive')>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="address">Alamat</label>
                <textarea id="address" name="address" rows="2">{{ old('address', $location->address) }}</textarea>
            </div>

            <div class="grid cols-3">
                <div class="field">
                    <label for="latitude">Latitude</label>
                    <input id="latitude" type="number" step="0.0000001" name="latitude"
                           value="{{ old('latitude', $location->latitude) }}" required>
                </div>
                <div class="field">
                    <label for="longitude">Longitude</label>
                    <input id="longitude" type="number" step="0.0000001" name="longitude"
                           value="{{ old('longitude', $location->longitude) }}" required>
                </div>
                <div class="field">
                    <label for="radius">Radius (meter)</label>
                    <input id="radius" type="number" name="radius"
                           min="{{ \App\Models\AttendanceLocation::MIN_RADIUS_METERS }}"
                           max="{{ \App\Models\AttendanceLocation::MAX_RADIUS_METERS }}"
                           value="{{ old('radius', $location->radius ?? \App\Models\AttendanceLocation::DEFAULT_RADIUS_METERS) }}" required>
                </div>
            </div>

            <div class="field">
                <div class="toolbar" style="margin-bottom:0">
                    <button type="button" class="btn secondary small" id="gps-capture">Ambil Koordinat dari GPS</button>
                </div>
                <p class="hint" id="gps-feedback" role="status" aria-live="polite"></p>
            </div>

            <div class="toolbar" style="margin-bottom:0">
                <button type="submit" class="btn">Simpan Lokasi</button>
                <a class="btn secondary" href="{{ route('admin.locations.index') }}">Batal</a>
            </div>
        </form>
    </div>

    @if ($location->exists)
        <div class="card">
            <h2>QR Code Lokasi</h2>
            <p style="color:var(--muted);margin-top:0">
                QR hanya dapat dibaca melalui aplikasi mobile untuk menerbitkan ticket validasi sekali pakai.
                Token QR tidak pernah dikirim ke API.
            </p>
            <p class="hint">
                QR dibuat sekali saat lokasi dibuat dan bersifat permanen untuk wilayah ini. Menyimpan perubahan
                pada halaman ini tidak akan mengubah QR. Untuk mencabut QR yang sudah tidak aman, gunakan tombol
                "Ganti QR Code" di halaman QR.
            </p>
            <div class="toolbar" style="margin-bottom:0">
                <a class="btn" href="{{ route('admin.locations.qr', $location) }}">Buka QR Code</a>
            </div>
        </div>
    @endif

    <script>
        (function () {
            var button = document.getElementById('gps-capture');
            var feedback = document.getElementById('gps-feedback');
            var latitude = document.getElementById('latitude');
            var longitude = document.getElementById('longitude');
            var radius = document.getElementById('radius');

            var tones = { info: '#64748b', ok: '#15803d', warn: '#b45309', error: '#b91c1c' };

            function report(message, tone) {
                feedback.textContent = message;
                feedback.style.color = tones[tone] || tones.info;
            }

            if (!button) {
                return;
            }

            if (window.isSecureContext === false) {
                button.disabled = true;
                report('GPS tidak dapat dipakai: halaman ini dibuka lewat HTTP. Buka lewat HTTPS atau localhost agar browser mengizinkan akses lokasi.', 'error');
                return;
            }

            if (!navigator.geolocation) {
                button.disabled = true;
                report('Browser Anda tidak mendukung Geolocation API. Isi latitude dan longitude secara manual.', 'error');
                return;
            }

            function onError(error) {
                button.disabled = false;

                var messages = {
                    1: 'Izin lokasi ditolak. Izinkan akses lokasi pada browser lalu coba lagi, atau isi koordinat secara manual.',
                    2: 'Sinyal lokasi tidak tersedia. Pastikan perangkat lock GPS atau isi koordinat secara manual.',
                    3: 'Pembacaan GPS melebihi batas waktu. Coba lagi di tempat dengan sinyal lebih baik, atau isi koordinat secara manual.',
                };

                report(messages[error.code] || 'Gagal membaca GPS. Isi koordinat secara manual.', 'error');
            }

            button.addEventListener('click', function () {
                button.disabled = true;
                report('Membaca posisi GPS, tunggu sebentar...', 'info');

                navigator.geolocation.getCurrentPosition(function (position) {
                    button.disabled = false;

                    latitude.value = position.coords.latitude.toFixed(7);
                    longitude.value = position.coords.longitude.toFixed(7);

                    var accuracy = Math.round(position.coords.accuracy);
                    var allowedRadius = parseFloat(radius.value) || 0;

                    if (allowedRadius > 0 && accuracy > allowedRadius / 2) {
                        report('Koordinat terisi dari GPS dengan akurasi +/- ' + accuracy + ' m. Nilai itu besar dibanding radius ' + allowedRadius + ' m, jadi pertimbangkan menaikkan radius atau ambil koordinat di titik yang lebih terbuka.', 'warn');
                        return;
                    }

                    if (accuracy > 50) {
                        report('Koordinat terisi dari GPS dengan akurasi +/- ' + accuracy + ' m. Akurasi kurang baik, periksa hasilnya sebelum menyimpan.', 'warn');
                        return;
                    }

                    report('Koordinat terisi dari GPS dengan akurasi +/- ' + accuracy + ' m.', 'ok');
                }, onError, {
                    enableHighAccuracy: true,
                    timeout: 20000,
                    maximumAge: 0,
                });
            });
        })();
    </script>
@endsection
