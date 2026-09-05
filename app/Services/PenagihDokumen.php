<?php

namespace App\Services;

use App\Enums\ArahTiket;
use App\Models\Dokumen;
use App\Models\Usulan;
use Carbon\Carbon;

/**
 * Menyusun tautan WhatsApp untuk menagih kelengkapan berkas
 * pertanggungjawaban kepada pelaksana perjalanan dinas.
 *
 * Pesan dibuka di aplikasi WhatsApp milik penagih, bukan dikirim otomatis,
 * sehingga tim keuangan tetap membaca dan menyetujui isinya lebih dulu.
 */
class PenagihDokumen
{
    /**
     * Label yang dipahami pengguna untuk tiap kolom berkas.
     *
     * @var array<string, string>
     */
    private const LABEL = [
        'sppd' => 'SPPD bertanda tangan',
        'kwintasi' => 'Kuitansi',
        'bill_hotel' => 'Bill hotel',
    ];

    /**
     * Kelengkapan pertanggungjawaban yang belum dipenuhi sebuah usulan.
     *
     * Satu-satunya tempat "berkas lengkap" didefinisikan: pengingat, dashboard,
     * menu pembayaran, dan penutupan usulan semuanya bertanya ke sini. Bila
     * masing-masing memeriksa sendiri, pegawai akan ditagih berkas yang di
     * layar lain sudah dinyatakan lengkap.
     *
     * @return list<string>
     */
    public function berkasKurang(Usulan $usulan): array
    {
        $dokumen = $usulan->dokumen->last();

        // Dalam kota tidak melibatkan tiket, penginapan, maupun kuitansi:
        // yang dipertanggungjawabkan hanya SPD dan transport lokalnya.
        if ($usulan->dalamKota()) {
            return collect([filled($dokumen?->sppd) ? null : self::LABEL['sppd']])
                ->filter()
                ->concat($this->notaKurang($usulan))
                ->concat($this->laporanKurang($usulan))
                ->values()
                ->all();
        }

        $kurang = collect(Usulan::DOKUMEN_LPJ_WAJIB)
            ->reject(fn (string $kolom) => filled($dokumen?->{$kolom}))
            ->map(fn (string $kolom) => self::LABEL[$kolom] ?? $kolom)
            ->values();

        return $kurang
            ->concat($this->tiketKurang($usulan))
            ->concat($this->notaKurang($usulan))
            ->concat($this->rincianHotelKurang($dokumen))
            ->concat($this->laporanKurang($usulan))
            ->values()
            ->all();
    }

    /**
     * Checklist kelengkapan untuk ditampilkan, satu baris per berkas yang
     * memang diunggah pelaksana.
     *
     * Diturunkan dari aturan yang sama dengan berkasKurang() agar layar dan
     * penagihan tidak pernah berbeda: dulu checklist punya daftarnya sendiri,
     * sehingga sempat menyatakan "Boarding Pass lengkap" padahal yang wajib
     * adalah boarding pass per tiket.
     *
     * @return list<array{label: string, terpenuhi: bool, berkas: ?string, catatan: ?string}>
     */
    public function checklist(Usulan $usulan): array
    {
        $usulan->loadMissing('dokumen', 'tiket', 'notaTransport', 'laporan', 'kategoriPerjadin');
        $dokumen = $usulan->dokumen->last();
        $dalamKota = $usulan->dalamKota();

        $baris = [
            $this->barisChecklist('SPPD Bertanda Tangan', $dokumen?->sppd, 'Hardcopy dikumpulkan ke tim keuangan'),
        ];

        // Tiket beserta boarding pass-nya, satu baris per arah. Dalam kota
        // tidak memakai tiket sama sekali, jadi barisnya pun tidak muncul.
        $tersimpan = $usulan->tiket->keyBy(fn ($tiket) => $tiket->arah->value);

        if (! $dalamKota) {
            foreach (ArahTiket::urutan() as $arah) {
                $tiket = $tersimpan->get($arah->value);

                $baris[] = [
                    'label' => $arah->label(),
                    'terpenuhi' => $tiket?->lengkap() === true,
                    'berkas' => $tiket?->boarding_pass,
                    'catatan' => $tiket?->kode_booking ? 'Kode booking '.$tiket->kode_booking : null,
                ];
            }
        }

        $nota = $usulan->notaTransport->first(fn ($item) => $item->terisi());

        $baris[] = [
            'label' => 'Nota Transportasi Lokal',
            'terpenuhi' => $nota !== null,
            'berkas' => $usulan->notaTransport->firstWhere(fn ($item) => filled($item->bukti))?->bukti,
            'catatan' => 'Tanpa nota, biaya transportasi tidak diganti',
        ];

        if (! $dalamKota) {
            $baris[] = [
                'label' => 'Bill Hotel',
                'terpenuhi' => filled($dokumen?->bill_hotel)
                    && filled($dokumen?->bill_hotel_no_transaksi)
                    && $dokumen?->bill_hotel_nominal > 0,
                'berkas' => $dokumen?->bill_hotel,
                'catatan' => $dokumen?->bill_hotel_no_transaksi
                    ? 'No. transaksi '.$dokumen->bill_hotel_no_transaksi
                    : 'Nomor transaksi dan nominalnya wajib diisi',
            ];

            $baris[] = $this->barisChecklist('Kuitansi', $dokumen?->kwintasi);
        }

        // Laporan perjadin diisi langsung di aplikasi, bukan diunggah.
        $baris[] = [
            'label' => 'Laporan Perjalanan Dinas',
            'terpenuhi' => $usulan->laporan?->sudahSelesai() === true,
            'berkas' => null,
            'catatan' => 'Diisi pada menu Dokumen, tidak diunggah',
        ];

        return $baris;
    }

