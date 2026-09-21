<?php

namespace App\Services;

use App\Models\DaftarNominatif;
use App\Models\Usulan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pelacakan satu perjalanan dinas, dari usulan sampai pelunasan.
 *
 * Berbeda dari jejak audit — yang mencatat tiap tindakan siapa pun — daftar
 * ini hanya memuat tonggak yang benar-benar menggerakkan berkas: dibuat,
 * uang muka cair, dokumen masuk, laporan selesai, kedua dokumen
 * ditandatangani, nominatif disahkan PPK dan diterima tim keuangan, lalu
 * dibayar. Tiap tonggak menyebut waktunya dan berapa hari berselang dari
 * tonggak sebelumnya, dan yang belum terjadi tetap tampil sebagai langkah
 * yang menunggu — lengkap dengan sudah berapa hari menunggunya — supaya
 * jelas berkasnya berhenti di mana dan seberapa lama.
 *
 * Persetujuan PPK dan terbitnya SPD sengaja tidak menjadi tonggak: SPD
 * terbit sebelum usulan ada, dan persetujuan PPK melekat pada SPD yang
 * ditandatanganinya, jadi keduanya selalu sudah terlewati begitu usulan
 * dibuat dan tidak menambah keterangan apa pun.
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
        'user', 'dokumen', 'laporan', 'keuangan', 'daftarRiil.ppk',
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
     * @return Collection<int, array{judul: string, keterangan: string, waktu: ?CarbonInterface, oleh: ?string, selesai: bool, durasi: ?int, durasi_label: ?string}>
     */
    public function tonggak(Usulan $usulan): Collection
    {
        $usulan->loadMissing(self::RELASI);

        $keuangan = $usulan->keuangan;
        $riil = $usulan->daftarRiil->first();

        // Daftar nominatif terbit per surat tugas begitu satu pelaksana
        // tuntas; usulan ini baru tercantum di sana setelah berkasnya sendiri
        // ditandatangani PPK. Sebelum itu tonggak nominatifnya belum terlewati
        // meski daftarnya sudah ada.
        $nominatif = $this->keduanyaDitandatangani($riil) ? $this->nominatif($usulan) : null;

        return $this->lekatkanDurasi(collect([
            $this->langkah(
                'Usulan dibuat',
                'Pelaksana menyusun usulan perjalanan dinas.',
                $usulan->created_at,
                $usulan->user?->nama,
            ),
            $this->langkah(
                'Uang muka dibayarkan',
                'Seluruh komponen kecuali uang harian, ditambah 80% uang harian.',
                $this->keTanggal($keuangan?->tanggal_transfer),
                null,
            ),
            $this->langkah(
                'Dokumen pertanggungjawaban diunggah',
                'Tiket, nota transportasi, bill hotel, dan kuitansi penyelenggara / hotel.',
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
                'Daftar nominatif dikirim PPK ke tim keuangan sebagai dasar pembayaran.',
                $nominatif?->dikirim_at,
                null,
            ),
            $this->langkah(
                'Pelunasan dibayarkan',
                'Sisa 20% uang harian ditambah penggantian transport lokal.',
                $this->keTanggal($keuangan?->tanggal_pelunasan),
                null,
            ),
        ]));
    }

    /**
     * Lekatkan berapa hari tiap tonggak berselang dari tonggak sebelumnya.
     *
     * Tonggak yang sudah terlewati dihitung dari tonggak terlewati terakhir
     * sebelumnya; tonggak pertama yang belum terlewati dihitung sampai hari
     * ini — itulah berapa lama berkas sudah menunggu di situ. Tonggak yang
     * menunggu sesudahnya tidak diberi durasi: belum ada yang bisa diukur.
     *
     * Tonggak tidak selalu terlewati berurutan — dokumen bisa diunggah
     * sebelum uang muka cair — jadi selisih yang negatif dibulatkan ke nol,
     * bukan ditampilkan sebagai angka minus yang membingungkan.
     *
     * @param  Collection<int, array{judul: string, keterangan: string, waktu: ?CarbonInterface, oleh: ?string, selesai: bool}>  $tonggak
     * @return Collection<int, array{judul: string, keterangan: string, waktu: ?CarbonInterface, oleh: ?string, selesai: bool, durasi: ?int, durasi_label: ?string}>
     */
    private function lekatkanDurasi(Collection $tonggak): Collection
    {
        $sebelumnya = null;
        $menungguSudahDitandai = false;

        return $tonggak->map(function (array $langkah) use (&$sebelumnya, &$menungguSudahDitandai): array {
            $langkah['durasi'] = null;
            $langkah['durasi_label'] = null;

            if ($langkah['selesai']) {
                if ($sebelumnya !== null) {
                    $langkah['durasi'] = $this->selisihHari($sebelumnya, $langkah['waktu']);
                    $langkah['durasi_label'] = $this->labelDurasi($langkah['durasi']).' dari tahap sebelumnya';
                }

                $sebelumnya = $langkah['waktu'];

                return $langkah;
            }

            if (! $menungguSudahDitandai && $sebelumnya !== null) {
                $menungguSudahDitandai = true;
                $langkah['durasi'] = $this->selisihHari($sebelumnya, now());
                $langkah['durasi_label'] = 'Sudah menunggu '.$this->labelDurasi($langkah['durasi']);
            }

            return $langkah;
        });
    }

    private function selisihHari(CarbonInterface $dari, CarbonInterface $sampai): int
    {
        return max(0, (int) $dari->copy()->startOfDay()->diffInDays($sampai->copy()->startOfDay(), false));
    }

    /**
     * "di hari yang sama", "1 hari", "12 hari".
     */
    public function labelDurasi(int $hari): string
    {
        return $hari === 0 ? 'di hari yang sama' : "{$hari} hari";
    }

    /**
     * Berapa hari seluruh perjalanan berkas ini memakan waktu: dari tonggak
     * pertama sampai tonggak terlewati terakhir, atau sampai hari ini bila
     * masih ada yang menunggu.
     */
    public function lamaBerjalan(Usulan $usulan): int
    {
        $tonggak = $this->tonggak($usulan);
        $pertama = $tonggak->first()['waktu'] ?? null;

        if (! $pertama instanceof CarbonInterface) {
            return 0;
        }

        // Tonggak tidak selalu terlewati berurutan, jadi akhirnya adalah yang
        // paling belakangan — bukan sekadar yang tercantum terakhir.
        $akhir = $tonggak->every(fn (array $langkah) => $langkah['selesai'])
            ? $tonggak->max('waktu')
            : now();

        return $this->selisihHari($pertama, $akhir);
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
        return $riil?->waktuDisetujuiPelaksana();
    }

    private function keduanyaDitandatangani(mixed $riil): ?CarbonInterface
    {
        return $riil?->waktuDisahkanPpk();
    }
}
