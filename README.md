# Absensi Karyawan (Mobile)

Aplikasi mobile absensi karyawan berbasis Flutter. Karyawan memindai QR code
lokasi, lalu aplikasi mengirim koordinat GPS dan selfie ke API Laravel untuk
mencatat check-in maupun check-out.

## Fitur

- Login dengan email dan password, token disimpan di keystore perangkat.
- Beranda berisi status kehadiran hari ini, jam kerja, aturan absensi, dan
  daftar lokasi beserta jarak perangkat ke titik lokasi.
- Scan QR lokasi dengan kamera, termasuk nyalakan senter.
- Validasi GPS: radius lokasi, akurasi, dan toleransi check-in.
- Selfie sebagai bukti kehadiran, diunggah terpisah lalu dikirim sebagai
  bagian dari check-in atau check-out.
- Check-out dengan validasi ulang bahwa perangkat masih di dalam radius.
- Riwayat kehadiran dengan pemilih rentang tanggal.
- Pengaturan: ganti alamat server, kembalikan ke default, dan keluar.

## Kebutuhan

- Flutter `3.47.5` atau lebih baru (Dart `3.13.4`).
- Server API Laravel yang sudah berjalan dan dapat dijangkau dari perangkat.
- Perangkat dengan kamera dan GPS aktif.

## Konfigurasi

Alamat server ditentukan pada `AppConstants.defaultBaseUrl`
(`lib/core/constants/app_constants.dart`). Nilai bawaannya adalah
`http://localhost:8000`, bukan alamat LAN, supaya aplikasi tetap jalan saat
perangkat berada di luar jaringan kantor.

### Perangkat Android via USB (disarankan)

`adb reverse` memetakan port 8000 di perangkat ke port 8000 di komputer
sehingga trafik API lewat kabel USB. Jaringan perangkat bebas, termasuk data
seluler.

```bash
# sekali setiap perangkat disambungkan ulang
powershell -ExecutionPolicy Bypass -File tool/usb_tunnel.ps1

flutter run
```

Script tersebut akan memperingatkan bila backend belum listen di port 8000.

### Tanpa USB

Buka tab **Profil** lalu ubah kolom *Alamat server* ke alamat komputer pada
jaringan Wi-Fi yang sama, contoh `http://192.168.100.18:8000`. Mengganti
alamat akan mengeluarkan perangkat dari sesi saat ini. Untuk emulator Android,
gunakan `http://10.0.2.2:8000`.

Alamat yang diganti hanya disimpan di perangkat, jadi build berikutnya tetap
memakai nilai bawaan `localhost`.

### Di luar jaringan

`localhost` hanya bekerja lewat kabel USB. Untuk akses dari jaringan mana pun,
backend perlu alamat publik: deploy ke VPS, port forwarding di router, atau
tunneling (`cloudflared`, `ngrok`). Jika alamatnya memakai `http://` dan bukan
`localhost`, domain tersebut harus ditambahkan ke
`android/app/src/main/res/xml/network_security_config.xml`, karena Android
memblokir cleartext secara default.

## Menjalankan

```bash
flutter pub get
flutter analyze
flutter test
flutter run
```

Build APK:

```bash
flutter build apk --debug
flutter build apk --release
```

## Struktur

```
tool/
└── usb_tunnel.ps1                  # adb reverse untuk device via USB
lib/
├── main.dart                       # Entry point
├── app/
│   ├── app.dart                    # Dependency graph + auth gate
│   ├── app_routes.dart             # Nama route
│   └── app_theme.dart              # Theme terpusat (Material 3)
├── core/
│   ├── constants/                  # Konstanta aplikasi
│   ├── models/                     # Model data dari API
│   ├── network/                    # HTTP client, config, error mapping
│   ├── services/                   # Repository API dan GPS
│   ├── state/                      # Controller (provider)
│   ├── storage/                    # Penyimpanan sesi
│   ├── utils/                      # Responsive + format tanggal
│   └── widgets/                    # Widget reusable
└── features/
    ├── auth/                       # Login dan pengaturan
    ├── attendance/                 # Scan QR, konfirmasi, selfie
    ├── dashboard/                  # Beranda
    ├── history/                    # Riwayat
    └── shell/                      # Kerangka tab utama
```

## Alur absensi

1. `POST /api/v1/auth/login` menyimpan token dan mendaftarkan perangkat.
2. `GET /api/v1/attendance/today` memuat status hari ini dan jarak ke lokasi.
3. `POST /api/v1/qr/validate` menukar isi QR menjadi ticket sekali pakai.
4. `POST /api/v1/attendance/selfie` mengunggah bukti foto.
5. `POST /api/v1/attendance/check-in` mengirim ticket, koordinat, dan path selfie.
6. `POST /api/v1/attendance/check-out` mengirim koordinat saat keluar.

Semua permintaan memakai header `Authorization: Bearer <token>`. Kode QR mentah
tidak pernah dikirim pada saat check-in, hanya ticket hasil validasi.

## Testing

```bash
flutter analyze
flutter test
```

Test mencakup parsing respons API, pemetaan error HTTP ke pesan yang bisa
ditampilkan pengguna, alur sesi, validasi form login, dan tata letak pada
layar kecil maupun tablet.

## Catatan

- `kotlin.incremental=false` di `android/gradle.properties` diperlukan karena
  compiler Kotlin mengunci cache `.tab` di Windows sehingga build gagal dengan
  pesan "Could not close incremental caches".
- Notifikasi push (FCM) belum diimplementasikan; endpoint
  `POST /api/v1/device` sudah storing token perangkat untuk keperluan audit.
- Tunnel USB breakdown setiap kali perangkat dicabut atau ADB terputus. Jalankan
  `tool/usb_tunnel.ps1` lagi; `adb reverse --list` untuk memeriksa tunnel aktif.
