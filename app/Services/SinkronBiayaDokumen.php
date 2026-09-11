<?php

namespace App\Services;

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
    public function __construct(private NotifikasiService $notifikasi) {}

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

        $tersimpan = $keuangan->rincianBiaya()
            ->where('sumber', RincianBiaya::SUMBER_DOKUMEN)
            ->get()
            ->keyBy('kunci_sumber');

        foreach ($diinginkan as $kunci => $baris) {
            $lama = $tersimpan->get($kunci);

            if (! $lama) {
                $keuangan->rincianBiaya()->create($baris + [
                    'sumber' => RincianBiaya::SUMBER_DOKUMEN,
                    'kunci_sumber' => $kunci,
                ]);
                $hasil['ditambah']++;

                continue;
            }

            // Nominal yang berubah setelah divalidasi harus diperiksa ulang.
            // Tanpa ini, pelaksana dapat menaikkan angka yang sudah disetujui
            // dan perubahannya lolos tanpa dilihat siapa pun.
            $berubah = (float) $lama->jumlah !== (float) $baris['jumlah']
                || $lama->komponen !== $baris['komponen'];

            $lama->update($baris + ($berubah ? ['divalidasi_at' => null, 'id_validator' => null] : []));

            if ($lama->wasChanged()) {
                $hasil['diperbarui']++;
            }
        }

        // Nominal yang dikosongkan pelaksana ikut dicabut dari rincian.
        $usang = $tersimpan->keys()->diff(array_keys($diinginkan));

        if ($usang->isNotEmpty()) {
            $hasil['dihapus'] = $keuangan->rincianBiaya()
                ->where('sumber', RincianBiaya::SUMBER_DOKUMEN)
                ->whereIn('kunci_sumber', $usang->all())
                ->delete();
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
        $peserta = $usulan->peserta()->where('id_user', $usulan->id_user)->first()
            ?? $usulan->peserta()->orderBy('id')->first();

        if (! $peserta) {
            return;
        }

        $daftar = DaftarRiil::firstOrCreate(
            ['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id],
            ['total_riil' => 0],
        );

        // Daftar yang sudah ditandatangani tidak boleh berubah diam-diam.
        if ($daftar->sudah_ditandatangani) {
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

        $baris = [];

        foreach ($usulan->tiket as $tiket) {
            if (! ($tiket->harga > 0)) {
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

        if ($dokumen && $dokumen->bill_hotel_nominal > 0) {
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
