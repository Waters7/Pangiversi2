<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LogApi;
use App\Models\Pengaturan;
use App\Models\PengirimanIntegrasi;
use App\Services\AuditService;
use App\Services\PengirimIntegrasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Integrasi Data — submenu Administrasi Sistem bagi super administrator:
 * token API yang dibagikan ke aplikasi lain, pemantauan setiap permintaan
 * yang masuk beserta token yang dibawanya, dan pengiriman data dashboard
 * eksekutif terjadwal ke aplikasi tujuan.
 */
class IntegrasiDataController extends Controller
{
    /** Baris catatan terbaru yang ditampilkan. */
    private const BARIS_LOG = 50;

    private const BARIS_PENGIRIMAN = 20;

    public function __construct(private AuditService $audit, private PengirimIntegrasi $pengirim) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403, 'Integrasi Data hanya untuk super administrator.');

        $hasil = $request->input('hasil');
        $log = LogApi::latest('id')
            ->when(in_array($hasil, [LogApi::DITERIMA, LogApi::DITOLAK, LogApi::TERTUTUP], true), fn ($q) => $q->where('hasil', $hasil))
            ->limit(self::BARIS_LOG)
            ->get();

        return view('administrasi.integrasi', [
            'tokenApi' => Pengaturan::tokenApi(),
            'ringkasanLog' => $this->ringkasanLog(),
            'log' => $log,
            'saringHasil' => $hasil,
            'pengaturanKirim' => $this->pengirim->pengaturan(),
            'jadwalBerikutnya' => $this->pengirim->jadwalBerikutnya(),
            'pengiriman' => PengirimanIntegrasi::with('pengguna')->latest('id')->limit(self::BARIS_PENGIRIMAN)->get(),
            'pilihanJadwal' => PengirimIntegrasi::JADWAL,
            'pilihanHari' => PengirimIntegrasi::HARI,
        ]);
    }

    /**
     * Simpan alamat tujuan, token, dan jadwal pengiriman.
     */
    public function simpanJadwal(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Integrasi Data hanya untuk super administrator.');

        $data = $request->validate([
            'integrasi_aktif' => ['nullable', 'boolean'],
            'integrasi_url' => ['nullable', 'url:http,https', 'max:255', Rule::requiredIf($request->boolean('integrasi_aktif'))],
            'integrasi_token_tujuan' => ['nullable', 'string', 'max:255', 'regex:/^\S+$/'],
            'hapus_token_tujuan' => ['nullable', 'boolean'],
            'integrasi_jadwal' => ['required', Rule::in(array_keys(PengirimIntegrasi::JADWAL))],
            'integrasi_jam' => ['required', 'date_format:H:i'],
            'integrasi_hari' => ['required', Rule::in(array_keys(PengirimIntegrasi::HARI))],
        ], [
            'integrasi_url.required' => 'Alamat tujuan wajib diisi bila pengiriman diaktifkan.',
            'integrasi_url.url' => 'Alamat tujuan harus berupa URL lengkap yang diawali http:// atau https://.',
            'integrasi_token_tujuan.regex' => 'Token tidak boleh memuat spasi.',
            'integrasi_jam.date_format' => 'Jam kirim ditulis HH:MM, misalnya 06:00.',
        ]);

        Pengaturan::simpan([
            Pengaturan::INTEGRASI_AKTIF => $request->boolean('integrasi_aktif') ? '1' : '0',
            Pengaturan::INTEGRASI_URL => trim((string) ($data['integrasi_url'] ?? '')),
            Pengaturan::INTEGRASI_JADWAL => $data['integrasi_jadwal'],
            Pengaturan::INTEGRASI_JAM => $data['integrasi_jam'],
            Pengaturan::INTEGRASI_HARI => $data['integrasi_hari'],
        ]);

        // Token tujuan hanya ditulis bila diisi; kolom kosong berarti "biarkan".
        if ($request->boolean('hapus_token_tujuan')) {
            Pengaturan::simpanRahasia(Pengaturan::INTEGRASI_TOKEN_TUJUAN, '');
        } elseif (filled($data['integrasi_token_tujuan'] ?? null)) {
            Pengaturan::simpanRahasia(Pengaturan::INTEGRASI_TOKEN_TUJUAN, $data['integrasi_token_tujuan']);
        }

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            $request->boolean('integrasi_aktif')
                ? 'Pengiriman data terjadwal diaktifkan ('.PengirimIntegrasi::JADWAL[$data['integrasi_jadwal']].') ke '.$data['integrasi_url'].'.'
                : 'Pengiriman data terjadwal dinonaktifkan.',
        );

        return redirect()->route('administrasi.integrasi')->with('success', 'Pengaturan pengiriman tersimpan.');
    }

    /**
     * Kirim sekarang, tanpa menunggu jadwal — untuk mencoba alamat tujuan.
     */
    public function kirimSekarang(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Integrasi Data hanya untuk super administrator.');

        $hasil = $this->pengirim->kirim(PengirimanIntegrasi::PEMICU_MANUAL, $request->user());

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            $hasil->berhasil()
                ? "Data dashboard eksekutif dikirim manual ke {$hasil->tujuan} (HTTP {$hasil->kode_http})."
                : "Pengiriman manual ke {$hasil->tujuan} gagal: {$hasil->pesan}",
        );

        return redirect()->route('administrasi.integrasi')->with(
            $hasil->berhasil() ? 'success' : 'error',
            $hasil->berhasil()
                ? "Data terkirim ke aplikasi tujuan (HTTP {$hasil->kode_http}, ".number_format((int) $hasil->ukuran_byte, 0, ',', '.').' byte).'
                : 'Pengiriman gagal: '.$hasil->pesan,
        );
    }

    /**
     * @return array{hari_ini: int, tujuh_hari: int, ditolak_tujuh_hari: int, terakhir: ?LogApi, ip_unik: int}
     */
    private function ringkasanLog(): array
    {
        $sejak = now()->subDays(7);

        return [
            'hari_ini' => LogApi::sejak(today())->count(),
            'tujuh_hari' => LogApi::sejak($sejak)->count(),
            'ditolak_tujuh_hari' => LogApi::sejak($sejak)->where('hasil', '!=', LogApi::DITERIMA)->count(),
            'terakhir' => LogApi::latest('id')->first(),
            'ip_unik' => LogApi::sejak($sejak)->distinct('ip')->count('ip'),
        ];
    }
}
