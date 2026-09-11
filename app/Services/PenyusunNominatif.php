<?php

namespace App\Services;

use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\RincianBiaya;
use App\Models\Usulan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun daftar nominatif pembayaran perjalanan dinas per surat tugas,
 * mengikuti format lembar "Nominatif" pada berkas rekap yang dipakai
 * bagian keuangan.
 *
 * Barisnya diturunkan, tidak disimpan: angka pada daftar nominatif selalu
 * sama dengan angka pada rincian biaya yang sudah divalidasi tim keuangan,
 * sehingga keduanya tidak pernah berselisih.
 *
 * Satu surat tugas satu daftar. Daftarnya terbit begitu pelaksana pertama
 * di bawah surat tugas itu tuntas, dan hanya memuat pelaksana yang sudah
 * tuntas; kawan seperjalanan yang menyusul belakangan otomatis bertambah
 * ke daftar yang sama — bukan menahan daftar seluruh rombongan.
 */
class PenyusunNominatif
{
    /**
     * Relasi yang dibutuhkan untuk menyusun satu baris nominatif.
     *
     * @var list<string>
     */
    private const RELASI_BARIS = ['user', 'spd.pelaksana', 'peserta', 'keuangan.rincianBiaya', 'daftarRiil'];

    /**
     * Tambahan yang dibaca PenagihDokumen saat menilai kelengkapan berkas.
     *
     * @var list<string>
     */
    private const RELASI_KELENGKAPAN = ['dokumen', 'tiket', 'notaTransport', 'laporan', 'kategoriPerjadin'];

    public function __construct(private PenagihDokumen $penagih) {}

    /**
     * Usulan di bawah satu surat tugas, lengkap dengan relasi yang
     * dibutuhkan barisnya.
     *
     * @return Collection<int, Usulan>
     */
    public function usulanSuratTugas(string $noTugas): Collection
    {
        return Usulan::with([...self::RELASI_BARIS, ...self::RELASI_KELENGKAPAN])
            ->where('no_tugas', $noTugas)
            ->orderBy('id')
            ->get();
    }

    /**
     * Pelaksana di bawah surat tugas ini yang belum tercantum pada
     * daftarnya: berkasnya belum lengkap atau belum ditandatangani PPK.
     * Usulan yang ditolak bukan menunggu — ia tidak akan pernah tercantum.
     *
     * @return Collection<int, string>
     */
    public function menunggu(string $noTugas): Collection
    {
        return $this->namaMenunggu($this->usulanSuratTugas($noTugas));
    }

    /**
     * Pelaksana yang belum tercantum, untuk sekumpulan surat tugas
     * sekaligus.
     *
     * @param  iterable<int, string>  $noTugas
     * @return Collection<string, Collection<int, string>>
     */
    public function menungguBanyak(iterable $noTugas): Collection
    {
        return $this->usulanBanyakSuratTugas($noTugas, [...self::RELASI_BARIS, ...self::RELASI_KELENGKAPAN])
            ->map(fn (Collection $usulan) => $this->namaMenunggu($usulan));
    }

    /**
     * @param  Collection<int, Usulan>  $usulan
     * @return Collection<int, string>
     */
    private function namaMenunggu(Collection $usulan): Collection
    {
        return $usulan
            ->reject(fn (Usulan $item) => $this->usulanSiap($item))
            ->reject(fn (Usulan $item) => StatusUsulan::dari($item->status) === StatusUsulan::Ditolak)
            ->map(fn (Usulan $item) => $item->user?->nama ?? $item->peserta->first()?->nama ?? $item->no_usulan)
            ->values();
    }

