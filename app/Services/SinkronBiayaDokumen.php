<?php

namespace App\Services;

use App\Enums\IsianBiaya;
use App\Enums\KategoriBiaya;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\Notifikasi;
use App\Models\RincianBiaya;
use App\Models\RincianDaftarRiil;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Support\Collection;

/**
 * Menyalin nominal pada berkas pertanggungjawaban ke rincian biaya.
 *
 * Angka yang diketik pelaksana masuk sebagai usulan angka, belum sebagai
 * angka resmi: barisnya bertanda sumber "dokumen" dan menunggu validasi tim
 * keuangan. Dengan begitu pelaksana cukup mengetik sekali, sementara
 * keputusan berapa yang dibayarkan tetap di tangan tim keuangan.
 */
class SinkronBiayaDokumen
{
    public function __construct(
        private NotifikasiService $notifikasi,
        private PemegangNominal $pemegang,
    ) {}

    /**
     * Selaraskan seluruh nominal sebuah usulan.
     *
     * @return array{ditambah: int, diperbarui: int, dihapus: int}
     */
    public function selaraskan(Usulan $usulan): array
    {
        // Lapis penjagaan kedua: rincian yang sudah ditandatangani tidak
        // ditulis ulang meski penyelarasan terpanggil dari jalur lain.
        if (app(PenguncianBerkas::class)->rincianBiaya($usulan)) {
            $this->selaraskanDaftarRiil($usulan);

            return ['ditambah' => 0, 'diperbarui' => 0, 'dihapus' => 0];
        }

        $keuangan = $this->keuangan($usulan);
        $diinginkan = $this->barisDariDokumen($usulan);

        $hasil = ['ditambah' => 0, 'diperbarui' => 0, 'dihapus' => 0];

        // Termasuk yang dihapus tim keuangan: baris itu tidak disalin lagi
        // selama pelaksana tidak mengubah nominalnya.
        $tersimpan = $keuangan->rincianBiaya()
            ->withTrashed()
            ->where('sumber', RincianBiaya::SUMBER_DOKUMEN)
            ->get()
            ->keyBy('kunci_sumber');

        foreach ($diinginkan as $kunci => $baris) {
            $lama = $tersimpan->get($kunci);

            if (! $lama) {
                $keuangan->rincianBiaya()->create($baris + [
                    'sumber' => RincianBiaya::SUMBER_DOKUMEN,
                    'kunci_sumber' => $kunci,
                    'isi_berkas' => $baris,
                ]);
                $hasil['ditambah']++;

                continue;
            }

            // Baris dari sebelum pembanding ini ada diadopsi apa adanya:
            // isinya bisa jadi sudah dikoreksi tim keuangan.
            if ($lama->isi_berkas === null) {
                $lama->update(['isi_berkas' => $baris]);

                continue;
            }

            // Hanya kolom yang diubah pelaksana sejak penyalinan terakhir yang
            // ditulis; koreksi tim keuangan atas kolom lain tetap bertahan.
            $diubah = array_filter(
                $baris,
                fn ($nilai, string $kolom) => ! $this->sama($lama->isi_berkas[$kolom] ?? null, $nilai),
                ARRAY_FILTER_USE_BOTH,
            );

            if ($diubah === []) {
                continue;
            }

            // Nominal yang berubah setelah divalidasi harus diperiksa ulang.
            // Tanpa ini, pelaksana dapat menaikkan angka yang sudah disetujui
            // dan perubahannya lolos tanpa dilihat siapa pun.
            $periksaUlang = array_key_exists('jumlah', $diubah) || array_key_exists('komponen', $diubah);

            // Baris yang dihapus tim keuangan kembali tampil bila pelaksana
            // mengubah nominalnya: angka baru itu perlu diperiksa.
            if ($lama->trashed() && $periksaUlang) {
                $lama->restore();
            }

            $lama->update($diubah + ['isi_berkas' => $baris]
                + ($periksaUlang ? ['divalidasi_at' => null, 'id_validator' => null] : []));
            $hasil['diperbarui']++;
        }

        // Nominal yang dikosongkan pelaksana ikut dicabut dari rincian.
        $usang = $tersimpan->keys()->diff(array_keys($diinginkan));

        if ($usang->isNotEmpty()) {
            $hasil['dihapus'] = $keuangan->rincianBiaya()
                ->withTrashed()
                ->where('sumber', RincianBiaya::SUMBER_DOKUMEN)
                ->whereIn('kunci_sumber', $usang->all())
                ->forceDelete();
        }

        $keuangan->hitungTotal();
        $this->selaraskanDaftarRiil($usulan);

        return $hasil;
    }

