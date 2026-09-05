@echo off
REM ============================================================
REM  PANGI - jalankan server untuk uji coba bersama di jaringan
REM
REM  Klik dua kali berkas ini. Server dibuka ke seluruh jaringan
REM  lokal, sehingga rekan yang terhubung ke Wi-Fi yang sama dapat
REM  membukanya dari perangkat masing-masing.
REM
REM  Tekan Ctrl+C atau tutup jendela ini untuk menghentikan server.
REM ============================================================

setlocal
chcp 65001 >nul

set "PHP_DIR=C:\laragon\bin\php\php-8.4.25-Win32-vs17-x64"
set "PATH=%PHP_DIR%;%PATH%"
set "PORTA=8000"

cd /d "%~dp0"

where php >nul 2>&1
if errorlevel 1 (
    echo.
    echo   PHP tidak ditemukan pada %PHP_DIR%
    echo   Sesuaikan baris PHP_DIR di dalam berkas ini.
    echo.
    pause
    exit /b 1
)

REM Pastikan porta belum dipakai proses lain.
netstat -ano | findstr /r /c:":%PORTA% .*LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo.
    echo   Porta %PORTA% sedang dipakai proses lain.
    echo   Tutup server PANGI yang masih berjalan, lalu coba lagi.
    echo.
    pause
    exit /b 1
)

REM Selaraskan APP_URL dengan alamat IP jaringan saat ini, lalu
REM bersihkan cache supaya alamat barunya benar-benar terpakai.
php artisan pangi:siapkan-lan --port=%PORTA%
if errorlevel 1 (
    echo.
    echo   Penyiapan gagal. Server tidak dijalankan.
    echo.
    pause
    exit /b 1
)

php artisan view:clear >nul 2>&1

REM Izinkan porta pada Windows Firewall bila berkas ini dijalankan
REM sebagai Administrator. Tanpa hak itu, langkahnya dilewati dan
REM perintahnya sudah ditampilkan oleh perintah penyiapan di atas.
net session >nul 2>&1
if not errorlevel 1 (
    netsh advfirewall firewall show rule name="PANGI %PORTA%" >nul 2>&1
    if errorlevel 1 (
        netsh advfirewall firewall add rule name="PANGI %PORTA%" dir=in action=allow protocol=TCP localport=%PORTA% >nul 2>&1
        echo   Aturan firewall untuk porta %PORTA% ditambahkan.
        echo.
    )
)

echo   Menjalankan server. Biarkan jendela ini terbuka.
echo.

php artisan serve --host=0.0.0.0 --port=%PORTA%

endlocal
