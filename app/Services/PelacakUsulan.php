<?php

namespace App\Services;

use App\Models\DaftarNominatif;
use App\Models\Persetujuan;
use App\Models\Usulan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pelacakan satu perjalanan dinas, dari usulan sampai pelunasan.
 *
 * Berbeda dari jejak audit — yang mencatat tiap tindakan siapa pun — daftar
 * ini hanya memuat tonggak yang benar-benar menggerakkan berkas: dibuat,
 * disetujui, SPD terbit, dokumen masuk, laporan selesai, kedua dokumen
 * ditandatangani, nominatif disahkan PPK dan diterima tim keuangan, lalu
 * dibayar. Tiap tonggak menyebut waktunya, dan yang belum terjadi tetap
 * tampil sebagai langkah yang menunggu — supaya jelas berkasnya berhenti
 * di mana.
 *
 * Seluruh waktunya dibaca dari kolom yang memang menyimpannya, bukan dari
 * uraian jejak audit yang bisa berubah kata-katanya.
 */
class PelacakUsulan
{
    /**
     * Relasi yang dibaca tonggak, untuk dimuat sekaligus.
     *
     * @var list<string>
     */
    private const RELASI = [
        'user', 'spd', 'dokumen', 'laporan', 'keuangan',
        'daftarRiil.ppk', 'persetujuan.approver',
    ];

    /**
     * Daftar nominatif yang sudah dimuat, bertaut nomor surat tugasnya.
     *
     * Nominatif tidak berelasi langsung dengan usulan — tautannya lewat
     * nomor surat tugas — sehingga tanpa persiapan ini tiap usulan mencari
     * nominatifnya sendiri-sendiri.
     *
     * @var Collection<string, DaftarNominatif>|null
     */
    private ?Collection $nominatif = null;

    /**
     * Siapkan pelacakan untuk sekumpulan usulan sekaligus.
     *
     * Dipakai halaman daftar: seluruh relasi dan daftar nominatifnya dimuat
     * sekali, lalu tonggak tiap baris dibaca tanpa kueri tambahan.
     *
     * @param  Collection<int, Usulan>  $usulan
     */
    public function siapkan(Collection $usulan): void
    {
        $usulan->loadMissing(self::RELASI);

        $this->nominatif = DaftarNominatif::with('ppk')
            ->whereIn('no_tugas', $usulan->pluck('no_tugas')->filter()->unique()->all())
            ->get()
            ->keyBy('no_tugas');
    }

    /**
     * Tonggak perjalanan berkas ini, berurutan.
     *
     * @return Collection<int, array{judul: string, keterangan: string, waktu: ?CarbonInterface, oleh: ?string, selesai: bool}>
     */
    public function tonggak(Usulan $usulan): Collection
    {
        $usulan->loadMissing(self::RELASI);

        $keuangan = $usulan->keuangan;
        $riil = $usulan->daftarRiil->first();
        $nominatif = $this->nominatif($usulan);

        $putusan = $usulan->persetujuan
            ->firstWhere('keputusan', Persetujuan::KEPUTUSAN_SETUJU);

        return collect([
            $this->langkah(
                'Usulan dibuat',
                'Pelaksana menyusun usulan perjalanan dinas.',
                $usulan->created_at,
                $usulan->user?->nama,
            ),
            $this->langkah(
                'Disetujui PPK',
                'Usulan disahkan sehingga perjalanan boleh berjalan.',
                $putusan?->waktu_keputusan,
                $putusan?->approver?->nama,
            ),
            $this->langkah(
                'Surat Perjalanan Dinas terbit',
                'SPD diterbitkan berikut nomor suratnya.',
                $usulan->spd?->created_at,
                $usulan->spd?->dikeluarkan_di ? 'Dikeluarkan di '.$usulan->spd->dikeluarkan_di : null,
            ),
            $this->langkah(
                'Uang muka dibayarkan',
                'Seluruh komponen kecuali uang harian, ditambah 80% uang harian.',
                $this->keTanggal($keuangan?->tanggal_transfer),
                null,
            ),
            $this->langkah(
                'Dokumen pertanggungjawaban diunggah',
                'Tiket, nota transportasi, bill hotel, dan kuitansi.',
                $usulan->dokumen->last()?->updated_at,
                $usulan->user?->nama,
            ),
            $this->langkah(
                'Laporan perjalanan dinas selesai',
                'Uraian kegiatan dan rencana tindak lanjut dinyatakan lengkap.',
                $usulan->laporan?->diselesaikan_at,
                $usulan->user?->nama,
            ),
            $this->langkah(
                'Berkas dikirim tim keuangan ke pelaksana',
                'Rincian biaya dan daftar pengeluaran riil siap disikapi.',
                $riil?->dikirim_ke_pegawai_at,
                $riil?->validator?->nama,
            ),
            $this->langkah(
                'Ditandatangani pelaksana',
                'Kedua dokumen disetujui pelaksana perjalanan.',
                $this->keduanyaDisetujui($riil),
                $usulan->user?->nama,
            ),
            $this->langkah(
                'Ditandatangani PPK',
                'Rincian biaya dan daftar riil disahkan PPK.',
                $this->keduanyaDitandatangani($riil),
                $riil?->ppk?->nama,
            ),
            $this->langkah(
                'Daftar nominatif diverifikasi PPK',
                'Nominatif surat tugas ditandatangani sebagai dasar pembayaran.',
                $nominatif?->ditandatangani_at,
                $nominatif?->ppk?->nama,
            ),
            $this->langkah(
                'Nominatif diterima tim keuangan',
                'Daftar nominatif dikirim PPK dan menjadi dasar pelunasan.',
                $nominatif?->dikirim_at,
                null,
            ),
            $this->langkah(
                'Pelunasan dibayarkan',
                'Sisa 20% uang harian ditambah penggantian transport lokal.',
                $this->keTanggal($keuangan?->tanggal_pelunasan),
                null,
            ),
        ]);
    }

