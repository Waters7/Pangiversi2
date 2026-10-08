<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CatatanPenyimpanan;
use App\Models\LogApi;
use App\Models\Pengaturan;
use App\Services\AuditService;
use App\Services\PemantauServer;
use App\Services\PengirimWhatsapp;
use App\Services\PengukurPenyimpanan;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pemantauan Server — submenu Administrasi Sistem bagi super administrator:
 * ukuran aplikasi di peladen beserta pertumbuhannya, status pemeriksaan
 * kesehatan, dan peringatan WhatsApp saat aplikasi tidak dapat diakses.
 */
class PemantauanServerController extends Controller
{
    /** Catatan harian yang digambarkan pada grafik pertumbuhan. */
    private const HARI_GRAFIK = 30;

    public function __construct(
        private AuditService $audit,
        private PemantauServer $pemantau,
        private PengukurPenyimpanan $pengukur,
    ) {}

    public function index(Request $request): View
    {
        $this->pastikanAdmin($request);

        $catatan = CatatanPenyimpanan::orderByDesc('tanggal')->limit(self::HARI_GRAFIK)->get();
        $pengaturan = $this->pemantau->pengaturan();
        $kuotaMb = Pengaturan::angka(Pengaturan::KUOTA_PENYIMPANAN_MB);

        return view('administrasi.server', [
            'terbaru' => $catatan->first(),
            'grafik' => $catatan->reverse()->values(),
            'pertumbuhan' => [
                'tujuh_hari' => $this->pertumbuhan($catatan, 7),
                'tiga_puluh_hari' => $this->pertumbuhan($catatan, 30),
            ],
            'kuotaByte' => $kuotaMb > 0 ? $kuotaMb * 1024 * 1024 : null,
            'kelompok' => PengukurPenyimpanan::KELOMPOK,
            'status' => $this->pemantau->status(),
            'cronBerjalan' => $this->pemantau->cronBerjalan(),
            'perintahCron' => '* * * * * cd '.base_path().' && php artisan pangi:pantau-server >> /dev/null 2>&1',
            'peringatan' => [
                'aktif' => $pengaturan['aktif'],
                'nomor' => $pengaturan['nomor'],
                'gateway' => $pengaturan['gateway'],
                'url_wablas' => $pengaturan['url_wablas'],
                'ulang_menit' => $pengaturan['ulang_menit'],
                'token_tersamar' => $pengaturan['token'] === '' ? null : LogApi::samarkan($pengaturan['token']),
            ],
            'pilihanGateway' => PengirimWhatsapp::GATEWAY,
        ]);
    }

    /**
     * Ukur sekarang, tanpa menunggu catatan malam.
     */
    public function ukurUlang(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        // Menjelajahi seluruh folder aplikasi di hosting bersama dapat lebih
        // lama dari batas eksekusi bawaan.
        @set_time_limit(300);

        $catatan = $this->pengukur->catat();

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, 'Ukuran aplikasi diukur ulang: '.CatatanPenyimpanan::ukuranTerbaca($catatan->total_byte).'.');

