@php
    /**
     * Surat Perjalanan Dinas, mengikuti format baku Poltekkes Kemenkes Manado.
     *
     * Penanda ${ttd_pengirim1} dan ${ttd_pengirim2} sengaja dibiarkan apa
     * adanya: aplikasi SRIKANDI yang menggantinya dengan QR tanda tangan
     * elektronik berukuran 3x3 cm. Ruang di sekitarnya sudah disediakan
     * seukuran itu agar tata letaknya tidak bergeser setelah QR disisipkan.
     *
     * Pembagian penandatangannya: Pejabat Pembuat Komitmen menandatangani
     * lembar pertama dan kolom pemberi perintah (${ttd_pengirim1}),
     * sedangkan Direktur mengesahkan keberangkatan dan kedatangan kembali
     * pada lembar kedua (${ttd_pengirim2}).
     *
     * Satu lembar terbit per pelaksana; rencana perjalanannya dipakai bersama.
     */
    $tanggal = fn ($t) => $t ? \Carbon\Carbon::parse($t)->translatedFormat('d F Y') : '';
    $terbilang = app(\App\Services\Terbilang::class);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Perjalanan Dinas</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        @page { margin: 10mm 13mm 10mm; }
        body { font-size: 10px; color: #000; margin: 0; line-height: 1.35; }

        .kop { text-align: center; }
        .kop img { width: 66%; height: auto; }

        /* Blok kode dan nomor di kanan atas. */
        .nomor-atas { width: 100%; margin: 6px 0 2px; }
        .nomor-atas td { padding: 0; vertical-align: top; font-size: 10px; }
        .nomor-atas .label { width: 92px; }
        .nomor-atas .titik { width: 34px; }

        .judul { text-align: center; margin: 10px 0 8px; }
        .judul span {
            font-size: 12px; font-weight: bold;
            text-decoration: underline; letter-spacing: .2px;
        }

        table.isi { width: 100%; border-collapse: collapse; }
        table.isi > tr > td, table.isi td { border: 1px solid #000; padding: 3px 6px; vertical-align: top; }
        td.no { width: 26px; text-align: left; }
        td.label { width: 44%; }
        td.pemisah { width: 12px; text-align: center; }
        .sub { padding-left: 14px; }
        .rapat { line-height: 1.5; }

        /* Butir 8 memakai kolom sendiri untuk Tanggal Lahir dan Keterangan. */
        table.pengikut { width: 100%; border-collapse: collapse; }
        table.pengikut td { border: none; padding: 0 4px; vertical-align: top; }
        table.pengikut td.garis-kiri { border-left: 1px solid #000; }

        .catatan-kaki { font-size: 9px; margin: 3px 0 0; }

        /* Blok pengesahan kanan bawah halaman depan. */
        .pengesahan { width: 100%; margin-top: 14px; }
        .pengesahan td { padding: 0; vertical-align: top; font-size: 10px; }
        .pengesahan .kanan { width: 52%; }

        .blok-ttd { text-align: center; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }

        /* Halaman belakang. */
        table.belakang { width: 100%; border-collapse: collapse; }
        table.belakang td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; font-size: 9px; line-height: 1.3; }
        td.rom { width: 26px; }
        td.sisi { width: 47%; }
        .titik-isi { letter-spacing: .5px; }
        .perhatian { text-align: justify; }

        .logo-bawah { width: 100%; margin-top: 10px; }
        .logo-bawah td { padding: 0; text-align: right; }
        .logo-bawah img { width: 92px; height: auto; }

        .pecah { page-break-after: always; }

        /* ─────────────────────────────────────────────────────────────
           Aturan tabel di dalam sel WAJIB berada paling bawah.

           "table.kotak-ttd td" dan "table.belakang td" punya bobot
           specificity yang sama persis, sehingga yang menang adalah yang
           ditulis belakangan. Bila blok ini dipindah ke atas, sel di dalam
           halaman belakang kembali mewarisi border dan vertical-align dari
           tabel induknya — penanda tanda tangan pun tidak lagi di tengah.
           ───────────────────────────────────────────────────────────── */

        /* Kotak 30 mm dengan penanda tepat di tengahnya, mendatar dan tegak.
           Saat SRIKANDI mengganti penanda dengan QR 3x3 cm, QR menempatinya
           persis tanpa menggeser nama dan NIP di bawahnya. */
        table.kotak-ttd { width: 100%; border-collapse: collapse; }
        table.kotak-ttd td {
            border: none;
            padding: 0;
            height: 30mm;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
            font-size: 11px;
            line-height: 1.2;
        }

        /* Pasangan label dan isian seperti "Berangkat dari / Ke / Pada
           Tanggal" tidak bergaris. */
        table.rincian-dalam { width: 100%; border-collapse: collapse; }
        table.rincian-dalam td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
    </style>
</head>
<body>

@foreach ($daftarPelaksana as $orang)
    @php $lembarTerakhir = $loop->last; @endphp

    {{-- ═══════════════ HALAMAN DEPAN ═══════════════ --}}

    <div class="kop">
        <img src="{{ public_path('images/kop-surat-poltekkes.jpg') }}" alt="Kop Poltekkes Kemenkes Manado">
    </div>

    <table class="nomor-atas">
        <tr>
            <td style="width:46%">&nbsp;</td>
            <td class="label">Kode Nomor</td>
            <td class="titik">:</td>
            <td>{{ $spd->akun_pembebanan ?: '.........................................' }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td class="label">Nomor</td>
            <td class="titik">:</td>
            <td>{{ $orang->nomor_surat }}</td>
        </tr>
    </table>

    <div class="judul"><span>SURAT PERJALANAN DINAS (SPD)</span></div>

    <table class="isi">
        <tr>
            <td class="no">1.</td>
            <td class="label">Pejabat Pembuat Komitmen</td>
            <td class="pemisah">:</td>
            <td>{{ $ppk?->nama ?? '' }}</td>
        </tr>
        <tr>
            <td class="no">2.</td>
            <td class="label">Nama / NIP yang melaksanakan perjalanan dinas</td>
            <td class="pemisah">:</td>
            <td>
                {{ $orang->nama }}<br>
                NIP.{{ $orang->nip }}
            </td>
        </tr>
        <tr>
            <td class="no">3.</td>
            <td class="label rapat">
                a.&nbsp; Pangkat dan Golongan<br>
                b.&nbsp; Jabatan / Instansi<br>
                c.&nbsp; Tingkat Biaya Perjalanan Dinas
            </td>
            <td class="pemisah">:</td>
            <td class="rapat">
                a.&nbsp; {{ $orang->pangkat_golongan }}<br>
                b.&nbsp; {{ $orang->jabatan_instansi }}<br>
                c.&nbsp; {{ $orang->tingkat_biaya }}
            </td>
        </tr>
        <tr>
            <td class="no">4.</td>
            <td class="label">Maksud Perjalanan Dinas</td>
            <td class="pemisah">:</td>
            <td>{{ $spd->maksud }}</td>
        </tr>
        <tr>
            <td class="no">5.</td>
            <td class="label">Alat Angkutan yang Dipergunakan</td>
            <td class="pemisah">:</td>
            <td>{{ $spd->alat_angkut }}</td>
        </tr>
        <tr>
            <td class="no">6.</td>
            <td class="label rapat">
                a.&nbsp; Tempat Berangkat<br>
                b.&nbsp; Tempat Tujuan
            </td>
            <td class="pemisah">:</td>
            <td class="rapat">
                a.&nbsp; {{ $spd->tempat_berangkat }}<br>
                b.&nbsp; {{ $spd->tempat_tujuan }}
            </td>
        </tr>
        <tr>
            <td class="no">7.</td>
            <td class="label rapat">
                a.&nbsp; Lamanya Perjalanan Dinas<br>
                b.&nbsp; Tanggal Berangkat<br>
                c.&nbsp; Tanggal Harus Kembali / Tiba<br>
                <span class="sub">ditempat baru *)</span>
            </td>
            <td class="pemisah">:</td>
            <td class="rapat">
                a.&nbsp; {{ $spd->lama_hari }} ({{ $terbilang->konversi($spd->lama_hari) }}) hari<br>
                b.&nbsp; {{ $tanggal($spd->tanggal_berangkat) }}<br>
                c.&nbsp; {{ $tanggal($spd->tanggal_kembali) }}
            </td>
        </tr>
        <tr>
            <td class="no">8.</td>
            <td colspan="3" style="padding:0">
                <table class="pengikut">
                    <tr>
                        <td style="width:46%; padding:4px 6px">
                            <span style="padding-left:52px">Pengikut&nbsp; :&nbsp; Nama</span>
                        </td>
                        <td class="garis-kiri" style="width:27%; padding:4px 6px">Tanggal Lahir</td>
                        <td class="garis-kiri" style="width:27%; padding:4px 6px">Keterangan</td>
                    </tr>
                    @for ($i = 0; $i < 3; $i++)
                        @php $ikut = $pengikut->get($i); @endphp
                        <tr>
                            <td style="padding:1px 6px">{{ $i + 1 }}.&nbsp; {{ $ikut->nama ?? '' }}</td>
                            <td class="garis-kiri" style="padding:1px 6px">
                                {{ $ikut && $ikut->tanggal_lahir ? $tanggal($ikut->tanggal_lahir) : '' }}
                            </td>
                            <td class="garis-kiri" style="padding:1px 6px">{{ $ikut->keterangan ?? '' }}</td>
                        </tr>
                    @endfor
                </table>
            </td>
        </tr>
        <tr>
            <td class="no">9.</td>
            <td class="label rapat">
                Pembebanan Anggaran<br>
                <span class="sub">a.&nbsp; Instansi</span><br>
                <span class="sub">b.&nbsp; Akun</span>
            </td>
            <td class="pemisah">:</td>
            <td class="rapat">
                &nbsp;<br>
                a.&nbsp; {{ $spd->instansi_pembebanan }}<br>
                b.&nbsp; {{ $spd->akun_pembebanan }}
            </td>
        </tr>
        <tr>
            <td class="no">10.</td>
            <td class="label">Keterangan Lain-lain</td>
            <td class="pemisah">:</td>
            <td>{{ $spd->keterangan_lain }}</td>
        </tr>
    </table>

    <p class="catatan-kaki">*) Coret yang tidak perlu</p>

    <table class="pengesahan">
        <tr>
            <td>&nbsp;</td>
            <td class="kanan">
                <table class="rincian-dalam">
                    <tr>
                        <td style="width:110px; padding:0">DIKELUARKAN DI</td>
                        <td style="width:12px; padding:0">:</td>
                        <td style="padding:0">{{ mb_strtoupper($spd->dikeluarkan_di) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0"><span style="text-decoration:underline">TANGGAL</span></td>
                        <td style="padding:0">:</td>
                        <td style="padding:0"><span style="text-decoration:underline">{{ $tanggal($spd->tanggal_surat) }}</span></td>
                    </tr>
                </table>

                {{-- Blok tanda tangan rata tengah agar QR SRIKANDI jatuh
                     tepat di tengah kolom, bukan menempel ke tepi. --}}
                <div class="blok-ttd">
                    <p style="margin:12px 0 0">Pejabat Pembuat Komitmen</p>

                    {{-- Diganti QR tanda tangan elektronik oleh SRIKANDI. --}}
                    <table class="kotak-ttd"><tr><td>${ttd_pengirim1}</td></tr></table>

                    <p style="margin:0"><span class="nama-ttd">{{ $ppk?->nama ?? '' }}</span></p>
                    <p style="margin:0">NIP. {{ $ppk?->nip ?? '' }}</p>
                </div>
            </td>
        </tr>
    </table>

    <table class="logo-bawah">
        <tr>
            <td><img src="{{ public_path('images/logo-akreditasi.jpg') }}" alt="KAN dan BLU"></td>
        </tr>
    </table>

    <div class="pecah"></div>

    {{-- ═══════════════ HALAMAN BELAKANG ═══════════════ --}}

    <table class="belakang">
        {{-- I. Keberangkatan awal, disahkan Direktur. --}}
        <tr>
            <td class="rom">&nbsp;</td>
            <td class="sisi">&nbsp;</td>
            <td>
                <table class="rincian-dalam">
                    <tr>
                        <td style="width:96px; padding:0">Berangkat dari</td>
                        <td style="width:12px; padding:0">:</td>
                        <td style="padding:0">{{ $spd->tempat_berangkat }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0">Ke</td>
                        <td style="padding:0">:</td>
                        <td style="padding:0">{{ $spd->tempat_tujuan }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0">Pada Tanggal</td>
                        <td style="padding:0">:</td>
                        <td style="padding:0">{{ $tanggal($spd->tanggal_berangkat) }}</td>
                    </tr>
                </table>

                <div class="blok-ttd">
                    <p style="margin:4px 0 0">Direktur Poltekkes Manado</p>

                    {{-- Diganti QR tanda tangan elektronik oleh SRIKANDI. --}}
                    <table class="kotak-ttd"><tr><td>${ttd_pengirim2}</td></tr></table>

                    <p style="margin:0"><span class="nama-ttd">{{ $direktur?->nama ?? '' }}</span></p>
                    <p style="margin:0">NIP {{ $direktur?->nip ?? '' }}</p>
                </div>
            </td>
        </tr>

        {{-- II sampai IV: singgah antara, diisi tangan di lapangan. --}}
        @foreach (['II.', 'III.', 'IV.'] as $rom)
            <tr>
                <td class="rom">{{ $rom }}</td>
                <td class="sisi">
                    <table class="rincian-dalam">
                        <tr>
                            <td style="width:96px; padding:0">Tiba di</td>
                            <td style="width:12px; padding:0">:</td>
                            <td style="padding:0">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:0">Pada Tanggal</td>
                            <td style="padding:0">:</td>
                            <td style="padding:0">&nbsp;</td>
                        </tr>
                    </table>
                    <p style="margin:5px 0 0" class="titik-isi">Kepala ......................................................</p>
                    <div style="height:7mm"></div>
                    <p style="margin:0" class="titik-isi">(....................................................)</p>
                    <p style="margin:0">NIP.</p>
                </td>
                <td>
                    <table class="rincian-dalam">
                        <tr>
                            <td style="width:96px; padding:0">Berangkat dari</td>
                            <td style="width:12px; padding:0">:</td>
                            <td style="padding:0">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:0">Ke</td>
                            <td style="padding:0">:</td>
                            <td style="padding:0">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:0">Pada Tanggal</td>
                            <td style="padding:0">:</td>
                            <td style="padding:0">&nbsp;</td>
                        </tr>
                    </table>
                    <p style="margin:5px 0 0" class="titik-isi">&nbsp;Kepala ......................................................</p>
                    <div style="height:7mm"></div>
                    <p style="margin:0" class="titik-isi">(....................................................)</p>
                    <p style="margin:0">NIP.</p>
                </td>
            </tr>
        @endforeach

        {{-- V. Kembali ke tempat kedudukan. Kolom kiri disahkan Direktur,
             kolom kanan oleh Pejabat Pembuat Komitmen selaku pemberi perintah. --}}
        <tr>
            <td class="rom">V.</td>
            <td class="sisi">
                <table class="rincian-dalam">
                    <tr>
                        <td style="width:96px; padding:0">Tiba di</td>
                        <td style="width:12px; padding:0">:</td>
                        <td style="padding:0">{{ $spd->tempat_berangkat }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0">&nbsp;</td>
                        <td style="padding:0">&nbsp;</td>
                        <td style="padding:0">(Tempat Kedudukan)</td>
                    </tr>
                    <tr>
                        <td style="padding:0">Pada Tanggal</td>
                        <td style="padding:0">:</td>
                        <td style="padding:0" class="titik-isi">....................................</td>
                    </tr>
                </table>

                <div class="blok-ttd">
                    <p style="margin:6px 0 0">Direktur Poltekkes Manado</p>

                    {{-- Diganti QR tanda tangan elektronik oleh SRIKANDI. --}}
                    <table class="kotak-ttd"><tr><td>${ttd_pengirim2}</td></tr></table>

                    <p style="margin:0"><span class="nama-ttd">{{ $direktur?->nama ?? '' }}</span></p>
                    <p style="margin:0">NIP. {{ $direktur?->nip ?? '' }}</p>
                </div>
            </td>
            <td>
                <p class="perhatian" style="margin:0">
                    Telah diperiksa dengan keterangan, bahwa perjalanan tersebut di atas benar
                    dilakukan atas perintahnya dan semata-mata untuk kepentingan jabatan dalam
                    waktu sesingkat-singkatnya.
                </p>
                <p style="margin:2px 0 0">Pejabat yang memberi perintah :</p>

                <div class="blok-ttd">
                    <p style="margin:6px 0 0">Pejabat Pembuat Komitmen</p>

                    {{-- Diganti QR tanda tangan elektronik oleh SRIKANDI. --}}
                    <table class="kotak-ttd"><tr><td>${ttd_pengirim1}</td></tr></table>

                    <p style="margin:0"><span class="nama-ttd">{{ $ppk?->nama ?? '' }}</span></p>
                    <p style="margin:0">NIP. {{ $ppk?->nip ?? '' }}</p>
                </div>
            </td>
        </tr>

        <tr>
            <td class="rom">VII.</td>
            <td class="sisi">Catatan Lain-lain</td>
            <td>&nbsp;</td>
        </tr>

        <tr>
            <td colspan="3">
                <p style="margin:0">VIII.&nbsp;&nbsp; PERHATIAN</p>
                <p class="perhatian" style="margin:2px 0 0; padding-left:24px">
                    Pejabat yang berwenang mengeluarkan SPD Pegawai yang melakukan Perjalanan Dinas.
                    Para Pejabat yang mengesahkan tanggal berangkat/tiba serta Bendahara Pengeluaran
                    bertanggung jawab berdasarkan peraturan-peraturan Keuangan Negara apabila Negara
                    menderita rugi akibat kesalahan, kelalaian dan kealpaannya.
                </p>
            </td>
        </tr>
    </table>

    @unless ($lembarTerakhir)
        <div class="pecah"></div>
    @endunless
@endforeach

</body>
</html>