    /**
     * Satu baris per pelaksana, dengan kolom persis seperti lembar
     * nominatif: tiket PP, transport lokal, uang harian, uang penginapan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function baris(string $noTugas): Collection
    {
        return $this->barisKelompok($this->usulanSuratTugas($noTugas));
    }

    /**
     * Baris dari sekelompok usulan satu surat tugas. Hanya pelaksana yang
     * berkasnya tuntas yang tercantum; yang lain menyusul begitu berkasnya
     * ditandatangani PPK, dan nomor urutnya disusun dari yang tercantum.
     *
     * @param  Collection<int, Usulan>  $usulan
     * @return Collection<int, array<string, mixed>>
     */
    private function barisKelompok(Collection $usulan): Collection
    {
        return $usulan
            ->filter(fn (Usulan $item) => $this->usulanSiap($item))
            ->values()
            ->map(fn (Usulan $item, int $i) => $this->barisUsulan($item, $i + 1));
    }

    /**
     * Baris nominatif untuk sekumpulan surat tugas sekaligus.
     *
     * Memanggil baris() satu per satu berarti satu rangkaian kueri untuk
     * tiap surat tugas — pada halaman yang memuat belasan daftar nominatif
     * jumlahnya menumpuk cepat. Di sini seluruh usulannya diambil sekali,
     * lalu dikelompokkan menurut nomor surat tugasnya.
     *
     * @param  iterable<int, string>  $noTugas
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function barisBanyak(iterable $noTugas): Collection
    {
        return $this->usulanBanyakSuratTugas($noTugas, [...self::RELASI_BARIS, ...self::RELASI_KELENGKAPAN])
            ->map(fn (Collection $usulan) => $this->barisKelompok($usulan));
    }

    /**
     * Usulan sekumpulan surat tugas, dikelompokkan menurut nomornya.
     *
     * @param  iterable<int, string>  $noTugas
     * @param  list<string>  $relasi
     * @return Collection<string, Collection<int, Usulan>>
     */
    private function usulanBanyakSuratTugas(iterable $noTugas, array $relasi = self::RELASI_BARIS): Collection
    {
        $nomor = collect($noTugas)->filter()->unique()->values();

        if ($nomor->isEmpty()) {
            return collect();
        }

        return Usulan::with($relasi)
            ->whereIn('no_tugas', $nomor->all())
            ->orderBy('id')
            ->get()
            ->groupBy('no_tugas');
    }

    /**
     * @return array<string, mixed>
     */
    private function barisUsulan(Usulan $usulan, int $nomor): array
    {
        $spd = $usulan->spd;
        $rincian = $usulan->keuangan?->rincianBiaya ?? collect();

        $tiket = $this->jumlahKategori($rincian, KategoriBiaya::Transport);

        // Kolom Transport diambil dari daftar pengeluaran riil, bukan dari
        // rincian biaya: transport lokal memang dipertanggungjawabkan di
        // sana sejak ia keluar dari Lampiran II.
        $transport = (float) $usulan->daftarRiil->sum('total_riil');
        $harian = $this->rekapHarian($rincian, KategoriBiaya::UangHarian);
        $inap = $this->rekapHarian($rincian, KategoriBiaya::Penginapan);
        $lainnya = $this->jumlahKategori($rincian, KategoriBiaya::Lainnya);

        return [
            'nomor' => $nomor,
            'usulan' => $usulan,
            'nama' => $usulan->user?->nama ?? $usulan->peserta->first()?->nama ?? '—',
            'asal' => $spd?->tempat_berangkat ?? 'Manado',
            'tujuan' => $spd?->tempat_tujuan ?? $usulan->lokasi,
            'lamanya' => $spd?->lama_hari ?? $this->lamaHari($usulan),
            'berangkat' => $this->keTanggal($spd?->tanggal_berangkat ?? $usulan->tanggal_mulai),
            'kembali' => $this->keTanggal($spd?->tanggal_kembali ?? $usulan->tanggal_selesai),
            'maksud' => $spd?->maksud ?? $usulan->uraian,
            'no_sppd' => $this->nomorSppd($usulan),
            'tanggal_sppd' => $spd?->tanggal_surat,
            'no_tugas' => $usulan->no_tugas,
            'tanggal_tugas' => $spd?->tanggal_surat,
            'tiket' => $tiket,
            'transport' => $transport,
            'harian_hari' => $harian['hari'],
            'harian_biaya' => $harian['biaya'],
            'harian_jumlah' => $harian['jumlah'],
            'inap_hari' => $inap['hari'],
            'inap_biaya' => $inap['biaya'],
            'inap_jumlah' => $inap['jumlah'],
            'jumlah' => $tiket + $transport + $harian['jumlah'] + $inap['jumlah'] + $lainnya,
        ];
    }

