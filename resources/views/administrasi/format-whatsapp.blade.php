@extends('app')

@section('title', 'Format WhatsApp')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Format WhatsApp';
        $subjudulHalaman = 'Kalimat pesan WhatsApp yang disusun aplikasi, dapat diubah sesuai kebutuhan';
    @endphp
    @include('administrasi.partials.kepala')

    @if (session('success'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl"
         x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>
        <svg class="w-5 h-5 text-teal-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <p class="text-sm font-medium text-teal-800">{{ session('success') }}</p>
        <button @click="show = false" class="ml-auto text-teal-400 hover:text-teal-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    <div class="mb-5 flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
        <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
        <p class="text-xs text-slate-500 leading-relaxed">
            Tulis kalimatnya bebas, lalu sisipkan <strong>penanda</strong> berkurung kurawal — misalnya
            <code>{nama}</code> — yang diganti isinya saat pesan disusun. Tekan penandanya untuk menyisipkan.
            Teks di antara bintang, <code>*seperti ini*</code>, tampil tebal di WhatsApp. Pratinjau di
            bawah tiap kolom memakai data contoh.
        </p>
    </div>

    @foreach (['Penagihan berkas kepada pelaksana' => false, 'Peringatan pemantau server' => true] as $kelompok => $server)
        <h2 class="text-sm font-bold text-slate-700 mb-3 {{ $loop->first ? '' : 'mt-8' }}">{{ $kelompok }}</h2>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 items-start">
            @foreach (collect($pesan)->filter(fn ($p) => $p->pesanServer() === $server) as $jenis)
                @php $kolom = 'isi_'.$jenis->value; @endphp

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden"
                     x-data="formatWhatsapp(@js(old($kolom, $templat[$jenis->value])), @js($jenis->contoh()))"
                     data-format-whatsapp="{{ $jenis->value }}">
                    <form method="POST" action="{{ route('administrasi.format-whatsapp.simpan', $jenis->value) }}">
                        @csrf @method('PUT')

                        <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm">{{ $jenis->label() }}</h3>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $jenis->keterangan() }}</p>
                            </div>
                            <span class="shrink-0 text-[11px] font-bold px-2.5 py-1 rounded-full {{ $sudahDiubah[$jenis->value] ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $sudahDiubah[$jenis->value] ? 'Diubah' : 'Bawaan' }}
                            </span>
                        </div>

                        <div class="p-5 space-y-3">
                            <textarea name="{{ $kolom }}" x-ref="teks" x-model="teks" rows="9" required maxlength="2000"
                                      aria-label="Format {{ $jenis->label() }}"
                                      class="w-full px-3.5 py-3 rounded-xl text-sm font-mono leading-relaxed bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has($kolom) ? 'border-red-500' : 'border-slate-200' }}"></textarea>
                            @error($kolom)
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            <div>
                                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">Penanda</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($jenis->penanda() as $kunci => $arti)
                                        <button type="button" @click="sisipkan(@js('{'.$kunci.'}'))" title="{{ $arti }}"
                                                class="px-2 py-1 rounded-lg border text-[11px] font-mono transition
                                                       {{ in_array($kunci, $jenis->penandaWajib(), true) ? 'border-teal-300 bg-teal-50 text-teal-800 hover:bg-teal-100' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                                            {{ '{'.$kunci.'}' }}
                                        </button>
                                    @endforeach
                                </div>
                                <ul class="mt-2 space-y-0.5 text-[11px] text-slate-500">
                                    @foreach ($jenis->penanda() as $kunci => $arti)
                                        <li>
                                            <code class="text-slate-700">{{ '{'.$kunci.'}' }}</code> — {{ $arti }}
                                            @if (in_array($kunci, $jenis->penandaWajib(), true))
                                                <span class="font-semibold text-teal-700">(wajib)</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            {{-- Pratinjau dengan data contoh, bergaya gelembung WhatsApp. --}}
                            <div>
                                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">Pratinjau</p>
                                <div class="rounded-xl bg-[#e5ddd5] p-3">
                                    <div class="max-w-[95%] rounded-lg rounded-tl-none bg-white shadow-sm px-3 py-2 text-[13px] text-slate-800 leading-relaxed break-words"
                                         x-html="pratinjau"></div>
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/60 flex items-center justify-between gap-2">
                            @if ($sudahDiubah[$jenis->value])
                                <button type="button" @click="$dispatch('buka-bawaan-wa-{{ $jenis->value }}')"
                                        class="text-xs font-bold text-slate-500 hover:text-slate-700 underline underline-offset-2">
                                    Kembalikan bawaan
                                </button>
                            @else
                                <span class="text-xs text-slate-400">Mengikuti bawaan aplikasi</span>
                            @endif

                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition">
                                Simpan
                            </button>
                        </div>
                    </form>

                    @if ($sudahDiubah[$jenis->value])
                        <x-modal-konfirmasi
                            nama="bawaan-wa-{{ $jenis->value }}"
                            judul="Kembalikan format &quot;{{ $jenis->label() }}&quot; ke bawaan?"
                            :aksi="route('administrasi.format-whatsapp.bawaan', $jenis->value)"
                            metode="DELETE"
                            tombol="Ya, Kembalikan"
                            warna="amber"
                            ikon="peringatan">
                            <p>Kalimat pesan ini kembali seperti bawaan aplikasi.</p>
                        </x-modal-konfirmasi>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    // Kolom format satu pesan WhatsApp beserta pratinjaunya.
    Alpine.data('formatWhatsapp', (awal, contoh) => ({
        teks: awal,
        contoh,

        sisipkan(penanda) {
            const kolom = this.$refs.teks;
            const awalPilih = kolom.selectionStart ?? this.teks.length;
            const akhirPilih = kolom.selectionEnd ?? this.teks.length;
            this.teks = this.teks.slice(0, awalPilih) + penanda + this.teks.slice(akhirPilih);
            this.$nextTick(() => {
                kolom.focus();
                kolom.selectionStart = kolom.selectionEnd = awalPilih + penanda.length;
            });
        },

        // Penanda diganti data contoh; *tebal* dan _miring_ mengikuti WhatsApp.
        get pratinjau() {
            const aman = (t) => String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            let hasil = aman(this.teks).replace(/\{([a-z_]+)\}/g, (asli, kunci) =>
                kunci in this.contoh ? aman(this.contoh[kunci]) : '<span style="color:#dc2626">' + asli + '</span>');
            hasil = hasil.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>').replace(/(^|\s)_([^_\n]+)_/g, '$1<em>$2</em>');

            return hasil.replace(/\n/g, '<br>');
        },
    }));
});
</script>
@endpush
