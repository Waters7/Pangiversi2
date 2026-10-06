{{-- Contoh pengisian Registrasi Naskah Keluar di SRIKANDI untuk SPD dari PANGI.
     Dipakai halaman detail SPD dan menu Panduan Penggunaan supaya isinya sama. --}}
<dl class="grid grid-cols-1 sm:grid-cols-[11rem_minmax(0,1fr)] gap-x-4 gap-y-0.5 sm:gap-y-1.5 text-xs leading-relaxed">
    @foreach ([
        'Tipe Form' => 'Naskah Keluar',
        'Dikirimkan melalui' => 'Pejabat Pembuat Komitmen Politeknik Kesehatan Kementerian Kesehatan Manado',
        'Jenis / Sifat Naskah' => 'Naskah Dinas · Biasa',
        'Klasifikasi' => 'KU.02.04 – Belanja Modal',
        'Nomor Naskah' => 'Tekan Ambil Nomor, lalu catat nomornya — nomor inilah yang nanti diisi pada usulan perjadin.',
        'Hal dan Isi Ringkas' => 'Surat Perjalanan Dinas a.n. nama pelaksana, beserta maksud, tujuan, dan tanggal perjalanan dari SPD.',
        'File naskah' => 'PDF hasil Unduh PDF di PANGI (maks. 5 MB), tanpa diubah.',
        'Tujuan Utama' => 'Pelaksana perjalanan dinas.',
        'Verifikator' => 'Kepala Sub Bagian Administrasi Umum, Wakil Direktur II, dan Sekretaris.',
        'Penandatangan' => 'PPK lebih dulu, lalu Direktur — urutannya menentukan kotak tanda tangan lembar pertama dan kedua.',
        'Tanda tangan' => 'Elektronik · Visual TTE QR Code · Ukuran QR 3x3, lalu tekan Simpan.',
    ] as $isian => $nilai)
        <dt class="font-semibold text-slate-700">{{ $isian }}</dt>
        <dd class="text-slate-600 mb-2 sm:mb-0">{{ $nilai }}</dd>
    @endforeach
</dl>
