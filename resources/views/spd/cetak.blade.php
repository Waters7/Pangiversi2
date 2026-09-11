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
        /* Arial dipetakan dompdf ke Helvetica, salah satu font baku PDF yang
           metriknya setara dan tersedia di semua pembaca — jadi tidak ada
           font yang perlu dipasang, dan berkasnya jauh lebih ringan karena
           tidak ada font yang ikut ditanam. */
        * { font-family: Arial, Helvetica, sans-serif; }

        /* Margin atas dirapatkan 2 mm untuk memberi ruang kop yang lebih
           besar; sisanya diambil dari jarak blok nomor dan judul di bawahnya,
           sehingga tabel isi tetap mulai pada ketinggian yang sama.

           Kertasnya folio (215,9 mm). Margin kiri-kanan 16 mm menyisakan
           ruang cetak 184 mm — sama persis dengan A4 bermargin 13 mm yang
           menjadi acuan seluruh lebar kolom di bawah, sehingga tata letaknya
           tidak bergeser sedikit pun; kelebihan tingginya jatuh ke bawah. */
        @page { margin: 8mm 16mm 7mm; }
        body { font-size: 11pt; color: #000; margin: 0; line-height: 1.15; }

        .kop { text-align: center; }
        .kop img { width: 82%; height: auto; }

        /* Blok kode dan nomor di kanan atas. */
        .nomor-atas { width: 100%; margin: 2px 0 0; }
        .nomor-atas td { padding: 0; vertical-align: top; font-size: 11pt; line-height: 1.15; }
        .nomor-atas .spasi { width: 47.7%; }
        .nomor-atas .label { width: 96px; }
        .nomor-atas .titik { width: 12px; }

        .judul { text-align: center; margin: 4px 0; }
        .judul span {
            font-size: 13pt; font-weight: bold;
            text-decoration: underline; letter-spacing: .2px;
        }

        /* ─────────────────────────────────────────────────────────────
           Lebar kolom dipatok dalam milimeter, bukan persen.

           8 + 78 + 5 + 93 = 184 mm, tepat selebar ruang cetak folio setelah
           margin kiri-kanan 16 mm. Dengan angka pasti, titik dua dan kolom
           isian jatuh pada garis tegak yang sama dari butir 1 sampai 10 —
           termasuk pada tabel pengikut di butir 8 yang lebarnya disetel
           mengikuti pembagian ini.
           ───────────────────────────────────────────────────────────── */
        table.isi { width: 100%; border-collapse: collapse; }
        table.isi > tr > td, table.isi td { border: 1px solid #000; padding: 1px 6px; vertical-align: top; }
        td.no { width: 26px; text-align: left; }
        td.label { width: 44%; }
        td.pemisah { width: 12px; text-align: center; }
        .sub { padding-left: 14px; }
        .rapat { line-height: 1.35; }

        /* Butir 8 memakai kolom sendiri untuk Tanggal Lahir dan Keterangan.
           Tiga kolomnya berjumlah 176 mm — sisa lebar setelah kolom nomor.

           Judul "Tanggal Lahir" dan "Keterangan" berdiri sekali di baris
           teratas, sedangkan garis tegaknya menerus sampai baris ketiga
           karena setiap sel di bawahnya ikut memasang border-left. */
        table.pengikut { width: 100%; border-collapse: collapse; }
        table.pengikut td { border: none; padding: 2px 6px; vertical-align: top; }
        table.pengikut td.garis-kiri { border-left: 1px solid #000; }
        table.pengikut td.nama { width: 46%; }
        table.pengikut td.lahir { width: 27%; }
        table.pengikut td.ket { width: 27%; }
        table.pengikut tr.baris td { padding: 1px 6px; }

        .catatan-kaki { font-size: 9pt; margin: 3px 0 0; }

        /* Blok pengesahan kanan bawah halaman depan. */
        .pengesahan { width: 100%; margin-top: 6px; }
        .pengesahan td { padding: 0; vertical-align: top; font-size: 11pt; }
        .pengesahan .kanan { width: 52%; }

        .blok-ttd { text-align: center; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }

        /* Halaman belakang. Kolom nomor Romawi 8 mm, lalu dua kolom sisi yang
           benar-benar sama lebar — 88 mm masing-masing. Sebelumnya kolom kiri
           47% dan kolom kanan mengambil sisanya, sehingga keduanya tampak
           timpang meski isinya sejenis. */
        table.belakang { width: 100%; border-collapse: collapse; }
        table.belakang td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; font-size: 9pt; line-height: 1.25; }
        td.rom { width: 26px; }
        td.sisi { width: 88mm; }
        .titik-isi { letter-spacing: .5px; }
        .perhatian { text-align: justify; }

        /* Kaki halaman depan: kotak pernyataan antigratifikasi di kiri, logo
           akreditasi di kanan — logonya berada di luar garis kotak, seperti
           pada berkas cetakan bakunya. */
        .kaki { width: 100%; margin-top: 5px; border-collapse: collapse; }
        .kaki td { padding: 0; vertical-align: middle; }
        .kaki td.pesan {
            width: 76%;
            border: 1px solid #000;
            padding: 3px 8px;
            text-align: center;
            font-size: 7.5pt;
            line-height: 1.25;
        }
        .kaki td.logo { padding-left: 6px; text-align: right; }
        .kaki td.logo img { width: 155px; height: auto; }
        .kaki .tautan { text-decoration: underline; }

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
            font-size: 11pt;
            line-height: 1.2;
        }

        /* Pasangan label dan isian seperti "Berangkat dari / Ke / Pada
           Tanggal" tidak bergaris.

           Lebar labelnya dipatok satu angka untuk seluruh dokumen — dulu
           110px di halaman depan dan 96px di halaman belakang, sehingga titik
           duanya tidak segaris antarblok. */
        table.rincian-dalam { width: 100%; border-collapse: collapse; }
        table.rincian-dalam td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        table.rincian-dalam td.k { width: 96px; }
        table.rincian-dalam.terbit td.k { width: 128px; }
        table.rincian-dalam td.t { width: 12px; }
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
            <td class="spasi">&nbsp;</td>
            <td class="label">Kode Nomor</td>
            <td class="titik">:</td>
            <td>{{ $spd->akun_pembebanan ?: '.........................................' }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td class="label">Nomor</td>
            <td class="titik">:</td>
            {{-- Diganti nomor naskah oleh SRIKANDI saat surat diregistrasi. --}}
            <td>${nomor_naskah}</td>
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
                a.&nbsp; {{ $spd->lama_hari }} ({{ $terbilang->kata($spd->lama_hari) }}) hari<br>
                b.&nbsp; {{ $tanggal($spd->tanggal_berangkat) }}<br>
                c.&nbsp; {{ $tanggal($spd->tanggal_kembali) }}
            </td>
        </tr>
        <tr>
            <td class="no">8.</td>
            <td colspan="3" style="padding:0">
                <table class="pengikut">
                    <tr>
                        <td class="nama">Pengikut&nbsp; :</td>
                        <td class="garis-kiri lahir">Tanggal Lahir</td>
                        <td class="garis-kiri ket">Keterangan</td>
                    </tr>
                    @for ($i = 0; $i < 3; $i++)
                        @php $ikut = $pengikut->get($i); @endphp
                        <tr class="baris">
                            {{-- Kata "Nama" hanya menemani baris pertama, persis
                                 seperti pada berkas cetakan bakunya. --}}
                            <td class="nama">
                                @if ($i === 0) Nama @endif
                                {{ $i + 1 }}.&nbsp; {{ $ikut->nama ?? '' }}
                            </td>
                            <td class="garis-kiri lahir">
                                {{ $ikut && $ikut->tanggal_lahir ? $tanggal($ikut->tanggal_lahir) : '' }}
                            </td>
                            <td class="garis-kiri ket">{{ $ikut->keterangan ?? '' }}</td>
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
                <table class="rincian-dalam terbit">
                    <tr>
                        <td class="k">DIKELUARKAN DI</td>
                        <td class="t">:</td>
                        <td>{{ mb_strtoupper($spd->dikeluarkan_di) }}</td>
                    </tr>
                    <tr>
                        <td class="k">TANGGAL</td>
                        <td class="t">:</td>
                        <td>{{ $tanggal($spd->tanggal_surat) }}</td>
                    </tr>
                </table>

                {{-- Blok tanda tangan rata tengah agar QR SRIKANDI jatuh
                     tepat di tengah kolom, bukan menempel ke tepi. --}}
                <div class="blok-ttd">
                    <p style="margin:6px 0 0">Pejabat Pembuat Komitmen</p>

                    {{-- Diganti QR tanda tangan elektronik oleh SRIKANDI. --}}
                    <table class="kotak-ttd"><tr><td>${ttd_pengirim1}</td></tr></table>

                    <p style="margin:0"><span class="nama-ttd">{{ $ppk?->nama ?? '' }}</span></p>
                    <p style="margin:0">NIP. {{ $ppk?->nip ?? '' }}</p>
                </div>
            </td>
        </tr>
    </table>

    <table class="kaki">
        <tr>
            <td class="pesan">
                Kementerian Kesehatan tidak menerima suap dan/atau gratifikasi dalam bentuk apapun.
                Jika terdapat potensi suap atau gratifikasi silakan laporkan melalui HALO KEMENKES
                1500567 dan <span class="tautan">https://wbs.kemkes.go.id</span>. Untuk verifikasi
                keaslian tanda tangan elektronik, silakan unggah dokumen pada laman
                <span class="tautan">https://tte.kominfo.go.id/verifyPDF</span>.
            </td>
            <td class="logo">
                <img src="{{ public_path('images/logo-akreditasi.jpg') }}"
                     alt="KAN, Garuda Sertifikasi Indonesia, dan BLU">
            </td>
        </tr>
    </table>

    <div class="pecah"></div>

    {{-- ═══════════════ HALAMAN BELAKANG ═══════════════ --}}

    <table class="belakang">
        {{-- I. Keberangkatan awal, disahkan Direktur. --}}
        <tr>
            <td class="rom">&nbsp;</td>
            <td class="sisi">&nbsp;</td>
            <td class="sisi">
                <table class="rincian-dalam">
                    <tr>
                        <td class="k">Berangkat dari</td>
                        <td class="t">:</td>
                        <td>{{ $spd->tempat_berangkat }}</td>
                    </tr>
                    <tr>
                        <td>Ke</td>
                        <td>:</td>
                        <td>{{ $spd->tempat_tujuan }}</td>
                    </tr>
                    <tr>
                        <td>Pada Tanggal</td>
                        <td>:</td>
                        <td>{{ $tanggal($spd->tanggal_berangkat) }}</td>
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
                            <td class="k">Tiba di</td>
                            <td class="t">:</td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>Pada Tanggal</td>
                            <td>:</td>
                            <td>&nbsp;</td>
                        </tr>
                    </table>
                    <p style="margin:5px 0 0" class="titik-isi">Kepala ........................................</p>
                    <div style="height:7mm"></div>
                    <p style="margin:0" class="titik-isi">(......................................)</p>
                    <p style="margin:0">NIP.</p>
                </td>
                <td class="sisi">
                    <table class="rincian-dalam">
                        <tr>
                            <td class="k">Berangkat dari</td>
                            <td class="t">:</td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>Ke</td>
                            <td>:</td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>Pada Tanggal</td>
                            <td>:</td>
                            <td>&nbsp;</td>
                        </tr>
                    </table>
                    <p style="margin:5px 0 0" class="titik-isi">Kepala ........................................</p>
                    <div style="height:7mm"></div>
                    <p style="margin:0" class="titik-isi">(......................................)</p>
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
                        <td class="k">Tiba di</td>
                        <td class="t">:</td>
                        <td>{{ $spd->tempat_berangkat }}</td>
                    </tr>
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>(Tempat Kedudukan)</td>
                    </tr>
                    <tr>
                        <td>Pada Tanggal</td>
                        <td>:</td>
                        <td class="titik-isi">....................................</td>
                    </tr>
                </table>

                {{-- Jarak atasnya lebih besar daripada kolom kanan supaya
                     kedua blok tanda tangan baris V berdiri sejajar: kolom
                     kanan didahului paragraf "Telah diperiksa" empat baris
                     dan satu baris "Pejabat yang memberi perintah", sedangkan
                     kolom kiri hanya tiga baris. Teks dan lebar kolomnya
                     tetap, jadi selisihnya pun tetap. --}}
                <div class="blok-ttd">
                    <p style="margin:37px 0 0">Direktur Poltekkes Manado</p>

                    {{-- Diganti QR tanda tangan elektronik oleh SRIKANDI. --}}
                    <table class="kotak-ttd"><tr><td>${ttd_pengirim2}</td></tr></table>

                    <p style="margin:0"><span class="nama-ttd">{{ $direktur?->nama ?? '' }}</span></p>
                    <p style="margin:0">NIP. {{ $direktur?->nip ?? '' }}</p>
                </div>
            </td>
            <td class="sisi">
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
            <td class="sisi">&nbsp;</td>
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
