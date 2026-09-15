@extends('app')

@section('title', 'Panduan Penggunaan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7" x-data="{ bagian: 'spd' }">

    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Panduan Penggunaan PANGI</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Alur perjalanan dinas dari terbitnya SPD sampai pelunasan
                <span class="ml-1 font-mono text-[11px] text-slate-400">PANGI {{ $versi }}</span>
            </p>
        </div>

        {{-- Yang di layar menyesuaikan peran pembacanya; berkas unduhan memuat
             seluruh peran sekaligus, untuk dicetak atau dibagikan. --}}
        @if ($bukuTersedia)
            <a href="{{ route('panduan.unduh') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold transition whitespace-nowrap self-start">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Unduh Buku Panduan
            </a>
        @endif
    </div>

    @if ($bukuTersedia)
        <div class="mb-5 flex items-start gap-3 px-4 py-3 rounded-xl bg-slate-50 border border-slate-200">
            <svg class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
            </svg>
            <p class="text-xs text-slate-600 leading-relaxed">
                Halaman ini hanya menampilkan bagian yang berlaku bagi peran Anda.
                <strong>Buku panduan lengkap</strong> memuat seluruh peran sekaligus beserta
                rujukan kategori, status, penomoran, dan kendala umum &mdash; berkas Word,
                siap dicetak. <span class="font-mono text-[11px] text-slate-400">{{ $namaBuku }}</span>
            </p>
        </div>
    @endif

    {{-- Peran yang sedang membuka, beserta pekerjaan yang menjadi
         tanggung jawabnya di luar bepergian sendiri. --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
        <div class="flex flex-wrap items-center gap-2.5">
            <p class="text-sm text-slate-600">Anda masuk sebagai</p>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-teal-100 text-teal-700">
                {{ $peran->label() }}
            </span>
        </div>

        @if ($tugasPeran === [])
            <p class="text-xs text-slate-500 mt-2.5 leading-relaxed">
                Panduan ini memuat seluruh yang perlu Anda lakukan: menerbitkan SPD, mengajukan
                perjalanan dinas, melengkapi berkasnya, lalu memeriksa dan menandatangani rincian
                biayanya.
            </p>
        @else
            <p class="text-xs text-slate-500 mt-2.5 mb-3 leading-relaxed">
                Seperti semua pengguna, Anda dapat mengajukan perjalanan dinas untuk diri sendiri.
                Di luar itu, peran Anda menangani:
            </p>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($tugasPeran as $baris)
                    <button type="button" @click="bagian = '{{ $baris['bagian'] }}'"
                            class="group flex items-start gap-2.5 text-left px-3 py-2.5 rounded-xl border border-slate-200 hover:border-teal-300 hover:bg-teal-50/40 transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500 shrink-0 mt-1.5"></span>
                        <span class="text-xs text-slate-700 group-hover:text-teal-800 leading-relaxed">
                            {{ $baris['tugas'] }}
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Alur ringkas satu berkas perjalanan dinas, dari terbit sampai lunas. --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-5">
        <p class="text-sm font-bold text-slate-700 mb-4">Lima tahap yang dilalui setiap berkas</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
            @php
                $tahap = [
                    ['1', 'SPD terbit', 'Penugasan disahkan lewat Surat Perjalanan Dinas', 'bg-slate-200 text-slate-700'],
                    ['2', 'Usulan diajukan', 'Pelaksana mengisi usulan yang mengacu pada SPD', 'bg-blue-100 text-blue-700'],
                    ['3', 'Berangkat', 'Uang muka cair, perjalanan dilaksanakan', 'bg-teal-100 text-teal-700'],
                    ['4', 'Dipertanggungjawabkan', 'Berkas diunggah, rincian disusun dan divalidasi', 'bg-amber-100 text-amber-700'],
                    ['5', 'Ditandatangani & lunas', 'Pelaksana dan PPK menandatangani, bendahara melunasi', 'bg-purple-100 text-purple-700'],
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

    {{-- Navigasi bagian. Isinya menyesuaikan kewenangan yang membuka. --}}
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
        @foreach ($bagian as $kunci => $label)
            <button type="button" @click="bagian = '{{ $kunci }}'"
                    :class="bagian === '{{ $kunci }}'
                        ? 'bg-teal-500 text-white shadow-sm'
                        : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ── Menerbitkan SPD ── --}}
    <div x-show="bagian === 'spd'" x-transition class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Menerbitkan Surat Perjalanan Dinas</h2>
            <p class="text-xs text-slate-400">Langkah pertama — tanpa SPD, usulan belum dapat diajukan</p>
        </div>

        <div class="p-6">
            <div class="mb-6 px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-xl">
                <p class="text-xs text-indigo-900 leading-relaxed">
                    <strong>Mengapa SPD lebih dulu:</strong> SPD adalah dasar penugasannya. Selama
                    Anda belum tercantum pada satu SPD pun, tombol Buat Usulan akan menolak dan
                    mengarahkan Anda ke sini.
                </p>
            </div>

            <x-panduan-langkah nomor="1" judul="Buka menu Buat SPD → Pembuatan SPD">
                Seluruh peran boleh menerbitkan SPD, dan tiap SPD hanya dapat disunting oleh yang
                membuatnya atau yang namanya tercantum di dalamnya.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Nomor surat terbit sendiri">
                Nomor tidak perlu diketik. Sistem merakitnya mengikuti pola nomor perjadin —
                <span class="font-mono text-[11px] bg-slate-100 px-1.5 py-0.5 rounded">PJ-[UNIT]-[tahun]-[bulan]-[urut]</span>
                — dengan bulan mengikuti tanggal keberangkatan, sehingga tidak pernah ada dua
                SPD bernomor sama. Pada dokumen cetaknya, baris Nomor diisi penanda
                <span class="font-mono text-[11px] bg-slate-100 px-1.5 py-0.5 rounded">${nomor_naskah}</span>
                yang diganti SRIKANDI saat surat diregistrasi.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Lengkapi maksud, tujuan, dan tanggal">
                Isian ini akan tersalin sendiri ke formulir usulan nanti, sehingga tanggal pada SPD
                dan pada usulan tidak pernah berselisih.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Tambahkan pengikut bila ada">
                Pengikut adalah orang yang ikut berangkat tanpa menjadi pelaksana — namanya
                tercantum pada SPD, tetapi ia tidak mengajukan usulan sendiri.
                @can('mengisi-pengikut-spd')
                    Bagian ini hanya muncul bagi Anda; peran lain tidak dapat mengisinya, dan
                    pengikut yang Anda pasang tidak akan terhapus saat pelaksana menyunting
                    suratnya sendiri.
                @else
                    Kolomnya <strong>hanya tersedia bagi Pimpinan dan Super Administrator</strong>,
                    karena merekalah yang memutuskan siapa boleh ikut berangkat. Sampaikan
                    permintaannya kepada mereka bila perjalanan ini membawa pengikut.
                @endcan
            </x-panduan-langkah>

            <x-panduan-langkah nomor="5" judul="Pratinjau, lalu simpan" :terakhir="true">
                Tombol <strong>Pratinjau</strong> menampilkan dokumennya persis seperti yang akan
                tercetak, tanpa menyimpan apa pun. Setelah disimpan, setiap pelaksana yang
                tercantum menerima pemberitahuan bahwa SPD-nya sudah terbit dan usulan sudah
                dapat diajukan.
            </x-panduan-langkah>
        </div>
    </div>

    {{-- ── Mengajukan perjadin ── --}}
    <div x-show="bagian === 'pengajuan'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Mengajukan Perjalanan Dinas untuk Diri Sendiri</h2>
            <p class="text-xs text-slate-400">Semua peran dapat mengajukan perjalanan dinas</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Buka menu Usulan Perjadin → Buat Usulan Perjadin">
                Dari sidebar kiri, pilih <strong>Usulan Perjadin</strong> lalu
                <strong>Buat Usulan Perjadin</strong>.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Pilih SPD yang mendasari">
                Daftar SPD Anda muncul di bagian atas formulir. Begitu satu dipilih, tujuan,
                tanggal, dan maksud perjalanan terisi sendiri dari SPD tersebut.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Unggah SPD bertanda tangan dan salin nomornya">
                Pada <strong>Data Dasar Perjalanan</strong>, unggah SPD yang sudah ditandatangani
                PPK dan Direktur lewat SRIKANDI, lalu salin <strong>nomor naskahnya</strong> persis
                seperti pada dokumen. Keduanya wajib — pengajuan tidak dapat dikirim tanpanya,
                dan nomor itulah yang tercatat sebagai dasar persetujuan PPK pada jejak audit.
                Pengajuan selalu atas nama Anda sendiri; rekan seperjalanan mengajukan
                usulannya masing-masing dengan SPD bertanda tangannya sendiri.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Pilih kategori perjalanan dengan benar">
                Kategori menentukan berkas apa yang nanti ditagih sistem. Perjalanan
                <strong>dalam kota</strong> tidak akan diminta tiket, penginapan, maupun kuitansi;
                <strong>luar kota</strong> diminta lengkap. Salah memilih di sini berarti ditagih
                berkas yang tidak pernah ada.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="5" judul="Lengkapi kegiatan dan lokasi">
                Isi jenis kegiatan, lokasi tujuan, dan instansi. Kolom lokasi menyediakan daftar
                kota yang sudah terdaftar — memilih dari daftar membuat usulan Anda ikut terekap
                pada laporan per wilayah.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="6" judul="Tekan Kirim Pengajuan Perjadin" :terakhir="true">
                Sebuah kotak konfirmasi muncul lebih dulu, memuat ringkasan isian Anda —
                periksa sekali lagi sebelum menekan lanjut. Pengajuan langsung berlaku karena
                penugasannya sudah disahkan lewat SPD bertanda tangan. Bila belum siap, tekan
                <strong>Simpan Draft</strong>.
            </x-panduan-langkah>

            <div class="mt-6 grid gap-3">
                <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-100 rounded-xl">
                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                    </svg>
                    <div class="text-xs text-amber-900 leading-relaxed">
                        <p class="font-bold mb-1">Isian tidak hilang</p>
                        <p>
                            Formulir menyimpan sendiri isian Anda di peramban ini. Bila tab
                            tertutup atau sesi habis, isinya dipulihkan saat formulir dibuka lagi.
                            Berkas unggahan perlu dipilih ulang.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-3 flex items-start gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl">
                <svg class="w-4 h-4 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
                </svg>
                <p class="text-xs text-teal-800 leading-relaxed">
                    Lengkapi <strong>rekening bank</strong> Anda di menu Profil. Tanpa rekening,
                    bendahara tidak dapat memproses pembayaran — dan itu baru ketahuan di tahap
                    paling akhir, saat Anda sudah pulang dinas.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Berkas pertanggungjawaban ── --}}
    <div x-show="bagian === 'berkas'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Setelah Perjalanan Selesai</h2>
            <p class="text-xs text-slate-400">Berkas yang ditagih berbeda menurut wilayah perjalanannya</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Buka menu Dokumen → Dokumen Perdin">
                Pilih usulan yang perjalanannya sudah selesai. Halaman formulirnya sudah
                menyesuaikan diri: bagian yang tidak berlaku bagi perjalanan Anda tidak
                ditampilkan sama sekali.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Unggah berkas sesuai wilayah perjalanan">
                <span class="block mb-3">Daftar di bawah ini yang menentukan usulan boleh ditutup atau belum.</span>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        <div class="px-3 py-2 bg-slate-50 border-b border-slate-200">
                            <p class="text-[11px] font-bold text-slate-600">Dalam kota</p>
                        </div>
                        <ul class="p-3 space-y-1.5">
                            @foreach (['SPPD bertanda tangan', 'Nota transport lokal — bila ada biaya', 'Bukti bayar biaya penyelenggaraan — bila ada', 'Laporan perjalanan dinas, dikonfirmasi Direktur'] as $berkas)
                                <li class="flex items-start gap-2 text-xs text-slate-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500 shrink-0 mt-1.5"></span>
                                    {{ $berkas }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        <div class="px-3 py-2 bg-slate-50 border-b border-slate-200">
                            <p class="text-[11px] font-bold text-slate-600">Luar kota</p>
                        </div>
                        <ul class="p-3 space-y-1.5">
                            @foreach (['SPPD bertanda tangan', 'Tiket pergi: boarding pass & invoice', 'Tiket pulang: boarding pass & invoice', 'Nota transport lokal — bila ada biaya', 'Bill hotel beserta nomor transaksi & nominal', 'Kuitansi', 'Bukti bayar biaya penyelenggaraan — bila ada', 'Laporan perjalanan dinas, dikonfirmasi Direktur'] as $berkas)
                                <li class="flex items-start gap-2 text-xs text-slate-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0 mt-1.5"></span>
                                    {{ $berkas }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Susun laporan perjalanan dinas">
                Menu <strong>Dokumen → List Laporan Perjadin</strong> memuat formulir laporannya:
                tempat dan uraian kegiatan tiap hari, hasil yang dicapai, kesimpulan, dan rencana
                tindak lanjut. <strong>Simpan Draf</strong> menyimpan isinya tanpa mengirim, jadi
                laporan boleh dicicil. Laporan yang belum dikirim menahan berkas Anda dinyatakan lengkap.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Simpan dan kirim ke pimpinan" :terakhir="true">
                Bila sudah lengkap, tekan <strong>Simpan &amp; Kirim ke Pimpinan</strong>; sebuah kotak
                konfirmasi muncul lebih dulu. Isi tersimpan, laporan dinyatakan selesai, dan langsung
                dikirim. Tanda tangan Anda terbit sebagai QR pada dokumen — memuat nomor surat, tanggal
                perjalanan, tanggal pembuatan, dan nama Anda — dan laporan terkunci selama diperiksa.
                Pimpinan <strong>mengonfirmasi</strong> (tanda tangannya ikut terbit sebagai QR) atau
                <strong>mengembalikannya</strong> dengan arahan revisi; bila dikembalikan, perbaiki lalu
                tekan Simpan &amp; Kirim lagi. Pelunasan pembayaran baru dapat diproses setelah laporan
                dikonfirmasi pimpinan.
            </x-panduan-langkah>

            <div class="mt-6 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
                <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <div class="text-xs text-red-800 leading-relaxed">
                    <p class="font-bold mb-1">Nota menentukan penggantian biaya</p>
                    <p>
                        Nota transport lokal hanya diminta untuk ruas yang <strong>bernominal</strong>;
                        ruas tanpa biaya dikosongkan, dan perjalanan tanpa transport lokal sama sekali tetap
                        dapat selesai. Perjalanan dalam kota cukup satu baris transport lokal. Nominal tanpa
                        nota <strong>tidak dapat diganti</strong> — simpan seluruh bukti sejak hari keberangkatan.
                    </p>
                    <p class="mt-2">
                        <strong>Biaya penyelenggaraan</strong> (kontribusi atau registrasi kegiatan) ditanya
                        dulu ada atau tidak. Bila ada: isi nominal, unggah bukti bayar, dan nomor invoice
                        bila diterbitkan penyelenggara; nominalnya tercantum pada rincian biaya di bawah
                        uang penginapan. Bila tidak, kolomnya tidak ditampilkan.
                    </p>
                </div>
            </div>

            <p class="text-xs text-slate-400 mt-4 leading-relaxed">
                Bila berkas belum lengkap sampai batas waktu, tim keuangan akan menghubungi Anda
                melalui WhatsApp. Pastikan nomor Anda terdaftar di menu Profil.
            </p>
        </div>
    </div>

    {{-- ── Memeriksa & menandatangani ── --}}
    <div x-show="bagian === 'rincian'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Memeriksa dan Menandatangani Berkas Anda</h2>
            <p class="text-xs text-slate-400">Dua dokumen terpisah, masing-masing dengan tanda tangannya sendiri</p>
        </div>

        <div class="p-6">
            <div class="mb-6 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                        <p class="text-xs font-bold text-slate-700">Rincian Biaya Saya</p>
                        <p class="text-[11px] text-slate-400">Lampiran II PMK 113/PMK.05/2012</p>
                    </div>
                    <p class="p-4 text-xs text-slate-600 leading-relaxed">
                        Seluruh komponen biaya <strong>kecuali transport lokal</strong>: tiket,
                        uang harian, uang penginapan, dan biaya penyelenggaraan.
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                        <p class="text-xs font-bold text-slate-700">Daftar Riil Saya</p>
                        <p class="text-[11px] text-slate-400">Lampiran IX PMK 113/PMK.05/2012</p>
                    </div>
                    <p class="p-4 text-xs text-slate-600 leading-relaxed">
                        Khusus <strong>transport lokal</strong> — biaya nyata yang tidak
                        seluruhnya didukung bukti dan dinyatakan sendiri oleh pelaksana.
                    </p>
                </div>
            </div>

            <x-panduan-langkah nomor="1" judul="Tim keuangan menyusun dan memvalidasi">
                Setelah berkas Anda lengkap, tim keuangan menghitung rincian biayanya lalu
                menyatakan tiap komponennya benar.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Berkas terkirim kepada Anda">
                Begitu seluruh komponen divalidasi, berkas <strong>terkirim sendiri</strong> —
                berstatus <strong>Sudah Dicek Tim Keuangan</strong> — dan pesan
                <em>“Berkas menunggu tanda tangan Anda”</em> muncul di lonceng pemberitahuan.
                Menu <strong>Rincian Saya</strong> di sidebar memuat keduanya, masing-masing pada
                submenunya sendiri.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Sikapi tiap dokumen sendiri-sendiri">
                Keduanya dokumen yang berbeda, jadi keputusannya juga terpisah — Anda boleh
                menyetujui rincian biaya sambil menyanggah daftar riil, atau sebaliknya.
                Menandatangani yang satu tidak ikut mengesahkan yang lain.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Setujui, atau ajukan sanggahan">
                Bila nominalnya sesuai, tekan <strong>Setuju &amp; Tandatangani</strong>. Bila ada
                yang keliru, tekan <strong>Sanggah</strong> dan jelaskan bagian mana yang tidak
                sesuai — berkas itu dikembalikan ke tim keuangan untuk diperbaiki, sementara
                dokumen satunya tetap berjalan.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="5" judul="PPK membubuhkan tanda tangan" :terakhir="true">
                Setelah Anda menyetujui, PPK menandatangani. Berkas yang sudah ditandatangani
                <strong>terkunci</strong> — tidak dapat diubah lagi oleh siapa pun, termasuk tim
                keuangan, supaya angka pada dokumen cetak selalu sama dengan angka di sistem.
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
                        Terhitung sejak berkas dikirim, Anda punya <strong>{{ $hariSanggah }} hari</strong>
                        untuk menyatakan sikap.
                    </p>
                    <p>
                        Bila sampai batas waktu tidak ada tanggapan, nominal
                        <strong>dianggap Anda terima</strong> dan PPK dapat langsung
                        menandatanganinya. Tombol tanda tangan Anda <strong>tetap tersedia</strong>
                        sampai PPK mengesahkan; yang tertutup hanyalah sanggahan. Karena itu, periksa
                        pemberitahuan Anda secara berkala.
                    </p>
                </div>
            </div>

            <div class="mt-4 flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
                <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Kedua dokumen dapat diunduh sebagai PDF berformat lampiran resmi PMK pada kertas
                    <strong>folio (F4)</strong>, siap dicetak dan diarsipkan. Keduanya memuat
                    <strong>QR code verifikasi</strong> yang dapat dipindai siapa pun untuk
                    memastikan keasliannya.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Tim keuangan ── --}}
    @if (array_key_exists('keuangan', $bagian))
    <div x-show="bagian === 'keuangan'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Menyusun dan Memvalidasi Rincian Biaya</h2>
            <p class="text-xs text-slate-400">Menu Keuangan — tim keuangan menyatakan nominalnya benar</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Mulai dari antrean di halaman muka">
                Panel <strong>Menunggu Tindakan Anda</strong> menyebutkan berapa berkas yang
                komponennya belum divalidasi, tertaut langsung ke daftarnya. Lencana angka pada
                menu Keuangan menghitung hal yang sama.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Buka Keuangan → Input Rincian Biaya">
                Pilih usulan yang berkasnya sudah lengkap. Nominal dari dokumen yang diunggah
                pelaksana sudah diselaraskan lebih dulu, jadi Anda memeriksa dan menyesuaikan —
                bukan mengetik dari nol.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Validasi tiap komponen">
                Tiap baris divalidasi sendiri-sendiri. Sebuah kotak konfirmasi muncul sebelum
                validasi tersimpan, memuat komponen dan nominal yang akan dinyatakan benar;
                mencabut validasi juga dikonfirmasi dan tercatat pada jejak audit.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Periksa transport lokal">
                Submenu <strong>Periksa Transport Lokal</strong> memuat biaya transport yang
                dinyatakan pelaksana pada daftar riilnya, beserta bukti yang dilampirkan.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="5" judul="Berkas terkirim sendiri ke pelaksana">
                Begitu seluruh komponen dan transport lokalnya tervalidasi, kedua dokumen
                <strong>otomatis terkirim</strong> kepada pelaksana dan masa sanggah
                {{ $hariSanggah }} hari mulai berjalan. Bila belum dapat dikirim, tombol kirim
                menyebutkan komponen mana yang menahan.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="6" judul="Pantau daftar nominatif dan arsip" :terakhir="true">
                Menu <strong>Laporan → List Daftar Nominatif</strong> memuat seluruh daftar yang
                terbit — menunggu PPK, sudah ditandatangani, sudah diterima — beserta siapa saja
                pelaksana yang sudah menandatangani berkasnya; kategori dan akun pembiayaan
                ditetapkan setelah daftar diterima. Rincian yang sudah ditandatangani pelaksana
                dan PPK langsung tampil di <strong>Arsip Rincian Lengkap</strong>.
            </x-panduan-langkah>

            <div class="mt-6 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
                <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>
                </svg>
                <div class="text-xs text-red-800 leading-relaxed">
                    <p class="font-bold mb-1">Berkas bertanda tangan terkunci</p>
                    <p>
                        Setelah pelaksana atau PPK menandatangani, rincian tidak dapat disunting
                        lagi. Bila memang perlu diperbaiki, mintalah PPK membatalkan tanda
                        tangannya lebih dulu — bukan mengubah angkanya diam-diam.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── PPK ── --}}
    @if (array_key_exists('ppk', $bagian))
    <div x-show="bagian === 'ppk'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Menandatangani sebagai PPK</h2>
            <p class="text-xs text-slate-400">Tiga berkas yang menunggu keputusan Anda</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Lihat antrean Anda lebih dulu">
                Panel <strong>Menunggu Tindakan Anda</strong> di halaman muka memecah antrean
                menjadi tiga: rincian biaya, daftar riil, dan daftar nominatif. Lencana angka pada
                menu Persetujuan menghitung ketiganya.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Verifikasi Rincian Biaya">
                Tab <strong>Perlu Tindakan</strong> hanya memuat berkas yang benar-benar siap:
                sudah disetujui pelaksana, atau masa sanggahnya lewat tanpa keberatan. Berkas yang
                masih di tangan tim keuangan tetap terlihat, tetapi tanpa tombol yang belum boleh
                ditekan.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Verifikasi Daftar Riil">
                Dokumen yang terpisah, jadi keputusannya juga terpisah. Bila ada yang tidak sesuai,
                kembalikan ke tim keuangan disertai alasannya.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Verifikasi Daftar Nominatif">
                Nominatif terbit sendiri per surat tugas begitu <em>satu</em> pelaksana di
                bawahnya tuntas — rincian biaya dan daftar riilnya Anda tandatangani — dan hanya
                memuat yang sudah tuntas. Yang belum tercantum disebut di bawah tabelnya dan
                bertambah sendiri setelah berkasnya Anda sahkan. Ketiga menu verifikasi
                dikelompokkan per bulan dan tahun.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="5" judul="Kirim nominatif ke tim keuangan" :terakhir="true">
                Setelah ditandatangani, kirimkan daftarnya ke tim keuangan sebagai dasar
                pembayaran. Tanda tangannya elektronik: cetakan nominatif memuat QR dan kode
                verifikasi yang dapat dipindai siapa pun.
            </x-panduan-langkah>

            <div class="mt-6 px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-xl text-xs text-indigo-900 leading-relaxed">
                <p class="font-bold mb-1">Persetujuan usulan tidak lagi lewat tombol</p>
                <p>
                    Tanda tangan Anda pada SPD adalah persetujuannya: saat pelaksana mengirim usulan
                    bersama SPD bertanda tangan, persetujuan PPK tercatat sendiri pada rantai
                    persetujuan dan jejak audit. Menu <strong>Keuangan</strong> terbuka bagi Anda
                    dalam mode lihat saja — seluruh usulan dapat dibuka tanpa satu pun tombol ubah.
                </p>
            </div>

            <div class="mt-6 flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
                <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                </svg>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Menu <strong>Riwayat Tanda Tangan</strong> memuat seluruh berkas yang pernah
                    Anda tandatangani. Dari sana pula tanda tangan dapat dibatalkan bila memang
                    ada yang harus diperbaiki.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Bendahara ── --}}
    @if (array_key_exists('bendahara', $bagian))
    <div x-show="bagian === 'bendahara'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Mencatat Pembayaran</h2>
            <p class="text-xs text-slate-400">Menu Pembayaran — uang muka, pelunasan, dan transport lokal</p>
        </div>

        <div class="p-6">
            <div class="mb-6 px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-xl">
                <p class="text-xs text-indigo-900 leading-relaxed">
                    <strong>Bendahara tidak memvalidasi biaya.</strong> Nominalnya sudah dinyatakan
                    benar oleh tim keuangan dan disahkan PPK. Yang Anda catat adalah pembayarannya:
                    kapan, berapa, dan buktinya mana.
                </p>
            </div>

            <x-panduan-langkah nomor="1" judul="Buka Pembayaran → Daftar Pembayaran">
                Tab bawaannya <strong>Menunggu Pembayaran</strong>: perjalanan yang sudah berlaku
                tetapi uang mukanya belum ditransfer. Tab lain memuat yang sedang berjalan, yang
                sudah menerima uang muka, dan yang sudah lunas.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Catat uang muka">
                Isi tanggal transfer dan unggah buktinya. Kelengkapan berkas pelaksana ditampilkan
                di tiap baris, sehingga terlihat mana yang belum boleh dilunasi.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Catat pelunasan">
                Satu-satunya syaratnya: <strong>laporan perjalanan dinas sudah dikonfirmasi
                Direktur</strong> lewat QR — tanda tangan pelaksana, PPK, maupun daftar nominatif
                tidak menahannya, dan halaman keuangan menampilkan syarat ini sebelum bukti
                transfer diunggah. Selisih antara uang muka dan nominal akhir terhitung sendiri.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Bayar transport lokal">
                Submenu <strong>Bayar Transport Lokal</strong> memuat daftar riil yang sudah
                ditandatangani penuh. Isi tanggal pembayaran dan buktinya. Yang sudah dibayar di
                sini tidak ikut terhitung lagi pada pelunasan; yang keliru dapat dibatalkan
                dengan menyebut alasannya.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="5" judul="Telusuri lewat Riwayat Pembayaran" :terakhir="true">
                Seluruh pembayaran yang pernah Anda catat — termasuk pembatalannya — tercatat di
                submenu <strong>Riwayat Pembayaran</strong>, dapat disaring per jenis, bulan, dan
                tahun.
            </x-panduan-langkah>

            <div class="mt-6 flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-100 rounded-xl">
                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <p class="text-xs text-amber-800 leading-relaxed">
                    Pembayaran yang telanjur salah dapat dibatalkan, dan pembatalannya ikut
                    tercatat di riwayat sebagai nilai negatif — bukan dihapus. Rekapitulasi
                    keuangan harus dapat ditelusuri, termasuk kekeliruannya.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Mengonfirmasi laporan perjadin (pimpinan) ── --}}
    @if (array_key_exists('laporan-pimpinan', $bagian))
    <div x-show="bagian === 'laporan-pimpinan'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Mengonfirmasi Laporan Perjadin</h2>
            <p class="text-xs text-slate-400">Menu Laporan Perjadin — konfirmasi, tanda tangan, dan revisi laporan pelaksana</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Buka Laporan Perjadin → Daftar Laporan Perjadin">
                Angka pada menu menunjukkan berapa laporan yang menunggu Anda. Tab
                <strong>Menunggu Konfirmasi</strong> terbuka lebih dulu; tab lain memperlihatkan
                laporan yang sedang direvisi, sudah dikonfirmasi, atau belum dikirim.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Periksa isinya">
                Tekan <strong>Periksa</strong>. Halaman keputusan memuat dasar pelaksanaan, uraian
                kegiatan per hari, rencana tindak lanjut, dan kesimpulan — persis yang tercetak
                pada dokumennya, yang juga dapat diunduh dari sana.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Konfirmasi &amp; tanda tangan, atau kembalikan">
                <strong>Konfirmasi</strong> membubuhkan tanda tangan Anda sebagai QR pada dokumen —
                memuat nama dan tanggal konfirmasi — lalu mengunci laporan dan membuka pelunasan
                pembayarannya. <strong>Kembalikan untuk Revisi</strong> wajib disertai arahan;
                laporan terbuka lagi bagi pelaksana beserta catatan Anda, dan ia mengirim ulang
                setelah memperbaikinya. Konfirmasi yang keliru masih dapat dicabut selama
                pelunasannya belum dibayarkan.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="Pantau lewat Status Konfirmasi dan Tindak Lanjut" :terakhir="true">
                <strong>Status Konfirmasi Laporan</strong> merekap berapa laporan pada tiap tahap;
                klik kartunya untuk menyaring. <strong>Tindak Lanjut</strong> memuat seluruh rencana
                tindak lanjut dari laporan yang sudah dikirim, lengkap dengan yang lewat target.
            </x-panduan-langkah>
        </div>
    </div>
    @endif

    {{-- ── Pemantauan & laporan ── --}}
    @if (array_key_exists('pemantauan', $bagian))
    <div x-show="bagian === 'pemantauan'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Memantau dan Melaporkan</h2>
            <p class="text-xs text-slate-400">Dashboard Eksekutif dan menu Laporan</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Dashboard Eksekutif">
                Realisasi anggaran per kategori dan per bulan, pergerakan pegawai per unit kerja,
                serta siapa yang akan berangkat, sedang berjalan, dan belum melapor. Tahun
                anggarannya dapat dipilih.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Rekap Perjadin">
                Rekapitulasi perjalanan dinas yang dikelompokkan per bulan, dengan ekspor daftar
                nominatif ke Excel menurut bulan dan tahun yang dipilih.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Arsip Daftar Riil dan Arsip Rincian Lengkap">
                Arsip dokumen pertanggungjawaban yang sudah ditandatangani pelaksana dan PPK,
                dikelompokkan per bulan dan tahun, siap dicetak ulang kapan pun dibutuhkan
                pemeriksa — tanpa menunggu daftar nominatif.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="4" judul="List Daftar Nominatif" :terakhir="true">
                Seluruh daftar nominatif yang terbit — menunggu PPK, sudah ditandatangani, sudah
                diterima tim keuangan — dengan saringan status dan keterangan siapa saja pelaksana
                yang sudah menandatangani berkasnya. Di sini pula pembebanan kategori dan akun
                anggarannya ditetapkan setelah daftar diterima.
            </x-panduan-langkah>

            <div class="mt-6 flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
                <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Hampir seluruh halaman daftar punya kotak cari yang menerima nomor usulan,
                    nomor surat tugas, atau nama pelaksana — berguna saat Anda datang membawa satu
                    nomor dari berkas kertas.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Administrasi ── --}}
    @if (array_key_exists('administrasi', $bagian))
    <div x-show="bagian === 'administrasi'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Mengelola Pengguna dan Master Data</h2>
            <p class="text-xs text-slate-400">Menu Administrasi Sistem dan Master Data</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Kelola akun pengguna">
                Menu <strong>Administrasi Sistem → Pengguna</strong> memuat seluruh pengguna beserta
                peran, unit kerja, dan atasannya. Kolom login terakhir memperlihatkan siapa yang belum
                pernah masuk sama sekali. Submenu <strong>Impor &amp; Ekspor</strong> memuat data
                pengguna massal lewat CSV.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Pantau siapa yang sedang aktif"
                               :terakhir="! $boleh['masterData'] && ! $boleh['jejakAudit']">
                Jumlah pengguna yang sedang masuk ditampilkan di halaman yang sama, dan daftarnya
                dapat disaring menjadi hanya yang aktif atau hanya yang belum pernah masuk.
            </x-panduan-langkah>

            @if ($boleh['masterData'])
                <x-panduan-langkah nomor="3" judul="Master Data menentukan perilaku sistem">
                    Kategori perjadin menentukan berkas apa yang ditagih — penanda
                    <strong>dalam kota</strong> pada tiap kategori itulah yang memilah. Komponen
                    biaya, lokasi tujuan, unit kerja, jenis kegiatan, dan akun pembiayaan juga
                    diatur di sini.
                </x-panduan-langkah>

                <x-panduan-langkah nomor="4" judul="Atur pengingat dokumen" :terakhir="! $boleh['jejakAudit']">
                    Tenggang hari, jeda antar pengingat, dan batas jumlahnya diatur pada
                    <strong>Administrasi Sistem → Pengaturan Sistem</strong>. Angka tenggang inilah yang
                    dipakai dashboard saat menghitung batas penyerahan laporan. Di halaman yang sama,
                    super administrator dapat membuka atau mengunci tanggal dikeluarkan SPD untuk
                    kasus tanggal mundur.
                </x-panduan-langkah>
            @endif

            @if ($boleh['jejakAudit'])
                <x-panduan-langkah :nomor="$boleh['masterData'] ? '5' : '3'" judul="Telusuri lewat Jejak Audit" :terakhir="true">
                    Setiap tindakan yang mengubah data tercatat beserta pelakunya dan waktunya.
                </x-panduan-langkah>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Melapor kendala ── --}}
    <div x-show="bagian === 'bantuan'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Bila Ada Kendala</h2>
            <p class="text-xs text-slate-400">Saluran bantuan langsung ke administrator</p>
        </div>

        <div class="p-6">
            <x-panduan-langkah nomor="1" judul="Tekan ikon obrolan di kanan bawah">
                Ikon melayang di sudut kanan bawah layar terbuka dari halaman mana pun. Menu
                <strong>Bantuan</strong> di sidebar membuka hal yang sama.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="2" judul="Tuliskan kendala Anda">
                Sebutkan halaman mana, nomor usulan atau surat tugasnya, dan apa yang Anda harapkan
                terjadi. Semakin jelas, semakin cepat tertangani.
            </x-panduan-langkah>

            <x-panduan-langkah nomor="3" judul="Pantau balasannya" :terakhir="true">
                Lencana angka pada menu Bantuan menghitung obrolan yang sudah dijawab tetapi belum
                Anda baca. Obrolan yang sudah tuntas dapat ditandai selesai.
            </x-panduan-langkah>
        </div>
    </div>

    {{-- ── Riwayat perubahan ── --}}
    <div x-show="bagian === 'perubahan'" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 text-sm">Riwayat Perubahan</h2>
            <p class="text-xs text-slate-400">Yang berubah dari versi ke versi — versi tayang: PANGI {{ $versi }}</p>
        </div>

        <div class="p-6 space-y-6">
            @foreach ($riwayat as $rilis)
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-2.5">
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $loop->first ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600' }}">
                            Versi {{ $rilis['versi'] }}
                        </span>
                        <span class="text-xs text-slate-400">{{ $rilis['tanggal'] }}</span>
                        @if ($loop->first)
                            <span class="text-[11px] font-semibold text-teal-600">terbaru</span>
                        @endif
                    </div>
                    <ul class="space-y-1.5">
                        @foreach ($rilis['butir'] as $butir)
                            <li class="flex items-start gap-2 text-xs text-slate-600 leading-relaxed">
                                <span class="w-1.5 h-1.5 rounded-full {{ $loop->parent->first ? 'bg-teal-500' : 'bg-slate-400' }} shrink-0 mt-1.5"></span>
                                {{ $butir }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <p class="text-xs text-slate-400 leading-relaxed">
                Riwayat yang sama tercantum pada bab terakhir buku panduan yang dapat diunduh di atas.
            </p>
        </div>
    </div>

    <p class="text-xs text-slate-400 text-center mt-8">
        Panduan ini menyesuaikan diri dengan peran Anda — pengguna lain melihat bagian yang berbeda.
    </p>

</div>

@endsection