        return redirect()->route('administrasi.server')->with(
            'success',
            'Ukuran aplikasi diukur ulang: '.CatatanPenyimpanan::ukuranTerbaca($catatan->total_byte)
                .' (selesai dalam '.number_format((float) $catatan->rincian['lama_detik'], 1, ',', '.').' detik).',
        );
    }

    public function simpanKuota(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        $data = $request->validate([
            'kuota_gb' => ['nullable', 'numeric', 'min:0.1', 'max:100000'],
        ], [
            'kuota_gb.numeric' => 'Kuota ditulis dalam angka GB, misalnya 10 atau 2.5.',
            'kuota_gb.min' => 'Kuota sedikitnya 0,1 GB.',
        ]);

        $kuotaMb = filled($data['kuota_gb'] ?? null) ? (int) round((float) $data['kuota_gb'] * 1024) : 0;

        Pengaturan::simpan([Pengaturan::KUOTA_PENYIMPANAN_MB => $kuotaMb > 0 ? (string) $kuotaMb : '']);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            $kuotaMb > 0 ? 'Kuota penyimpanan hosting diatur '.$data['kuota_gb'].' GB.' : 'Kuota penyimpanan hosting dikosongkan.',
        );

        return redirect()->route('administrasi.server')->with('success', 'Kuota penyimpanan tersimpan.');
    }

    public function simpanPeringatan(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        $aktif = $request->boolean('peringatan_aktif');
        $hapusToken = $request->boolean('hapus_token');
        $adaToken = $this->pemantau->pengaturan()['token'] !== '';

        $data = $request->validate([
            'peringatan_aktif' => ['nullable', 'boolean'],
            'nomor_penerima' => ['nullable', 'string', 'max:500', Rule::requiredIf($aktif), $this->aturanNomor()],
            'wa_gateway' => ['required', Rule::in(array_keys(PengirimWhatsapp::GATEWAY))],
            'wa_token' => [
                'nullable', 'string', 'max:255', 'regex:/^\S+$/',
                Rule::requiredIf($aktif && (! $adaToken || $hapusToken)),
            ],
            'hapus_token' => ['nullable', 'boolean'],
            'wa_url_wablas' => ['nullable', 'url:http,https', 'max:255', Rule::requiredIf($request->input('wa_gateway') === 'wablas')],
            'peringatan_ulang_menit' => ['required', 'integer', 'min:15', 'max:1440'],
        ], [
            'nomor_penerima.required' => 'Isi sedikitnya satu nomor WhatsApp penerima peringatan.',
            'wa_token.required' => 'Isi token dari dasbor gateway WhatsApp.',
            'wa_token.regex' => 'Token tidak boleh memuat spasi.',
            'wa_url_wablas.required' => 'Isi alamat peladen Wablas, misalnya https://tegal.wablas.com.',
            'wa_url_wablas.url' => 'Alamat peladen Wablas harus berupa URL lengkap yang diawali https://.',
            'peringatan_ulang_menit.min' => 'Jeda pengingat sedikitnya 15 menit.',
            'peringatan_ulang_menit.max' => 'Jeda pengingat paling lama 1.440 menit (sehari).',
        ]);

        $nomor = PengirimWhatsapp::daftarNomor($data['nomor_penerima'] ?? '');

        $this->pemantau->simpanPengaturan([
            'aktif' => $aktif,
            'nomor' => $nomor,
            'gateway' => $data['wa_gateway'],
            'url_wablas' => trim((string) ($data['wa_url_wablas'] ?? '')),
            'ulang_menit' => (int) $data['peringatan_ulang_menit'],
        ], $data['wa_token'] ?? null, $hapusToken);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            $aktif
                ? 'Peringatan WhatsApp pemantau server diaktifkan ke '.count($nomor).' nomor lewat '.PengirimWhatsapp::GATEWAY[$data['wa_gateway']].'.'
                : 'Peringatan WhatsApp pemantau server dinonaktifkan.',
        );

        return redirect()->route('administrasi.server')->with('success', 'Pengaturan peringatan WhatsApp tersimpan.');
    }

    /**
     * Kirim pesan uji ke nomor yang tersimpan — memastikan token dan nomor
     * benar sebelum gangguan sungguhan terjadi.
     */
    public function kirimUji(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        $hasil = $this->pemantau->kirimUji();

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            ($hasil['terkirim'] ? 'Pesan uji peringatan WhatsApp terkirim. ' : 'Pesan uji peringatan WhatsApp gagal. ').$hasil['pesan'],
        );

        return redirect()->route('administrasi.server')->with(
            $hasil['terkirim'] ? 'success' : 'error',
            $hasil['terkirim'] ? 'Pesan uji terkirim. '.$hasil['pesan'] : 'Pesan uji gagal: '.$hasil['pesan'],
        );
    }

    /**
     * Tiap nomor yang ditulis harus dapat dibaca sebagai nomor WhatsApp.
     */
    private function aturanNomor(): Closure
    {
        return function (string $atribut, mixed $nilai, Closure $gagal): void {
            foreach (preg_split('/[\r\n,;]+/', (string) $nilai) ?: [] as $potongan) {
                if (blank(trim($potongan))) {
                    continue;
                }

                $nomor = PengirimWhatsapp::normalkan($potongan);

                if ($nomor === null || strlen($nomor) < 10 || strlen($nomor) > 15) {
                    $gagal('Nomor "'.trim($potongan).'" tidak dikenali sebagai nomor WhatsApp.');
                }
            }
        };
    }

    /**
     * Pertambahan ukuran sejak catatan yang paling dekat dengan N hari lalu.
     *
     * @param  Collection<int, CatatanPenyimpanan>  $catatan  Terbaru lebih dulu.
     * @return array{byte: int, sejak: string}|null
     */
    private function pertumbuhan(Collection $catatan, int $hari): ?array
    {
        $terbaru = $catatan->first();

        if (! $terbaru || $catatan->count() < 2) {
            return null;
        }

        $batas = $terbaru->tanggal->copy()->subDays($hari);
        $pembanding = $catatan->first(fn (CatatanPenyimpanan $satu) => $satu->tanggal->lte($batas)) ?? $catatan->last();

        return [
            'byte' => $terbaru->total_byte - $pembanding->total_byte,
            'sejak' => $pembanding->tanggal->translatedFormat('d M Y'),
        ];
    }

    private function pastikanAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Pemantauan Server hanya untuk super administrator.');
    }
}
