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
                ->concat($this->penyelenggaraanKurang($dokumen))
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
            ->concat($this->penyelenggaraanKurang($dokumen))
            ->concat($this->laporanKurang($usulan))
            ->values()
            ->all();
    }

    /**
     * Checklist kelengkapan untuk ditampilkan, satu baris per seksi formulir
     * dokumen — persis yang diminta formulir itu kepada pelaksana.
     *
     * Diturunkan dari aturan yang sama dengan berkasKurang() agar layar dan
     * penagihan tidak pernah berbeda: dulu checklist punya daftarnya sendiri,
     * sehingga sempat menyatakan "Boarding Pass lengkap" padahal yang wajib
     * adalah boarding pass per tiket. Tiap baris membawa seluruh berkasnya —
     * tiket punya boarding pass dan invoice, nota punya bukti per ruas —
     * supaya tim keuangan membuka semuanya dari satu tempat.
     *
     * @return list<array{label: string, terpenuhi: bool, berkas: list<array{label: string, path: string}>, catatan: ?string}>
     */
    public function checklist(Usulan $usulan): array
    {
        $usulan->loadMissing('dokumen', 'tiket', 'notaTransport', 'laporan', 'kategoriPerjadin');
        $dokumen = $usulan->dokumen->last();
        $dalamKota = $usulan->dalamKota();

        // Seksi 1 — penugasan.
        $baris = [
            $this->barisChecklist('SPPD Bertanda Tangan', $dokumen?->sppd, 'Hardcopy dikumpulkan ke tim keuangan'),
        ];

        // Seksi 2 — tiket pergi dan pulang, masing-masing dengan boarding pass
        // dan invoice-nya. Dalam kota tidak memakai tiket sama sekali.
        if (! $dalamKota) {
            $tersimpan = $usulan->tiket->keyBy(fn ($tiket) => $tiket->arah->value);

            foreach (ArahTiket::urutan() as $arah) {
                $tiket = $tersimpan->get($arah->value);
                $kurang = $this->kekuranganTiket($tiket);

                $baris[] = [
                    'label' => $arah->label(),
                    'terpenuhi' => $kurang === [],
                    'berkas' => array_values(array_filter([
                        filled($tiket?->boarding_pass) ? ['label' => 'Boarding pass', 'path' => $tiket->boarding_pass] : null,
                        filled($tiket?->invoice) ? ['label' => 'Invoice', 'path' => $tiket->invoice] : null,
                    ])),
                    'catatan' => $kurang !== []
                        ? 'Belum ada '.implode(', ', $kurang)
                        : 'Kode booking '.$tiket->kode_booking,
                ];
            }
        }

        // Seksi 3 — nota transportasi lokal, bukti per ruas.
        $ruasKurang = $this->ruasTanpaBukti($usulan);
        $adaNota = $usulan->notaTransport->contains(fn ($item) => $item->terisi());

        $baris[] = [
            'label' => 'Nota Transportasi Lokal',
            'terpenuhi' => $ruasKurang === [],
            'berkas' => $usulan->notaTransport
                ->filter(fn ($item) => filled($item->bukti))
                ->sortBy('urutan')
                ->map(fn ($item) => [
                    'label' => $item->nama_ruas,
                    'path' => $item->bukti,
                ])
                ->values()
                ->all(),
            'catatan' => match (true) {
                ! $adaNota => 'Tidak ada biaya transport lokal yang dinyatakan',
                $ruasKurang !== [] => 'Bernominal tapi belum ada notanya: '.implode(', ', $ruasKurang),
                default => 'Ruas terisi: '.$usulan->notaTransport->filter(fn ($item) => $item->terisi())->count(),
            },
        ];

        // Seksi 4 — akomodasi dan bukti biaya.
        if (! $dalamKota) {
            $baris[] = [
                'label' => 'Bill Hotel',
                'terpenuhi' => filled($dokumen?->bill_hotel)
                    && filled($dokumen?->bill_hotel_no_transaksi)
                    && $dokumen?->bill_hotel_nominal > 0,
                'berkas' => filled($dokumen?->bill_hotel) ? [['label' => 'Bill hotel', 'path' => $dokumen->bill_hotel]] : [],
                'catatan' => $dokumen?->bill_hotel_no_transaksi
                    ? 'No. transaksi '.$dokumen->bill_hotel_no_transaksi
                    : 'Nomor transaksi dan nominalnya wajib diisi',
            ];

            $baris[] = $this->barisChecklist('Kuitansi', $dokumen?->kwintasi);
        }

        // Seksi 5 — biaya penyelenggaraan, hanya bila pelaksana menyatakan ada.
        if ($dokumen?->adaPenyelenggaraan()) {
            $baris[] = [
                'label' => 'Bukti Biaya Penyelenggaraan',
                'terpenuhi' => $this->penyelenggaraanKurang($dokumen) === [],
                'berkas' => filled($dokumen->penyelenggaraan_bukti)
                    ? [['label' => 'Bukti bayar', 'path' => $dokumen->penyelenggaraan_bukti]]
                    : [],
                'catatan' => $dokumen->penyelenggaraan_nominal > 0
                    ? 'Rp '.number_format($dokumen->penyelenggaraan_nominal, 0, ',', '.')
                        .($dokumen->penyelenggaraan_invoice ? ' · No. invoice '.$dokumen->penyelenggaraan_invoice : '')
                    : 'Nominal dan bukti bayarnya wajib diisi',
            ];
        }

        // Laporan perjadin diisi langsung di aplikasi, bukan diunggah.
        $laporan = $usulan->laporan;

        $baris[] = [
            'label' => 'Laporan Perjalanan Dinas',
            'terpenuhi' => $laporan?->sudahSelesai() === true,
            'berkas' => [],
            'catatan' => $laporan
                ? $laporan->status()->label()
                : 'Diisi pada menu Dokumen, tidak diunggah',
        ];

        return $baris;
    }

    /**
     * @return array{label: string, terpenuhi: bool, berkas: list<array{label: string, path: string}>, catatan: ?string}
     */
    private function barisChecklist(string $label, ?string $berkas, ?string $catatan = null): array
    {
        return [
            'label' => $label,
            'terpenuhi' => filled($berkas),
            'berkas' => filled($berkas) ? [['label' => $label, 'path' => $berkas]] : [],
            'catatan' => $catatan,
        ];
    }

    /**
     * Bagian tiket yang belum terisi, dengan sebutan yang dipahami pelaksana.
     *
     * @return list<string>
     */
    private function kekuranganTiket(mixed $tiket): array
    {
        if (! $tiket) {
            return ['data tiket'];
        }

        return array_values(array_filter([
            filled($tiket->kota_asal) && filled($tiket->kota_tujuan) ? null : 'rute',
            filled($tiket->nomor_tiket) ? null : 'nomor tiket',
            filled($tiket->kode_booking) ? null : 'kode booking',
            $tiket->harga > 0 ? null : 'harga',
            filled($tiket->boarding_pass) ? null : 'boarding pass',
            filled($tiket->invoice) ? null : 'invoice',
        ]));
    }

    /**
     * Ruas yang diisi nominalnya tetapi belum diunggah notanya.
     *
     * Nota hanya wajib untuk ruas yang bernominal: ruas yang kosong memang
     * tidak dilalui, dan ruas bernominal tanpa bukti tidak boleh diganti.
     *
     * @return list<string>
     */
    private function ruasTanpaBukti(Usulan $usulan): array
    {
        $usulan->loadMissing('notaTransport');

        return $usulan->notaTransport
            ->filter(fn ($item) => $item->terisi() && blank($item->bukti))
            ->sortBy('urutan')
            ->map(fn ($item) => lcfirst($item->nama_ruas))
            ->values()
            ->all();
    }

    /**
     * Tiket pergi dan pulang, masing-masing lengkap dengan boarding pass dan
     * invoice-nya; yang kurang disebut supaya pelaksana tahu apa yang ditagih.
     *
     * @return list<string>
     */
    private function tiketKurang(Usulan $usulan): array
    {
        $usulan->loadMissing('tiket');
        $tersimpan = $usulan->tiket->keyBy(fn ($tiket) => $tiket->arah->value);

        return collect(ArahTiket::urutan())
            ->map(fn (ArahTiket $arah) => [$arah, $this->kekuranganTiket($tersimpan->get($arah->value))])
            ->reject(fn (array $pasangan) => $pasangan[1] === [])
            ->map(fn (array $pasangan) => $pasangan[0]->label().' ('.implode(', ', $pasangan[1]).')')
            ->values()
            ->all();
    }

    /**
     * Nota transportasi lokal hanya ditagih untuk ruas yang bernominal.
     *
     * Tidak ada ruas yang diwajibkan: perjalanan yang diantar kendaraan
     * dinas atau dijemput panitia memang tidak mengeluarkan transport lokal,
     * dan dulu syarat "sedikitnya satu ruas" membuat berkas seperti itu
     * tidak pernah lengkap — tidak pernah tercantum di daftar nominatif dan
     * tidak pernah selesai. Kosong berarti tidak ada biaya; yang bernominal
     * tanpa nota itulah yang ditagih.
     *
     * @return list<string>
     */
    private function notaKurang(Usulan $usulan): array
    {
        $usulan->loadMissing('notaTransport');

        $tanpaBukti = $this->ruasTanpaBukti($usulan);

        return $tanpaBukti === []
            ? []
            : ['Nota transportasi lokal untuk '.implode(', ', $tanpaBukti)];
    }

    /**
     * Biaya penyelenggaraan yang dinyatakan ada harus bernominal dan
     * berbukti bayar; yang dinyatakan tidak ada tidak ditagih apa pun.
     *
     * @return list<string>
     */
    private function penyelenggaraanKurang(?Dokumen $dokumen): array
    {
        if (! $dokumen?->adaPenyelenggaraan()) {
            return [];
        }

        return $dokumen->penyelenggaraan_nominal > 0 && filled($dokumen->penyelenggaraan_bukti)
            ? []
            : ['Bukti bayar biaya penyelenggaraan'];
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