    /**
     * Tanggal perjalanan tersimpan sebagai tanggal maupun untai,
     * tergantung sumbernya.
     */
    private function keTanggal(mixed $nilai): ?Carbon
    {
        if ($nilai instanceof Carbon) {
            return $nilai;
        }

        return $nilai ? Carbon::parse($nilai) : null;
    }

    /**
     * Nomor SPPD melekat pada pelaksananya, bukan pada surat: satu SPD
     * dapat memuat beberapa pelaksana dengan nomor masing-masing.
     */
    private function nomorSppd(Usulan $usulan): ?string
    {
        $pelaksana = $usulan->spd?->pelaksana;

        if (! $pelaksana) {
            return null;
        }

        return $pelaksana->firstWhere('id_user', $usulan->id_user)?->nomor_surat
            ?? $pelaksana->first()?->nomor_surat;
    }

    /**
     * @param  Collection<int, RincianBiaya>  $rincian
     */
    private function jumlahKategori(Collection $rincian, KategoriBiaya $kategori): float
    {
        return (float) $rincian
            ->filter(fn ($baris) => $baris->kategori === $kategori)
            ->sum('jumlah');
    }

    /**
     * Uang harian dan penginapan tampil terpisah sebagai hari × biaya,
     * bukan hanya totalnya.
     *
     * @param  Collection<int, RincianBiaya>  $rincian
     * @return array{hari: int, biaya: float, jumlah: float}
     */
    private function rekapHarian(Collection $rincian, KategoriBiaya $kategori): array
    {
        $baris = $rincian->filter(fn ($item) => $item->kategori === $kategori);

        return [
            'hari' => (int) $baris->sum('volume'),
            'biaya' => (float) ($baris->first()->harga_satuan ?? 0),
            'jumlah' => (float) $baris->sum('jumlah'),
        ];
    }

