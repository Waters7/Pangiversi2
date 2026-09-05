@extends('app')

@section('title', 'Panduan Penggunaan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7" x-data="{ bagian: 'pengajuan' }">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Panduan Penggunaan PANGI</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Alur perjalanan dinas dari pengajuan sampai pertanggungjawaban
        </p>
    </div>

    {{-- Alur ringkas --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-5">
        <p class="text-sm font-bold text-slate-700 mb-4">Empat tahap yang akan Anda lalui</p>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            @php
                $tahap = [
                    ['1', 'Mengajukan', 'Isi formulir usulan, lampirkan surat tugas', 'bg-blue-100 text-blue-700'],
                    ['2', 'Validasi PPK', 'PPK memeriksa kegiatan dan anggaran', 'bg-amber-100 text-amber-700'],
                    ['3', 'Berangkat', 'Uang muka cair, perjalanan dilaksanakan', 'bg-teal-100 text-teal-700'],
                    ['4', 'Pertanggungjawaban', 'Unggah berkas, periksa rincian, tanda tangan', 'bg-purple-100 text-purple-700'],
                ];
            @endphp

            @foreach ($tahap as [$nomor, $judul, $isi, $warna])
                <div class="relative p-4 rounded-xl border border-slate-100 bg-slate-50/60">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-xs font-bold {{ $warna }} mb-2">
                        {{ $nomor }}
                    </span>
                    <p class="text-sm font-bold text-slate-800">{{ $judul }}</p>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Navigasi bagian --}}
    @php
        $menu = [
            'pengajuan' => 'Mengajukan Perjadin',
            'kelompok' => 'Pengajuan Kelompok',
            'konfirmasi' => 'Konfirmasi Usulan',
            'selesai' => 'Setelah Perjalanan',
            'rincian' => 'Rincian & Tanda Tangan',
        ];
    @endphp

    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
        @foreach ($menu as $kunci => $label)
            <button @click="bagian = '{{ $kunci }}'"
                    :class="bagian === '{{ $kunci }}'
                        ? 'bg-teal-500 text-white shadow-sm'
                        : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ── Mengajukan perjadin personal ── --}}
    <div x-show="bagian === 'pengajuan'" x-transition class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Mengajukan Perjalanan Dinas untuk Diri Sendiri</h2>
            <p class="text-xs text-slate-400">Semua peran dapat mengajukan perjalanan dinas</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Buka menu Usulan → Buat Usulan">
                Dari sidebar kiri, pilih <strong>Usulan</strong> lalu <strong>Buat Usulan</strong>.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Pilih jenis pengajuan Personal">
                Pada kotak <strong>Jenis Pengajuan</strong>, pilih <strong>Personal</strong>.
                Usulan akan dibuat atas nama Anda sendiri.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Lengkapi data perjalanan">
                Isi jenis kegiatan, dasar penugasan, lokasi tujuan, instansi, serta tanggal
                berangkat dan kembali. Kolom lokasi menyediakan daftar kota yang sudah terdaftar —
                memilih dari daftar membuat usulan Anda ikut terekap pada laporan per wilayah.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Lampirkan surat tugas">
                Surat tugas wajib diunggah. Rundown kegiatan dan dokumen pendukung bersifat opsional,
                namun sangat membantu bendahara saat memeriksa.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="5" judul="Tekan Ajukan Usulan Perjadin" :terakhir="true">
                Usulan langsung berlaku karena penugasannya sudah disahkan lewat SPD. Bila belum siap, tekan <strong>Simpan Draft</strong> —
                draft dapat disunting kapan saja sebelum dikirim.
            </x-panduan-langkah>

            <div class="mt-6 flex items-start gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl">
                <svg class="w-4 h-4 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
                </svg>
                <p class="text-xs text-teal-800 leading-relaxed">
                    Lengkapi <strong>rekening bank</strong> Anda di menu Profil sebelum mengajukan.
                    Tanpa rekening, bendahara tidak dapat memproses pembayaran.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Pengajuan kelompok ── --}}
    <div x-show="bagian === 'kelompok'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Mengajukan untuk Satu Rombongan</h2>
            <p class="text-xs text-slate-400">Satu kali pengisian, tetapi tetap perorangan pertanggungjawabannya</p>
        </div>

        <div class="p-6">
            <div class="mb-6 px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-xl">
                <p class="text-xs text-indigo-900 leading-relaxed">
                    <strong>Yang perlu dipahami lebih dulu:</strong> pengajuan kelompok hanya alat bantu
                    pengisian. Sistem tetap membuat <strong>satu usulan bernomor sendiri untuk tiap orang</strong>,
                    karena pertanggungjawaban perjalanan dinas bersifat perorangan — bukan kolektif.
                </p>
            </div>

            <x-panduan-langkah nomor="1" judul="Pilih jenis pengajuan Berkelompok">
                Pada formulir Buat Usulan, pilih <strong>Berkelompok</strong>.
                Kotak daftar rekan seperjalanan akan muncul.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Tambahkan rekan seperjalanan">
                Tekan <strong>+ Tambah Pegawai</strong>, lalu pilih rekan dari daftar pegawai —
                setiap baris menampilkan NIP dan nama sehingga tidak tertukar. Jumlah total
                keberangkatan terhitung otomatis di atas daftar.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Isi data perjalanan seperti biasa">
                Data perjalanan, surat tugas, dan lampiran cukup diisi sekali. Seluruh usulan yang
                terbentuk memakai data yang sama.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Kirim untuk dikonfirmasi" :terakhir="true">
                Usulan milik Anda langsung masuk ke PPK. Usulan milik rekan
                <strong>menunggu konfirmasi mereka</strong> lebih dulu, baru diteruskan ke PPK.
            </x-panduan-langkah>

            <div class="mt-6 rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                    <p class="text-xs font-bold text-slate-600">Contoh: rombongan 3 orang</p>
                </div>
                <div class="p-4 space-y-2 text-xs text-slate-600">
                    <p>Anda mengisi formulir <strong>satu kali</strong>, sistem membuat:</p>
                    <ul class="space-y-1.5 mt-2">
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-teal-500 shrink-0"></span>
                            <span class="font-mono text-slate-500">USL-2026-041</span> — milik Anda, langsung ke PPK
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                            <span class="font-mono text-slate-500">USL-2026-042</span> — milik rekan A, menunggu konfirmasinya
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                            <span class="font-mono text-slate-500">USL-2026-043</span> — milik rekan B, menunggu konfirmasinya
                        </li>
                    </ul>
                    <p class="pt-2 border-t border-slate-100 mt-3">
                        Ketiganya tertaut sebagai satu rombongan dan saling terlihat pada halaman detail,
                        namun biaya, dokumen, dan tanda tangan diurus sendiri-sendiri.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Konfirmasi usulan ── --}}
    <div x-show="bagian === 'konfirmasi'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Saat Usulan Dibuatkan Orang Lain</h2>
            <p class="text-xs text-slate-400">Keputusan tetap di tangan Anda</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Anda menerima notifikasi">
                Lonceng notifikasi di kanan atas berbunyi dengan pesan
                <em>“Usulan perjalanan dinas dibuatkan untuk Anda”</em>.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Buka Daftar Usulan">
                Usulan tersebut muncul di daftar Anda dengan penanda
                <strong>Dibuatkan [nama]</strong> beserta dua tombol aksi.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Pilih Konfirmasi atau Tolak" :terakhir="true">
                <strong>Konfirmasi</strong> berarti Anda bersedia berangkat — usulan langsung diteruskan
                ke PPK untuk divalidasi. <strong>Tolak</strong> membatalkan usulan itu saja; rekan
                serombongan Anda tidak terpengaruh. Anda dapat menyertakan alasan pembatalan.
            </x-panduan-langkah>

            <div class="mt-6 flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-100 rounded-xl">
                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <p class="text-xs text-amber-800 leading-relaxed">
                    Kesediaan hanya dapat diubah <strong>sebelum PPK memvalidasi</strong>.
                    Setelah divalidasi, pembatalan harus melalui PPK.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Setelah perjalanan ── --}}
    <div x-show="bagian === 'selesai'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Setelah Perjalanan Selesai</h2>
            <p class="text-xs text-slate-400">Melengkapi berkas pertanggungjawaban</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Buka menu Dokumen">
                Pilih usulan yang perjalanannya sudah selesai, lalu unggah berkas sesuai kelompoknya.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Unggah seluruh berkas wajib" :terakhir="true">
                <span class="block mb-2">Tujuh berkas berikut wajib lengkap sebelum usulan dapat ditutup:</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                    @foreach (['SPPD', 'Boarding pass', 'Nota/biaya transportasi', 'Faktur', 'Kuitansi', 'Bill hotel', 'Laporan hasil'] as $berkas)
                        <span class="inline-flex items-center gap-2 text-xs text-slate-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-300 shrink-0"></span>
                            {{ $berkas }}
                        </span>
                    @endforeach
                </div>
            </x-panduan-langkah>

            <div class="mt-6 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
                <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <div class="text-xs text-red-800 leading-relaxed">
                    <p class="font-bold mb-1">Nota transportasi menentukan penggantian biaya</p>
                    <p>
                        Tanpa nota atau bukti biaya transportasi selama perjalanan,
                        biaya tersebut <strong>tidak dapat diganti</strong> oleh tim keuangan.
                        Simpan seluruh bukti sejak hari keberangkatan.
                    </p>
                </div>
            </div>

            <p class="text-xs text-slate-400 mt-4 leading-relaxed">
                Bila berkas belum lengkap sampai batas waktu, tim keuangan akan menghubungi Anda
                melalui WhatsApp. Pastikan nomor Anda terdaftar di menu Profil.
            </p>
        </div>
    </div>

    {{-- ── Rincian & tanda tangan ── --}}
    <div x-show="bagian === 'rincian'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Melihat Rincian dan Menandatanganinya</h2>
            <p class="text-xs text-slate-400">Termasuk cara menyanggah bila nominalnya tidak sesuai</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Tim keuangan menyusun rincian biaya">
                Setelah berkas Anda lengkap, tim keuangan menghitung rincian sesuai
                Lampiran II PMK 113/PMK.05/2012: transport, uang harian, transport lokal,
                dan uang penginapan.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Anda menerima notifikasi untuk memeriksa">
                Pesan <em>“Rincian biaya menunggu tanda tangan Anda”</em> muncul di lonceng
                notifikasi, memuat nominal total dan batas waktu sanggah.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Buka menu Rincian Saya">
                Menu <strong>Rincian Saya</strong> di sidebar menampilkan seluruh rincian yang
                menunggu tanggapan Anda, lengkap dengan sisa hari masa sanggah.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Setujui, atau ajukan sanggahan">
                Bila nominalnya sudah sesuai, tekan <strong>Setuju &amp; Tandatangani</strong>.
                Bila menurut Anda ada yang keliru, tekan <strong>Sanggah</strong> dan jelaskan
                bagian mana yang tidak sesuai — rincian akan dikembalikan ke tim keuangan
                untuk diperbaiki.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="5" judul="PPK membubuhkan tanda tangan" :terakhir="true">
                Setelah Anda menyetujui, PPK menandatangani daftar pengeluaran riil.
                Dokumen hasilnya memuat <strong>QR code verifikasi</strong> yang dapat dipindai
                siapa pun untuk memastikan keasliannya.
            </x-panduan-langkah>

            <div class="mt-6 rounded-xl border border-amber-200 overflow-hidden">
                <div class="px-4 py-2.5 bg-amber-50 border-b border-amber-200 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                    </svg>
                    <p class="text-xs font-bold text-amber-800">Masa sanggah {{ $hariSanggah }} hari</p>
                </div>
                <div class="p-4 text-xs text-slate-600 leading-relaxed space-y-2">
                    <p>
                        Terhitung sejak rincian dikirim, Anda punya <strong>{{ $hariSanggah }} hari</strong>
                        untuk menyatakan sikap.
                    </p>
                    <p>
                        Bila sampai batas waktu tidak ada tanggapan, nominal
                        <strong>dianggap Anda terima</strong> dan PPK dapat langsung menandatanganinya.
                        Karena itu, periksa notifikasi Anda secara berkala.
                    </p>
                </div>
            </div>

            <div class="mt-4 flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
                <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Dokumen rincian dan daftar pengeluaran riil dapat diunduh sebagai PDF —
                    formatnya sudah mengikuti lampiran resmi PMK, siap dicetak dan diarsipkan.
                </p>
            </div>
        </div>
    </div>

    <p class="text-xs text-slate-400 text-center mt-8">
        Masih ada yang kurang jelas? Hubungi Tim SDM atau administrator sistem.
    </p>

</div>

@endsection