    /**
     * @return array{label: string, terpenuhi: bool, berkas: ?string, catatan: ?string}
     */
    private function barisChecklist(string $label, ?string $berkas, ?string $catatan = null): array
    {
        return [
            'label' => $label,
            'terpenuhi' => filled($berkas),
            'berkas' => $berkas,
            'catatan' => $catatan,
        ];
    }

    /**
     * Tiket pergi dan pulang, masing-masing lengkap dengan boarding pass-nya.
     *
     * @return list<string>
     */
    private function tiketKurang(Usulan $usulan): array
    {
        $usulan->loadMissing('tiket');
        $tersimpan = $usulan->tiket->keyBy(fn ($tiket) => $tiket->arah->value);

        return collect(ArahTiket::urutan())
            ->reject(fn (ArahTiket $arah) => $tersimpan->get($arah->value)?->lengkap() === true)
            ->map(fn (ArahTiket $arah) => $arah->label())
            ->values()
            ->all();
    }

    /**
     * Sedikitnya satu ruas transportasi lokal dinotakan.
     *
     * Tidak diwajibkan keempatnya: ada perjalanan darat yang memang tidak
     * melewati bandara, dan menagih ruas yang tidak pernah ada hanya membuat
     * pelaksana mengarang angka.
     *
     * @return list<string>
     */
    private function notaKurang(Usulan $usulan): array
    {
        $usulan->loadMissing('notaTransport');

        return $usulan->notaTransport->contains(fn ($nota) => $nota->terisi())
            ? []
            : ['Nota/biaya transportasi lokal'];
    }

    /**
     * @return list<string>
     */
    private function rincianHotelKurang(?Dokumen $dokumen): array
    {
        if (! $dokumen || blank($dokumen->bill_hotel)) {
            // Berkasnya sendiri sudah ditagih lewat DOKUMEN_LPJ_WAJIB.
            return [];
        }

        $kurang = [];

        if (blank($dokumen->bill_hotel_no_transaksi)) {
            $kurang[] = 'Nomor transaksi bill hotel';
        }

        if (! ($dokumen->bill_hotel_nominal > 0)) {
            $kurang[] = 'Nominal bill hotel';
        }

        return $kurang;
    }

    /**
     * @return list<string>
     */
    private function laporanKurang(Usulan $usulan): array
    {
        $usulan->loadMissing('laporan');

        return $usulan->laporan?->sudahSelesai() === true
            ? []
            : ['Laporan perjalanan dinas'];
    }

    public function lengkap(Usulan $usulan): bool
    {
        return $this->berkasKurang($usulan) === [];
    }

    /**
     * Tautan wa.me lengkap dengan pesan yang sudah tersusun.
     *
     * Mengembalikan null bila berkasnya sudah lengkap, atau nomor WhatsApp
     * pelaksana belum terdaftar.
     */
    public function tautanWhatsapp(Usulan $usulan): ?string
    {
        $nomor = $usulan->user?->nomor_whatsapp;
        $kurang = $this->berkasKurang($usulan);

        if (! $nomor || $kurang === []) {
            return null;
        }

        return 'https://wa.me/'.$nomor.'?text='.rawurlencode($this->pesan($usulan, $kurang));
    }

    /**
     * @param  list<string>  $kurang
     */
    public function pesan(Usulan $usulan, array $kurang): string
    {
        $daftar = collect($kurang)
            ->map(fn (string $label, int $i) => ($i + 1).'. '.$label)
            ->implode("\n");

        $sapaan = $usulan->user?->nama ?? 'Bapak/Ibu';
        $selesai = $usulan->tanggal_selesai
            ? Carbon::parse($usulan->tanggal_selesai)->translatedFormat('d F Y')
            : '-';

        return "Selamat pagi/siang {$sapaan},\n\n"
            ."Menindaklanjuti perjalanan dinas {$usulan->no_usulan} ke {$usulan->lokasi} "
            ."yang berakhir {$selesai}, berkas pertanggungjawaban berikut belum kami terima:\n\n"
            ."{$daftar}\n\n"
            ."Mohon diunggah melalui aplikasi PANGI pada menu Dokumen agar pembayaran dapat kami proses.\n\n"
            .'Terima kasih. — Tim Keuangan Poltekkes Kemenkes Manado';
    }

    /**
     * Alasan tautan tidak tersedia, untuk ditampilkan sebagai keterangan.
     */
    public function alasanTidakTersedia(Usulan $usulan): ?string
    {
        return match (true) {
            $this->lengkap($usulan) => 'Berkas sudah lengkap',
            blank($usulan->user?->nomor_whatsapp) => 'Nomor WhatsApp pelaksana belum terdaftar',
            default => null,
        };
    }
}
