<?php

namespace App\Http\Controllers;

use App\Enums\KategoriBiaya;
use App\Models\DaftarRiil;
use App\Models\RincianBiaya;
use App\Models\Usulan;
use App\Services\JalurPersetujuan;
use App\Services\PemantauBerkas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * "Rincian Saya" — berkas keuangan milik pengguna yang sedang berjalan.
 *
 * Rincian biaya perjalanan dinas dan daftar pengeluaran riil adalah dua
 * dokumen berbeda, jadi masing-masing bermenu sendiri dan disikapi
 * sendiri: pelaksana boleh menandatangani yang satu dan menyanggah yang
 * lain.
 */
class RincianSayaController extends Controller
{
    public function __construct(private PemantauBerkas $pemantau) {}

    /**
     * Kelompok kerja pada kedua daftar, sekaligus label tabnya.
     *
     * @var array<string, string>
     */
    private const KELOMPOK = [
        'perlu-tanggapan' => 'Perlu Tanggapan',
        'dicek' => 'Dicek Tim Keuangan',
        'menunggu-ppk' => 'Menunggu PPK',
        'disanggah' => 'Disanggah',
        'selesai' => 'Selesai',
        'lainnya' => 'Lainnya',
    ];

    /**
     * Daftar pengeluaran riil transportasi milik pengguna ini.
     *
     * Hanya dokumen transportasinya — rincian biaya perjalanan dinas punya
     * submenunya sendiri.
     */
    public function daftarRiil(Request $request): View
    {
        return view('rincian-saya.daftar-riil', $this->bekal(
            $request,
            JalurPersetujuan::RIIL,
            route('rincian-saya.daftar-riil'),
        ));
    }

    /**
     * Rincian biaya perjalanan dinas — seluruh komponen kecuali transport
     * lokal, yang sudah pindah ke daftar pengeluaran riil.
     */
    public function rincianBiaya(Request $request): View
    {
        return view('rincian-saya.rincian-biaya', $this->bekal(
            $request,
            JalurPersetujuan::RINCIAN,
            route('rincian-saya.rincian-biaya'),
        ));
    }

    /**
     * Isi halaman untuk satu jenis dokumen: barisnya, tab kelompoknya, dan
     * saringan periodenya.
     *
     * @return array<string, mixed>
     */
    private function bekal(Request $request, string $jenis, string $aksi): array
    {
        $semua = $this->berkasMilik($request, $jenis);

        $kelompok = $request->input('kelompok');
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');

        // Tab dihitung dari seluruh berkas, saringan periode dari yang
        // tersisa — supaya angkanya menjawab pertanyaan yang berbeda.
        $jumlah = collect(self::KELOMPOK)
            ->map(fn (string $label, string $kunci) => $semua->where('kelompok', $kunci)->count())
            ->all();

        $tampil = $semua
            ->when($kelompok, fn (Collection $baris) => $baris->where('kelompok', $kelompok))
            ->when($tahun, fn (Collection $baris) => $baris->filter(
                fn (array $b) => $b['tanggal']?->year === (int) $tahun
            ))
            ->when($bulan, fn (Collection $baris) => $baris->filter(
                fn (array $b) => $b['tanggal']?->month === (int) $bulan
            ))
            ->values();

        return [
            'jenis' => $jenis,
            'aksi' => $aksi,
            'cari' => $request->input('cari'),
            'daftar' => $tampil->groupBy('periode'),
            'kelompok' => $kelompok,
            'labelKelompok' => self::KELOMPOK,
            'jumlah' => $jumlah,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'tahunTersedia' => $semua->pluck('tanggal')->filter()
                ->map(fn (Carbon $t) => $t->year)->unique()->sortDesc()->values(),
            'jumlahBulan' => $semua
                ->when($tahun, fn (Collection $baris) => $baris->filter(
                    fn (array $b) => $b['tanggal']?->year === (int) $tahun
                ))
                ->pluck('tanggal')->filter()
                ->countBy(fn (Carbon $t) => $t->month),
        ];
    }

    /**
     * Berkas milik pengguna ini yang sudah disentuh tim keuangan — dikirim
     * kepadanya, atau sekurangnya sudah diperiksa nominalnya — dilengkapi
     * keadaan jalur dan penanda periodenya.
     *
     * Yang baru diperiksa tetapi belum dikirim ikut tampil supaya pelaksana
     * tahu berkasnya sudah dicek dan tinggal menunggu dikirim; sebelumnya
     * berkas seperti itu tidak terlihat sama sekali dari sisi pelaksana.
     *
     * Tiap baris membawa panel pemantauannya — seluruh tanda tangan dan
     * pembayaran berkas itu — supaya pelaksana tahu berkasnya berhenti di
     * mana tanpa bertanya ke tim keuangan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function berkasMilik(Request $request, string $jenis): Collection
    {
        $cari = $request->input('cari');

        $berkas = DaftarRiil::with(['usulan.keuangan.rincianBiaya', 'peserta', 'rincian'])
            ->where(fn ($q) => $q
                ->whereNotNull('dikirim_ke_pegawai_at')
                ->orWhereNotNull('divalidasi_at')
                ->orWhereHas('usulan.keuangan.rincianBiaya', fn ($r) => $r->whereNotNull('divalidasi_at')))
            ->whereHas('peserta', fn ($q) => $q->where('id_user', $request->user()->id))
            ->when($cari, fn ($q) => $q->whereHas(
                'usulan',
                fn ($u) => $u->where('no_usulan', 'like', "%{$cari}%")
                    ->orWhere('no_tugas', 'like', "%{$cari}%")
                    ->orWhere('lokasi', 'like', "%{$cari}%")
            ))
            ->orderByDesc('dikirim_ke_pegawai_at')
            ->orderByDesc('updated_at')
            ->get();

        $this->pemantau->siapkan($berkas);

        return $berkas
            ->map(function (DaftarRiil $berkas) use ($jenis): array {
                $jalur = $berkas->jalur($jenis);
                $mulai = $berkas->usulan?->tanggal_mulai
                    ? Carbon::parse($berkas->usulan->tanggal_mulai)
                    : null;

                return [
                    'berkas' => $berkas,
                    'jalur' => $jalur,
                    'kelompok' => $jalur->kelompok(),
                    'tanggal' => $mulai,
                    'periode' => $mulai?->translatedFormat('F Y') ?? 'Tanpa Tanggal',
                    'rincian' => $jenis === JalurPersetujuan::RINCIAN
                        ? $this->rincianTanpaTransportLokal($berkas->usulan)
                        : $berkas->rincian,
                    'pantau' => $this->pemantau->untuk($berkas),
                ];
            });
    }

    /**
     * Baris rincian biaya, tanpa transport lokal yang sudah masuk daftar riil.
     *
     * @return Collection<int, RincianBiaya>
     */
    private function rincianTanpaTransportLokal(?Usulan $usulan): Collection
    {
        return ($usulan?->keuangan?->rincianBiaya ?? collect())
            ->reject(fn ($baris) => $baris->kategori === KategoriBiaya::TransportLokal)
            ->sortBy(fn ($baris) => $baris->kategori?->urutan() ?? 99)
            ->values();
    }
}
