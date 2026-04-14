@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('persetujuan') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Rincian Usulan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">Detail lengkap usulan perjalanan dinas</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- LEFT --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- DATA DASAR --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Data Dasar Perjalanan</h3>
                        <p class="text-xs text-slate-400">Informasi utama perjalanan dinas</p>
                    </div>
                </div>

                <div class="p-6 space-y-4 text-sm">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-slate-500">Jenis Kegiatan</p>
                            <p class="font-semibold">{{ $usulan->kegiatan->nama }}</p>
                        </div>

                        <div>
                            <p class="text-slate-500">Dasar Penugasan</p>
                            <p class="font-semibold">{{ $usulan->no_tugas }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-slate-500">Lokasi Tujuan</p>
                            <p class="font-semibold">{{ $usulan->lokasi }}</p>
                        </div>

                        <div>
                            <p class="text-slate-500">Instansi Tujuan</p>
                            <p class="font-semibold">{{ $usulan->instansi }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-slate-500">Tanggal Mulai</p>
                            <p class="font-semibold">{{ $usulan->tanggal_mulai_formatted }}</p>
                        </div>

                        <div>
                            <p class="text-slate-500">Tanggal Selesai</p>
                            <p class="font-semibold">{{ $usulan->tanggal_selesai_formatted }} </p>
                        </div>
                    </div>

                    <div>
                        <p class="text-slate-500">Uraian Tujuan</p>
                        <p class="font-semibold">
                            {{ $usulan->uraian }}
                        </p>
                    </div>

                </div>
            </div>
            {{-- LAMPIRAN --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Lampiran Dokumen</h3>
                        <p class="text-xs text-slate-400">Dokumen pendukung perjalanan dinas</p>
                    </div>
                </div>

                <div class="p-6 space-y-3 text-sm">

                    @php $dokumen = $usulan->dokumen->last(); @endphp

                    <div class="flex justify-between">
                        <span>Surat Tugas</span>
                        @if ($dokumen && $dokumen->surat_tugas)
                            <a href="{{ route('persetujuan.dokumen', $dokumen->surat_tugas) }}" class="text-blue-600 font-semibold">Lihat</a>
                        @else
                            <span class="text-slate-400 italic">Tidak ada</span>
                        @endif
                    </div>
                    
                    <div class="flex justify-between">
                        <span>Rundown Kegiatan</span>
                        @if ($dokumen && $dokumen->rundown)
                            <a href="{{ route('persetujuan.dokumen', $dokumen->rundown) }}" class="text-blue-600 font-semibold">Lihat</a>
                        @else
                            <span class="text-slate-400 italic">Tidak ada</span>
                        @endif
                    </div>

                    <div class="flex justify-between">
                        <span>Dokumen Pendukung</span>
                        @if ($dokumen && $dokumen->dokumen_pendukung)
                            <a href="{{ route('persetujuan.dokumen', $dokumen->dokumen_pendukung) }}" class="text-blue-600 font-semibold">Lihat</a>
                        @else
                            <span class="text-slate-400 italic">Tidak ada</span>
                        @endif
                    </div>

                </div>
            </div>

        </div>

        {{-- RIGHT SIDEBAR --}}
        <div class="space-y-5">

            {{-- TIMELINE PERSETUJUAN --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                </div>
                <div>
                <h3 class="font-bold text-slate-800 text-sm">Timeline Persetujuan</h3>
                </div>
            </div>

            <div class="space-y-4 text-sm">

                {{-- STEP 1: Submit Usulan --}}
                <div class="flex gap-3">
                <div class="flex flex-col items-center">
                    <div class="w-6 h-6 rounded-full bg-teal-500 flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    </div>
                    <div class="w-0.5 h-8 bg-slate-200 my-1"></div>
                </div>
                <div class="pb-4">
                    <p class="font-semibold text-slate-800">Submit Usulan</p>
                    <p class="text-xs text-slate-500">10 Jan 2026</p>
                </div>
                </div>

                {{-- STEP 2: PPK --}}
                <div class="flex gap-3">

                @if ($usulan->status == 'disetujui' || $usulan->status == 'selesai')
                    <div class="flex flex-col items-center">
                        <div class="w-6 h-6 rounded-full bg-teal-500 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        </div>
                        <div class="w-0.5 h-8 bg-slate-200 my-1"></div>
                    </div>
                @else
                    <div class="flex flex-col items-center">
                        <div class="w-6 h-6 rounded-full bg-slate-300 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        </div>
                        <div class="w-0.5 h-8 bg-slate-200 my-1"></div>
                    </div>
                @endif

                <div class="pb-4">
                    <p class="font-semibold text-slate-800">PPK</p>
                    <p class="text-xs text-slate-500">
                    @if ($usulan->status == 'disetujui' || $usulan->status == 'selesai')
                        Disetujui pada {{ $usulan->updated_at->format('d M Y') }}
                    @elseif ($usulan->status == 'ditolak')
                        Ditolak pada {{ $usulan->updated_at->format('d M Y') }}
                    </p>
                    @else
                        Menunggu keputusan...
                    @endif
                </div>
                </div>

                {{-- STEP 3: Bendahara --}}
                <div class="flex gap-3">
                <div class="flex flex-col items-center">
                    <div class="w-6 h-6 rounded-full bg-slate-300 flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    </div>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Bendahara</p>
                    <p class="text-xs text-slate-500">Menunggu...</p>
                </div>
                </div>

            </div>
            </div>

            {{-- STATUS & AKSI --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-4">

            <div>
                <p class="text-xs text-slate-500">Putuskan</p>
            </div>

            <div class="pt-4 border-t border-slate-100 flex flex-col gap-2">

                {{-- Unduh --}}
                <a href="#"
                class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-sm font-semibold
                    bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Unduh Dokumen
                </a>
                {{-- SETUJU --}}
                @if ($usulan->status == 'diajukan' || $usulan->status == 'menunggu')

                    {{-- setuju --}}
                    <form action="{{ route('persetujuan.approve', $usulan->no_usulan) }}" method="post">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold
                                bg-teal-50 text-teal-700 border border-teal-100 hover:bg-teal-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Setujui & Teruskan
                        </button>
                    </form>

                    {{-- tolak --}}
                    <button id="btnTolak" type="button" class="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold
                            bg-red-50 text-red-700 border border-red-100 hover:bg-red-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Tolak / Revisi
                    </button>
                @elseif ($usulan->status == 'selesai')
                    <div class="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold bg-green-50 text-green-700 border border-green-100 cursor-default">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Selesai
                    </div>
                @else
                    <button type="button" id="btnBatalkan"
                            data-label="{{ $usulan->status == 'ditolak' ? 'Tolak' : 'Persetujuan' }}"
                            class="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold
                                bg-orange-50 text-orange-700 border border-orange-100 hover:bg-orange-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Batalkan {{ $usulan->status == 'ditolak' ? 'Tolak' : 'Persetujuan' }}
                    </button>
                @endif

            </div>
            </div>

        </div>

    </div>

</div>


{{-- Modal Konfirmasi Batalkan --}}
<div id="modalBatalkan" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 text-sm">Konfirmasi Pembatalan</h3>
                <p class="text-xs text-slate-400">Apakah Anda yakin ingin membatalkan tindakan ini?</p>
            </div>
        </div>

        <div class="p-6 flex flex-col items-center text-center gap-3">
            <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <p class="text-sm text-slate-500">
                Tindakan <span class="font-semibold text-slate-700" id="labelStatusBatalkan"></span> pada usulan ini akan dibatalkan dan status akan dikembalikan ke semula.
            </p>
        </div>

        <div class="px-6 pb-6 flex gap-2">
            <button type="button"
                id="btnBatalkanTidak"
                class="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-100 transition-colors">
                Tidak
            </button>
            <form id="formBatalkan" action="{{ route('persetujuan.revoke', $usulan->no_usulan) }}" method="post" class="flex-1">
                @csrf
                @method('PUT')
                <button type="submit"
                    class="w-full py-2.5 rounded-xl text-sm font-semibold bg-orange-50 text-orange-700 border border-orange-100 hover:bg-orange-100 transition-colors">
                    Ya, Batalkan
                </button>
            </form>
        </div>

    </div>
</div>


{{-- Modal Tolak / Revisi --}}
<div id="modalTolak" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 text-sm">Tolak / Revisi Usulan</h3>
                <p class="text-xs text-slate-400">Berikan catatan untuk pemohon</p>
            </div>
        </div>

        <form action="{{ route('persetujuan.reject', $usulan->no_usulan) }}" method="post">
            @csrf
            @method('PUT')

            <div class="p-6 space-y-4">
                <div>
                    <label for="catatan" class="block text-xs font-semibold text-slate-600 mb-1.5">
                        Catatan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="catatan"
                        name="catatan"
                        rows="4"
                        required
                        placeholder="Tuliskan alasan penolakan atau revisi yang diperlukan..."
                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 resize-none transition"
                    >{{ old('catatan') }}</textarea>
                </div>
            </div>

            <div class="px-6 pb-6 flex gap-2">
                <button type="button"
                    onclick="document.getElementById('modalTolak').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-100 transition-colors">
                    Batal
                </button>
                <button type="submit"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-red-50 text-red-700 border border-red-100 hover:bg-red-100 transition-colors">
                    Kirim Penolakan
                </button>
            </div>
        </form>

    </div>
</div>

{{-- Flash Message Modal --}}
@if(session('success') || session('error'))
<div id="flashModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">

        <div class="p-6 flex flex-col items-center text-center gap-3">

            @if(session('success'))
                <div class="w-12 h-12 rounded-full bg-teal-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="font-bold text-slate-800 text-base">Berhasil</h3>
                <p class="text-sm text-slate-500">{{ session('success') }}</p>
            @else
                <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
                <h3 class="font-bold text-slate-800 text-base">Gagal</h3>
                <p class="text-sm text-slate-500">{{ session('error') }}</p>
            @endif

        </div>

        <div class="px-6 pb-6">
            <button onclick="document.getElementById('flashModal').remove()"
                class="w-full py-2.5 rounded-xl text-sm font-semibold
                    {{ session('success') ? 'bg-teal-50 text-teal-700 border border-teal-100 hover:bg-teal-100' : 'bg-red-50 text-red-700 border border-red-100 hover:bg-red-100' }}
                    transition-colors">
                Tutup
            </button>
        </div>

    </div>
</div>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Modal Tolak
        const btnTolak = document.getElementById('btnTolak');
        const modalTolak = document.getElementById('modalTolak');

        if (btnTolak) {
            btnTolak.addEventListener('click', function () {
                modalTolak.classList.remove('hidden');
            });
        }

        modalTolak.addEventListener('click', function (e) {
            if (e.target === modalTolak) {
                modalTolak.classList.add('hidden');
            }
        });

        // Modal Batalkan
        const btnBatalkan = document.getElementById('btnBatalkan');
        const modalBatalkan = document.getElementById('modalBatalkan');
        const btnBatalkanTidak = document.getElementById('btnBatalkanTidak');
        const labelStatusBatalkan = document.getElementById('labelStatusBatalkan');

        if (btnBatalkan) {
            btnBatalkan.addEventListener('click', function () {
                labelStatusBatalkan.textContent = btnBatalkan.dataset.label;
                modalBatalkan.classList.remove('hidden');
            });
        }

        if (btnBatalkanTidak) {
            btnBatalkanTidak.addEventListener('click', function () {
                modalBatalkan.classList.add('hidden');
            });
        }

        modalBatalkan.addEventListener('click', function (e) {
            if (e.target === modalBatalkan) {
                modalBatalkan.classList.add('hidden');
            }
        });
    });
</script>


@endsection