    /**
     * Salin nota transportasi lokal ke Daftar Pengeluaran Riil.
     *
     * Biaya transport tidak masuk rincian biaya karena bukan tagihan
     * berdasarkan kuitansi resmi; ia dinyatakan sendiri oleh pelaksana pada
     * daftar riil, lalu disetujui PPK.
     */
    public function selaraskanDaftarRiil(Usulan $usulan): void
    {
        $daftar = $this->daftarRiilPelaksana($usulan);

        // Daftar yang sudah ditandatangani tidak boleh berubah diam-diam.
        if (! $daftar || $daftar->sudah_ditandatangani) {
            return;
        }

        $usulan->loadMissing('notaTransport');

        $tersimpan = $daftar->rincian()
            ->where('sumber', RincianDaftarRiil::SUMBER_DOKUMEN)
            ->get()
            ->keyBy('kunci_sumber');

        $urutan = 0;
        $dipakai = [];

        foreach ($usulan->notaTransport as $nota) {
            if (! $nota->terisi()) {
                continue;
            }

            $kunci = 'nota:'.$nota->urutan;
            $dipakai[] = $kunci;

            $daftar->rincian()->updateOrCreate(
                ['kunci_sumber' => $kunci],
                [
                    'urutan' => ++$urutan,
                    'uraian' => $nota->label,
                    'nominal' => (float) $nota->nominal,
                    'sumber' => RincianDaftarRiil::SUMBER_DOKUMEN,
                ],
            );
        }

        $usang = $tersimpan->keys()->diff($dipakai);

        if ($usang->isNotEmpty()) {
            $daftar->rincian()
                ->where('sumber', RincianDaftarRiil::SUMBER_DOKUMEN)
                ->whereIn('kunci_sumber', $usang->all())
                ->delete();
        }

        // Dipaksa: bila seluruh nota dicabut, totalnya memang harus nol —
        // bukan menyisakan angka lama yang tak lagi berdasar.
        $daftar->hitungTotal(paksa: true);
        $this->ajukanKeTimKeuangan($daftar->fresh(), $usulan);
    }

