@extends('app')

@section('title', 'Detail Usulan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('usulan.list') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Detail Usulan</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }}</p>
        </div>
        <div class="ml-auto">
            <span class="inline-block text-xs font-bold px-3 py-1.5 rounded-full {{ $usulan->status_badge }}">
                {{ $usulan->status_text }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Kiri: Informasi Utama --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Data Perjalanan --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Data Perjalanan Dinas</h3>
                        <p class="text-xs text-slate-400">Informasi utama usulan</p>
                    </div>
                </div>

                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">No. Usulan</dt>
                            <dd class="text-sm font-bold text-slate-800">{{ $usulan->no_usulan }}</dd>
                        </div>

                        {{-- Jenis kegiatan hanya ditampilkan untuk usulan lama; penggolongannya
                             kini memakai kategori perjalanan dinas. --}}
                        @if ($usulan->kegiatan)
                            <div>
                                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Jenis Kegiatan</dt>
                                <dd class="text-sm text-slate-800">{{ $usulan->kegiatan->nama }}</dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Kategori Perjadin</dt>
                            <dd class="text-sm text-slate-800">
                                @if ($usulan->kategoriPerjadin)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $usulan->kategoriPerjadin->badge }}">
                                        {{ $usulan->kategoriPerjadin->nama }}
                                    </span>
                                    <span class="block text-xs text-slate-400 mt-1">{{ $usulan->kategoriPerjadin->grup }}</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Nomor Surat Tugas</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->no_tugas ?: '—' }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Nomor SPD Bertanda Tangan</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->no_spd ?: '—' }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Lokasi / Kota Tujuan</dt>
                            <dd class="text-sm text-slate-800 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                </svg>
                                {{ $usulan->lokasi }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Instansi Tujuan</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->instansi }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Tanggal Mulai</dt>
                            <dd class="text-sm text-slate-800">{{ date('d F Y', strtotime($usulan->tanggal_mulai)) }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Tanggal Selesai</dt>
                            <dd class="text-sm text-slate-800">{{ date('d F Y', strtotime($usulan->tanggal_selesai)) }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Durasi</dt>
                            <dd class="text-sm font-bold text-slate-800">{{ $usulan->durasi }} hari</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Periode</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->periode }}</dd>
                        </div>

                        @if($usulan->uraian)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Uraian Tujuan</dt>
                            <dd class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $usulan->uraian }}</dd>
                        </div>
                        @endif

                    </dl>
                </div>
            </div>

            {{-- Catatan approver saat usulan ditolak atau diminta revisi --}}
            @if(in_array($usulan->status, ['ditolak', 'perlu_revisi']) && $usulan->catatan)
            @php $revisi = $usulan->status === 'perlu_revisi'; @endphp
            <div class="{{ $revisi ? 'bg-amber-50 border-amber-100' : 'bg-red-50 border-red-100' }} rounded-2xl border p-5 flex gap-3">
                <svg class="w-5 h-5 {{ $revisi ? 'text-amber-500' : 'text-red-500' }} shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div>
                    <p class="text-sm font-bold {{ $revisi ? 'text-amber-700' : 'text-red-700' }} mb-1">
                        {{ $revisi ? 'Permintaan Revisi' : 'Catatan Penolakan' }}
                    </p>
                    <p class="text-sm {{ $revisi ? 'text-amber-700' : 'text-red-600' }} leading-relaxed">{{ $usulan->catatan }}</p>
                    @if ($revisi)
                        <a href="{{ route('usulan.edit', $usulan) }}" class="inline-block mt-2 text-xs font-bold text-amber-700 hover:underline">Perbaiki usulan →</a>
                    @endif
                </div>
            </div>
            @endif
            {{-- Konfirmasi kesediaan atas usulan yang dibuatkan orang lain --}}
            @if ($usulan->dibuatkanOrangLain() && auth()->id() === $usulan->id_user)
            <div class="bg-white rounded-2xl border-2 {{ $usulan->menungguKonfirmasi() ? 'border-amber-200' : 'border-slate-100' }} shadow-sm p-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex gap-3">
                        <div class="w-9 h-9 rounded-xl shrink-0 flex items-center justify-center {{ $usulan->konfirmasi_badge }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800">
                                {{ $usulan->menungguKonfirmasi()
                                    ? 'Usulan ini dibuatkan untuk Anda'
                                    : 'Status kesediaan Anda: '.$usulan->konfirmasi_label }}
                            </p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Dibuatkan oleh {{ $usulan->pembuat?->nama }}.
                                Nomor pengajuan ini milik Anda sendiri, begitu pula pertanggungjawabannya.
                            </p>
                            @if ($usulan->alasan_batal)
                                <p class="text-xs text-red-600 mt-1">Alasan pembatalan: {{ $usulan->alasan_batal }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($usulan->konfirmasiMasihTerbuka())
                    <div class="flex gap-2 shrink-0">
                        @unless ($usulan->sudahDikonfirmasi())
                            <form method="POST" action="{{ route('usulan.konfirmasi', $usulan) }}">
                                @csrf
                                @method('PUT')
                                <button type="submit"
                                        class="px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition whitespace-nowrap">
                                    Bersedia Berangkat
                                </button>
                            </form>
                        @endunless

                        @unless ($usulan->konfirmasi === \App\Models\Usulan::KONFIRMASI_DIBATALKAN)
                            <form method="POST" action="{{ route('usulan.batal-konfirmasi', $usulan) }}"
                                  x-data
                                  @submit.prevent="$refs.alasan.value = prompt('Alasan mengundurkan diri (boleh dikosongkan):') ?? ''; $el.submit()">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="alasan_batal" x-ref="alasan">
                                <button type="submit"
                                        class="px-4 py-2.5 bg-white border border-red-200 hover:bg-red-50 text-red-600 text-sm font-semibold rounded-xl transition whitespace-nowrap">
                                    Mengundurkan Diri
                                </button>
                            </form>
                        @endunless
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Usulan lain yang dibuat dalam satu rombongan input --}}
            @if ($usulan->kode_rombongan && $usulan->serombongan->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Berangkat Bersama</h3>
                        <p class="text-xs text-slate-400">
                            Diajukan dalam satu rombongan, namun tiap orang punya nomor dan pertanggungjawaban sendiri
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($usulan->serombongan as $rekan)
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                            <span class="font-semibold text-slate-700">{{ $rekan->user?->nama ?? '—' }}</span>
                            <span class="font-mono text-slate-400">{{ $rekan->no_usulan }}</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $rekan->konfirmasi_badge }}">{{ $rekan->konfirmasi_label }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Peserta Perjalanan --}}
            @php
                // Kunci status berlaku untuk semua peran, termasuk administrator.
                $bolehKelolaPeserta = $usulan->bolehMengubahPeserta()
                    && (auth()->user()->isAdmin() || auth()->id() === $usulan->id_user);
            @endphp
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden"
                 x-data="{ showTambah: false, sumber: 'pegawai' }">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Peserta Perjalanan</h3>
                            <p class="text-xs text-slate-400">{{ $usulan->peserta->count() }} orang terdaftar</p>
                        </div>
                    </div>
                    @if ($bolehKelolaPeserta)
                        <button @click="showTambah = !showTambah"
                                class="px-3 py-1.5 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition whitespace-nowrap">
                            + Tambah Peserta
                        </button>
                    @elseif ($usulan->alasanPesertaTerkunci())
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 text-slate-500 text-xs font-bold whitespace-nowrap"
                              title="{{ $usulan->alasanPesertaTerkunci() }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                            Terkunci
                        </span>
                    @endif
                </div>

                @if (! $bolehKelolaPeserta && $usulan->alasanPesertaTerkunci())
                    <p class="px-6 py-3 bg-slate-50 border-b border-slate-100 text-xs text-slate-500 leading-relaxed">
                        {{ $usulan->alasanPesertaTerkunci() }}
                    </p>
                @endif

                @if ($bolehKelolaPeserta)
                    <div x-show="showTambah" x-transition x-cloak class="px-6 py-4 bg-slate-50 border-b border-slate-100">
                        <form method="POST" action="{{ route('usulan.peserta.store', $usulan) }}">
                            @csrf

                            <div class="flex gap-4 mb-3 text-xs font-semibold">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="pegawai" x-model="sumber" class="text-teal-500 focus:ring-teal-400">
                                    Pilih pegawai terdaftar
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="manual" x-model="sumber" class="text-teal-500 focus:ring-teal-400">
                                    Isi manual
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <template x-if="sumber === 'pegawai'">
                                    <div class="sm:col-span-3">
                                        <select name="id_user"
                                                class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                            <option value="">— Pilih pegawai —</option>
                                            @foreach ($calonPeserta as $pegawai)
                                                <option value="{{ $pegawai->id }}">{{ $pegawai->nama }}{{ $pegawai->jabatan ? ' — '.$pegawai->jabatan : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                                <template x-if="sumber === 'manual'">
                                    <div class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <input type="text" name="nama" placeholder="Nama peserta" value="{{ old('nama') }}"
                                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                        <input type="text" name="nip" placeholder="NIP (opsional)" value="{{ old('nip') }}"
                                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                        <input type="text" name="jabatan" placeholder="Jabatan (opsional)" value="{{ old('jabatan') }}"
                                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                    </div>
                                </template>

                                <select name="peran" required
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                    @foreach (\App\Models\PesertaUsulan::peranOptions() as $nilai => $label)
                                        <option value="{{ $nilai }}" @selected($nilai === 'anggota')>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @error('id_user') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                            @error('nama') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror

                            <div class="flex justify-end gap-2 mt-3">
                                <button type="button" @click="showTambah = false"
                                        class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-lg transition">Batal</button>
                                <button type="submit"
                                        class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">Simpan Peserta</button>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3 w-14">No</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Nama</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">NIP</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Jabatan</th>
                                <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3 w-32">Peran</th>
                                @if ($bolehKelolaPeserta)
                                    <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3 w-20">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse ($usulan->peserta as $peserta)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-4 py-3 text-center text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $peserta->nama }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $peserta->nip ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $peserta->jabatan ?? '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $peserta->peran === 'ketua' ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $peserta->peran_label }}
                                        </span>
                                    </td>
                                    @if ($bolehKelolaPeserta)
                                        <td class="px-4 py-3 text-center">
                                            <form method="POST" action="{{ route('usulan.peserta.destroy', [$usulan, $peserta]) }}"
                                                  x-data
                                                  @submit.prevent="if (confirm('Hapus peserta {{ addslashes($peserta->nama) }}?')) $el.submit()">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-red-100 text-slate-600 hover:text-red-600 inline-flex items-center justify-center transition" title="Hapus">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $bolehKelolaPeserta ? 6 : 5 }}" class="px-4 py-8 text-center text-sm text-slate-400">
                                        Belum ada peserta terdaftar
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Lampiran Dokumen --}}
            @php $dokumen = $usulan->dokumen->last(); @endphp
            @if($dokumen)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Lampiran Dokumen</h3>
                        <p class="text-xs text-slate-400">Dokumen yang dilampirkan saat pengajuan</p>
                    </div>
                </div>
                <div class="p-6 space-y-1">
                    @php
                        $files = [
                            'Surat Tugas'       => $dokumen->surat_tugas,
                            'SPD Bertanda Tangan' => $dokumen->spd_ditandatangani,
                            'Rundown Kegiatan'  => $dokumen->rundown,
                            'Dokumen Pendukung' => $dokumen->dokumen_pendukung,
                        ];
                    @endphp
                    @foreach($files as $label => $path)
                        <div class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">{{ $label }}</p>
                                    @if($path)
                                        <p class="text-xs text-slate-400">{{ basename($path) }}</p>
                                    @endif
                                </div>
                            </div>
                            @if($path)
                                <a href="{{ route('berkas.lihat', $path) }}" target="_blank"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 text-xs font-semibold rounded-lg transition border border-teal-100">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Lihat
                                </a>
                            @else
                                <span class="text-xs text-slate-400 italic">Tidak ada</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Estimasi Biaya & Status Pembayaran (hanya untuk usulan disetujui) --}}
            @if($usulan->status === 'disetujui' && $usulan->keuangan)
            @php
                $keuangan = $usulan->keuangan;
                $rincian = $keuangan->rincianBiaya ?? collect();
                $statusPayment = match($keuangan->status) {
                    'lunas'           => ['label' => 'Lunas',           'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                    'bayar sebagian'  => ['label' => 'Bayar Sebagian',  'class' => 'bg-blue-50 text-blue-700 border-blue-200',        'dot' => 'bg-blue-500'],
                    default           => ['label' => 'Belum Dibayar',   'class' => 'bg-amber-50 text-amber-700 border-amber-200',     'dot' => 'bg-amber-500'],
                };
            @endphp
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Estimasi Biaya & Pembayaran</h3>
                            <p class="text-xs text-slate-400">Rincian biaya perjalanan dinas Anda</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 border text-xs font-bold rounded-full {{ $statusPayment['class'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $statusPayment['dot'] }}"></span>
                        {{ $statusPayment['label'] }}
                    </span>
                </div>

                @if($rincian->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3 w-10">No</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Komponen Biaya</th>
                                <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-16">Vol.</th>
                                <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-16">Satuan</th>
                                <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Harga Satuan</th>
                                <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($rincian as $i => $item)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-6 py-3 text-slate-500 font-medium">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $item->komponen }}</td>
                                    <td class="px-4 py-3 text-center text-slate-600">{{ $item->volume }}</td>
                                    <td class="px-4 py-3 text-center text-slate-600">{{ $item->satuan }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-800">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                            <tr>
                                <td colspan="5" class="px-6 py-3 text-right text-sm font-bold text-slate-600">Total Estimasi</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-slate-800">Rp {{ number_format($keuangan->total, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-6 py-2 text-right text-sm font-semibold text-teal-700">Uang Muka</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-teal-700">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-6 py-2 text-right text-sm font-semibold text-slate-500">Sisa Bayar</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-slate-600">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="p-6 text-center">
                    <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm text-slate-400">Rincian biaya belum ditentukan oleh admin.</p>
                </div>
                @endif

                {{-- Ringkasan Pembayaran --}}
                @if($keuangan->status !== 'belum bayar')
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @if($keuangan->tanggal_transfer)
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Uang Muka Ditransfer</p>
                            <p class="text-sm font-semibold text-slate-700">{{ $keuangan->tanggal_transfer->format('d/m/Y') }}</p>
                            <p class="text-xs font-bold text-teal-600">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</p>
                        </div>
                        @endif
                        @if($keuangan->status === 'lunas' && $keuangan->tanggal_pelunasan)
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Pelunasan Sisa</p>
                            <p class="text-sm font-semibold text-slate-700">{{ $keuangan->tanggal_pelunasan->format('d/m/Y') }}</p>
                            <p class="text-xs font-bold text-emerald-600">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</p>
                        </div>
                        @endif
                        @if($keuangan->dokumenKeuangan?->transfer_uang_muka)
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Bukti Uang Muka</p>
                            <a href="{{ route('berkas.lihat', $keuangan->dokumenKeuangan->transfer_uang_muka) }}" target="_blank"
                               class="text-xs text-teal-600 font-semibold hover:underline flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Lihat File
                            </a>
                        </div>
                        @endif
                        @if($keuangan->dokumenKeuangan?->transfer_sisa)
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Bukti Pelunasan</p>
                            <a href="{{ route('berkas.lihat', $keuangan->dokumenKeuangan->transfer_sisa) }}" target="_blank"
                               class="text-xs text-emerald-600 font-semibold hover:underline flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Lihat File
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif

            {{-- Pelacakan tonggak berkas menggantikan jejak audit: yang
                 dicari orang di sini bukan tiap tindakan siapa pun,
                 melainkan sudah sampai mana berkasnya dan kapan tiap
                 tahapnya terlewati. Jejak audit lengkap tetap ada pada
                 menu Jejak Audit. --}}
            <x-lacak-usulan :usulan="$usulan" />

        </div>

        {{-- Kanan: Sidebar Info --}}
        <div class="xl:col-span-1 space-y-4">

            {{-- Pengusul --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Informasi Pengusul</h3>
                <div class="flex items-center gap-3 mb-4">
                    <x-avatar :nama="$usulan->user?->nama" :foto="$usulan->user?->url_foto" />
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $usulan->user?->nama ?? '—' }}</p>
                        <p class="text-xs text-slate-400">{{ $usulan->user?->email ?? '—' }}</p>
                    </div>
                </div>
                <dl class="space-y-3 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Dibuat</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->created_at->format('d M Y, H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Diperbarui</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->updated_at->format('d M Y, H:i') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Aksi --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>

                @if(in_array($usulan->status, ['draft']))
                    @if($usulan->menungguKonfirmasi())
                        <p class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 leading-relaxed">
                            Konfirmasi kesediaan Anda lebih dulu pada daftar usulan, baru usulan ini dapat dikirim ke PPK.
                        </p>
                    @elseif($usulan->id_user === auth()->id())
                        @unless ($usulan->punyaSpdBertandaTangan())
                            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 leading-relaxed">
                                Lengkapi nomor dan berkas SPD yang sudah ditandatangani lewat <strong>Edit Usulan</strong>
                                lebih dulu; pengajuan belum dapat dikirim tanpa keduanya.
                            </p>
                        @endunless
                        <form method="POST" action="{{ route('usulan.ajukan', $usulan) }}"
                              onsubmit="return confirm('Kirim pengajuan perjadin ini? Pengajuan langsung berlaku setelah dikirim.')">
                            @csrf
                            @method('PUT')
                            <button type="submit"
                                    class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                </svg>
                                Kirim Pengajuan Perjadin
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('usulan.edit', $usulan) }}"
                       class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Usulan
                    </a>
                @endif

                <a href="{{ route('usulan.list') }}"
                   class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                    Kembali ke List
                </a>

                @if($usulan->bolehDisunting())
                    <form method="POST" action="{{ route('usulan.destroy', $usulan) }}"
                          x-data
                          @submit.prevent="if(confirm('Hapus usulan {{ $usulan->no_usulan }}?\nTindakan ini tidak dapat dibatalkan.')) $el.submit()">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-red-200 bg-white text-red-600 text-sm font-semibold rounded-xl hover:bg-red-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                            </svg>
                            Hapus Usulan
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>

</div>

@endsection