    /**
     * Berapa tonggak yang sudah terlewati dari seluruhnya.
     *
     * @return array{selesai: int, total: int, persen: int}
     */
    public function kemajuan(Usulan $usulan): array
    {
        $tonggak = $this->tonggak($usulan);
        $selesai = $tonggak->where('selesai', true)->count();
        $total = $tonggak->count();

        return [
            'selesai' => $selesai,
            'total' => $total,
            'persen' => $total > 0 ? (int) round($selesai / $total * 100) : 0,
        ];
    }

    /**
     * Tonggak pertama yang belum terlewati — di sinilah berkasnya berhenti.
     *
     * @return array<string, mixed>|null
     */
    public function langkahBerikutnya(Usulan $usulan): ?array
    {
        return $this->tonggak($usulan)->firstWhere('selesai', false);
    }

    /**
     * Nominatif surat tugas ini, dari yang sudah disiapkan bila ada.
     */
    private function nominatif(Usulan $usulan): ?DaftarNominatif
    {
        if (blank($usulan->no_tugas)) {
            return null;
        }

        if ($this->nominatif !== null) {
            return $this->nominatif->get($usulan->no_tugas);
        }

        return DaftarNominatif::with('ppk')->firstWhere('no_tugas', $usulan->no_tugas);
    }

    /**
     * @return array{judul: string, keterangan: string, waktu: ?CarbonInterface, oleh: ?string, selesai: bool}
     */
    private function langkah(string $judul, string $keterangan, mixed $waktu, ?string $oleh): array
    {
        $waktu = $waktu instanceof CarbonInterface ? $waktu : null;

        return [
            'judul' => $judul,
            'keterangan' => $keterangan,
            'waktu' => $waktu,
            'oleh' => $oleh,
            'selesai' => $waktu !== null,
        ];
    }

    /**
     * Tanggal pembayaran tersimpan sebagai tanggal, bukan waktu penuh.
     */
    private function keTanggal(mixed $nilai): ?CarbonInterface
    {
        if ($nilai instanceof CarbonInterface) {
            return $nilai;
        }

        return $nilai ? Carbon::parse($nilai) : null;
    }

    /**
     * Kedua dokumen dianggap disetujui pada saat yang terakhir disikapi.
     */
    private function keduanyaDisetujui(mixed $riil): ?CarbonInterface
    {
        if (! $riil) {
            return null;
        }

        $waktu = [$riil->disetujui_pegawai_at, $riil->rincian_disetujui_at];

        return in_array(null, $waktu, true) ? null : max($waktu);
    }

    private function keduanyaDitandatangani(mixed $riil): ?CarbonInterface
    {
        if (! $riil) {
            return null;
        }

        $waktu = [$riil->ditandatangani_at, $riil->rincian_ditandatangani_at];

        return in_array(null, $waktu, true) ? null : max($waktu);
    }
}
