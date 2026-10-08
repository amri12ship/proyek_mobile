<#
.SYNOPSIS
    Meneruskan port 8000 di perangkat Android ke port 8000 di komputer ini.

.DESCRIPTION
    Aplikasi memakai `http://localhost:8000` sebagai alamat default. Agar
    localhost di perangkat sampai ke backend Laravel yang jalan di laptop,
    perlu `adb reverse`: trafik API berjalan lewat kabel USB sehingga jaringan
    perangkat bebas, termasuk data seluler.

    Jalankan script ini setiap kali perangkat disambungkan ulang ke laptop.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tool\usb_tunnel.ps1
#>
[CmdletBinding()]
param(
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'

function Resolve-Adb {
    $command = Get-Command adb -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $candidates = @(
        "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe",
        "$env:ANDROID_HOME\platform-tools\adb.exe",
        "$env:ANDROID_SDK_ROOT\platform-tools\adb.exe"
    )

    foreach ($candidate in $candidates) {
        if ($candidate -and (Test-Path -LiteralPath $candidate)) {
            return $candidate
        }
    }

    throw 'adb tidak ditemukan. Pasang Android SDK Platform-Tools atau jalankan `flutter doctor`.'
}

$adb = Resolve-Adb
Write-Host "adb: $adb" -ForegroundColor DarkGray

$devices = @(& $adb devices | Select-Object -Skip 1 | Where-Object { $_ -match '\sdevice$' })
if ($devices.Count -eq 0) {
    Write-Host 'Tidak ada perangkat USB yang terdeteksi.' -ForegroundColor Red
    Write-Host 'Colok HP, aktifkan USB debugging, lalu terima dialog "Allow USB debugging" di layar HP.'
    exit 1
}

foreach ($line in $devices) {
    $serial = $line.Trim() -split '\s+', 2 | Select-Object -First 1
    Write-Host "Memasang reverse tunnel untuk $serial" -ForegroundColor Cyan
    & $adb -s $serial reverse "tcp:$Port" "tcp:$Port"
}

$listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
if (-not $listener) {
    Write-Host "Peringatan: tidak ada proses yang listen di port $Port di laptop." -ForegroundColor Yellow
    Write-Host 'Backend API kemungkinan belum jalan. Tunnel sudah terpasang, tapi request akan gagal.'
}
else {
    Write-Host "Backend listen di port $Port." -ForegroundColor Green
}

Write-Host ''
Write-Host "Tunnel siap. Alamat server di app: http://localhost:$Port" -ForegroundColor Green
Write-Host 'Jalankan `flutter run` seperti biasa. Jaringan HP/data tidak berpengaruh.'