    /**
     * Daftar Pengeluaran Riil milik pelaksana utama usulan — tempat transport
     * lokal dicatat, dari nota pelaksana maupun tulisan tim keuangan.
     *
     * Null bila usulan belum punya peserta sama sekali.
     */
    public function daftarRiilPelaksana(Usulan $usulan): ?DaftarRiil
    {
        $peserta = $usulan->peserta()->where('id_user', $usulan->id_user)->first()
            ?? $usulan->peserta()->orderBy('id')->first();

        if (! $peserta) {
            return null;
        }

        return DaftarRiil::firstOrCreate(
            ['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id],
            ['total_riil' => 0],
        );
    }

    /**
     * Berkas pertanggungjawaban yang baru diisi pelaksana berjalan ke tim
     * keuangan, bukan langsung ke PPK: merekalah yang memeriksa nominalnya
     * dan memisahkan transport lokal dari komponen lainnya sebelum kedua
     * dokumen dikirimkan kembali kepada pelaksana.
     *
     * Pemberitahuan dikirim sekali saja: tanpa penjagaan ini, tiap kali
     * pelaksana menyunting notanya tim keuangan menerima pesan yang sama.
     */
    private function ajukanKeTimKeuangan(DaftarRiil $daftar, Usulan $usulan): void
    {
        if ($daftar->diajukan_at !== null) {
            return;
        }

        $daftar->update(['diajukan_at' => now()]);

        $this->notifikasi->kirimKePeran(
            [User::ROLE_TIM_KEUANGAN, User::ROLE_BENDAHARA],
            'Berkas pertanggungjawaban menunggu verifikasi',
            'Berkas pertanggungjawaban '.$usulan->no_usulan.' dari '
                .($usulan->user?->nama ?? 'pelaksana')
                .' sudah terisi dan menunggu pemeriksaan Anda sebelum dikirim kembali kepada pelaksana.',
            [
                'usulan' => $usulan,
                'tipe' => Notifikasi::TIPE_PERINGATAN,
                'url' => route('keuangan.detail', $usulan->no_usulan),
            ],
        );
    }

    /**
     * Baris rincian yang seharusnya ada berdasarkan isi berkas, dikunci
     * per asal datanya.
     *
     * @return array<string, array<string, mixed>>
     */
    public function barisDariDokumen(Usulan $usulan): array
    {
        $usulan->loadMissing('tiket', 'notaTransport', 'dokumen');

        // Komponen yang nominalnya ditetapkan tim keuangan sudah punya
        // barisnya sendiri; nominal pelaksana untuk komponen itu tidak
        // disalin agar tidak tercatat dua kali.
        $dariKeuangan = $this->pemegang->dariKeuangan($usulan);
        $ditetapkanKeuangan = fn (IsianBiaya $isian) => isset($dariKeuangan[$isian->value]);

        $baris = [];

        foreach ($usulan->tiket as $tiket) {
            if (! ($tiket->harga > 0) || $ditetapkanKeuangan(IsianBiaya::dariArah($tiket->arah))) {
                continue;
            }

            // Nama komponen membawa kode bookingnya sendiri: dokumen rincian
            // biaya dibaca lepas dari aplikasi, jadi nomor yang menautkannya
            // ke tiket harus ikut tercetak pada baris itu.
            $nama = $tiket->arah->label();

            if ($tiket->kode_booking) {
                $nama .= ' (Kode Booking '.$tiket->kode_booking.')';
            }

            $baris['tiket:'.$tiket->arah->value] = $this->baris(
                KategoriBiaya::Transport,
                $nama,
                'OK',
                (float) $tiket->harga,
                trim($tiket->rute().' · '.($tiket->nomor_tiket ?? ''), ' ·'),
            );
        }

        // Biaya transport lokal sengaja tidak masuk rincian biaya: ia
        // dipertanggungjawabkan lewat Daftar Pengeluaran Riil, sesuai
        // Lampiran IX PMK 113/2012. Lihat selaraskanDaftarRiil().

        $dokumen = $usulan->dokumen->last();

        // Penginapan yang sudah termasuk biaya penyelenggaraan tidak disalin
        // sebagai uang penginapan, supaya hotel yang sama tidak terbayar dua kali.
        if ($dokumen && $dokumen->bill_hotel_nominal > 0 && ! $dokumen->penginapanTermasukPenyelenggaraan() && ! $ditetapkanKeuangan(IsianBiaya::Penginapan)) {
            // Sebutan resmi pada dokumen rincian: uang penginapan, bukan biaya hotel.
            $nama = 'Uang Penginapan';

            if ($dokumen->bill_hotel_no_transaksi) {
                $nama .= ' (No. Transaksi '.$dokumen->bill_hotel_no_transaksi.')';
            }

            // Lama menginap ikut tercetak agar pemeriksa dapat menakar
            // kewajaran nominalnya tanpa membuka SPD.
            $hari = max(1, (int) $usulan->durasi);

            $baris['hotel'] = $this->baris(
                KategoriBiaya::Penginapan,
                $nama,
                'hari',
                (float) $dokumen->bill_hotel_nominal,
                null,
                $hari,
            );
        }

        // Biaya penyelenggaraan hanya bila pelaksana menyatakan ada; nomor
        // invoice ikut pada nama komponen supaya cetakannya dapat ditelusuri
        // ke bukti bayarnya.
        if ($dokumen?->adaPenyelenggaraan() && $dokumen->penyelenggaraan_nominal > 0 && ! $ditetapkanKeuangan(IsianBiaya::Penyelenggaraan)) {
            $nama = 'Biaya Penyelenggaraan';

            if ($dokumen->penyelenggaraan_invoice) {
                $nama .= ' (No. Invoice '.$dokumen->penyelenggaraan_invoice.')';
            }

            $baris['penyelenggaraan'] = $this->baris(
                KategoriBiaya::Penyelenggaraan,
                $nama,
                'paket',
                (float) $dokumen->penyelenggaraan_nominal,
                null,
            );
        }

        return $baris;
    }

    /**
     * Total nominal transportasi lokal yang sudah diinput pelaksana.
     */
    public function totalNotaTransport(Usulan $usulan): float
    {
        return (float) $usulan->notaTransport()->sum('nominal');
    }

    /**
     * Baris rincian dari dokumen yang belum diperiksa tim keuangan.
     *
     * @return Collection<int, RincianBiaya>
     */
    public function menungguValidasi(Usulan $usulan): Collection
    {
        if (! $usulan->keuangan) {
            return collect();
        }

        return $usulan->keuangan->rincianBiaya()
            ->where('sumber', RincianBiaya::SUMBER_DOKUMEN)
            ->whereNull('divalidasi_at')
            ->get();
    }

    /**
     * Seluruh nominal dari dokumen sudah diperiksa, sehingga rincian biayanya
     * boleh diteruskan ke pelaksana untuk masa sanggah.
     */
    public function seluruhnyaTervalidasi(Usulan $usulan): bool
    {
        return $this->menungguValidasi($usulan)->isEmpty();
    }

    /**
     * Dua isian berkas sama — angka dibandingkan sebagai angka, supaya
     * 1200000 dan 1200000.0 tidak terbaca berubah.
     */
    private function sama(mixed $lama, mixed $baru): bool
    {
        return is_numeric($lama) && is_numeric($baru)
            ? (float) $lama === (float) $baru
            : $lama === $baru;
    }

    private function keuangan(Usulan $usulan): Keuangan
    {
        return $usulan->keuangan ?? $usulan->keuangan()->create([
            'total' => 0,
            'uang_muka' => 0,
            'sisa' => 0,
            'status' => Keuangan::STATUS_BELUM,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  int|null  $volume  Banyaknya satuan yang tercetak pada dokumen.
     *                            Diisi hanya untuk komponen yang memang
     *                            dihitung per satuan, seperti lama menginap.
     */
    private function baris(
        KategoriBiaya $kategori,
        string $komponen,
        string $satuan,
        float $nominal,
        ?string $keterangan,
        ?int $volume = null,
    ): array {
        return [
            'kategori' => $kategori->value,
            'komponen' => $komponen,
            // Nominalnya sudah berupa jumlah akhir dari nota, bukan hasil
            // kali volume, jadi harga satuannya dibagi rata atas volumenya.
            'volume' => $volume ?? 1,
            'satuan' => $satuan,
            'harga_satuan' => $volume ? $nominal / $volume : $nominal,
            'jumlah' => $nominal,
            'keterangan' => $keterangan ?: null,
        ];
    }
}