    private function lamaHari(Usulan $usulan): int
    {
        return (int) $usulan->durasi;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return array<string, float>
     */
    public function total(Collection $baris): array
    {
        $kolom = ['tiket', 'transport', 'harian_jumlah', 'inap_jumlah', 'jumlah'];

        return collect($kolom)
            ->mapWithKeys(fn (string $k) => [$k => (float) $baris->sum($k)])
            ->all();
    }

    /**
     * Daftar nominatif terbit begitu ada satu pelaksana di bawah surat
     * tugas itu yang berkas pertanggungjawabannya sudah ditandatangani PPK.
     *
     * Bukan sekadar dokumennya lengkap: rincian biaya dan daftar pengeluaran
     * riil harus sudah disahkan kedua belah pihak, sebab keduanyalah dasar
     * angka pada daftar nominatif ini. Pelaksana lain yang belum tuntas
     * tidak menahan daftarnya — ia belum tercantum, dan bertambah sendiri
     * ke daftar yang sama begitu berkasnya disahkan.
     */
    public function siapTerbit(string $noTugas): bool
    {
        return $this->kelompokSiapTerbit($this->usulanSuratTugas($noTugas));
    }

    /**
     * Seorang pelaksana tercantum pada daftar nominatif bila berkasnya
     * lengkap dan kedua dokumennya — rincian biaya dan daftar riil — sudah
     * ditandatangani PPK.
     */
    public function usulanSiap(Usulan $usulan): bool
    {
        $usulan->loadMissing(self::RELASI_KELENGKAPAN);

        return $this->penagih->lengkap($usulan) && $this->disahkanPpk($usulan);
    }

    /**
     * Rincian biaya dan daftar riil pelaksana ini sudah ditandatangani PPK
     * pada kedua jalurnya — pengesahan yang menjadi dasar angkanya.
     */
    public function disahkanPpk(Usulan $usulan): bool
    {
        $usulan->loadMissing('daftarRiil');

        return $usulan->daftarRiil->isNotEmpty()
            && $usulan->daftarRiil->every(
                fn ($daftar) => $daftar->sudah_ditandatangani
                    && $daftar->jalurRincian()->sudahDitandatangani()
            );
    }

    /**
     * Penilaian yang sama atas sekelompok usulan yang sudah dimuat, supaya
     * banyak surat tugas dapat dinilai tanpa menariknya satu per satu.
     *
     * @param  Collection<int, Usulan>  $usulan
     */
    private function kelompokSiapTerbit(Collection $usulan): bool
    {
        return $usulan->contains(fn (Usulan $item) => $this->usulanSiap($item));
    }

    /**
     * Nomor surat tugas yang layak terbit tapi daftarnya belum dibuat.
     *
     * @return Collection<int, string>
     */
    public function suratTugasSiap(): Collection
    {
        return $this->kelompokSiap()->keys()->values();
    }

    /**
     * Usulan tiap surat tugas yang layak terbit tapi daftarnya belum dibuat.
     *
     * @return Collection<string, Collection<int, Usulan>>
     */
    private function kelompokSiap(): Collection
    {
        $sudahTerbit = DaftarNominatif::pluck('no_tugas');

        // Calonnya disaring lebih dulu di basis data: surat tugas yang punya
        // setidaknya satu usulan dengan daftar riil bertanda tangan PPK pada
        // kedua jalur. Tanpa ini tiap surat tugas ditarik satu per satu hanya
        // untuk ditolak — dan justru yang belum lengkap itulah yang paling
        // banyak menumpuk. Saringannya sengaja longgar; penilaian sebenarnya
        // tetap di PHP.
        $calon = Usulan::whereNotNull('no_tugas')
            ->whereNotIn('no_tugas', $sudahTerbit)
            ->whereHas('daftarRiil')
            ->whereDoesntHave('daftarRiil', fn ($q) => $q
                ->whereNull('ditandatangani_at')
                ->orWhereNull('rincian_ditandatangani_at'))
            ->distinct()
            ->pluck('no_tugas');

        return $this
            ->usulanBanyakSuratTugas($calon, [...self::RELASI_BARIS, ...self::RELASI_KELENGKAPAN])
            ->filter(fn (Collection $usulan) => $this->kelompokSiapTerbit($usulan));
    }

    /**
     * Menerbitkan daftar untuk tiap surat tugas yang sudah lengkap.
     *
     * @return Collection<int, DaftarNominatif>
     */
    public function terbitkanYangSiap(): Collection
    {
        // Usulannya sudah dimuat saat menilai kelayakan, jadi tanggal surat
        // tugasnya dibaca dari situ — tidak perlu ditarik ulang per nomor.
        return $this->kelompokSiap()
            ->map(fn (Collection $usulan, string $no) => DaftarNominatif::firstOrCreate(
                ['no_tugas' => $no],
                ['tanggal_tugas' => $usulan->first()?->spd?->tanggal_surat],
            ))
            ->values();
    }

    public function terbitkan(string $noTugas): DaftarNominatif
    {
        $tanggal = $this->usulanSuratTugas($noTugas)->first()?->spd?->tanggal_surat;

        return DaftarNominatif::firstOrCreate(
            ['no_tugas' => $noTugas],
            ['tanggal_tugas' => $tanggal],
        );
    }
}
