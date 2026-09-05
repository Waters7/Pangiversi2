@props([
    'nama',
    'aksi',
    'metode' => 'PUT',
    'tervalidasi' => false,
    'komponen' => null,
    'nominal' => null,
])

{{-- Validasi komponen biaya: satu klik menyatakan nominalnya sudah diperiksa
     dan ikut menentukan boleh tidaknya berkas berjalan ke pelaksana. Klik
     yang keliru mudah terjadi pada tabel padat, jadi ditanya ulang sekali. --}}
<div x-data="{ tanya: false }" class="inline-block">

    <button type="button" @click="tanya = true"
            class="w-8 h-8 inline-flex items-center justify-center rounded-lg transition
                   {{ $tervalidasi
                        ? 'bg-emerald-100 hover:bg-amber-100 text-emerald-700 hover:text-amber-700'
                        : 'bg-slate-100 hover:bg-emerald-500 text-slate-400 hover:text-white' }}"
            title="{{ $tervalidasi ? 'Cabut validasi nominal ini' : 'Validasi nominal ini' }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path d="M5 13l4 4L19 7"/>
        </svg>
    </button>

    <div x-show="tanya" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
         @keydown.escape.window="tanya = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-left" @click.outside="tanya = false">

            <h3 class="font-bold text-slate-800 text-sm mb-2">
                {{ $tervalidasi ? 'Cabut validasi komponen ini?' : 'Validasi komponen ini?' }}
            </h3>

            <p class="text-xs text-slate-600 leading-relaxed">
                Apakah Anda yakin nominalnya sudah benar diperiksa?
            </p>

            @if ($komponen)
                <p class="mt-2 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                    {{ $komponen }}
                    @if ($nominal !== null)
                        · <strong class="text-slate-700">Rp {{ number_format($nominal, 0, ',', '.') }}</strong>
                    @endif
                </p>
            @endif

            <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                {{ $tervalidasi
                    ? 'Berkas tertahan selama masih ada komponen yang menunggu pemeriksaan.'
                    : 'Berkas baru dapat dikirim ke pelaksana setelah seluruh komponennya diperiksa.' }}
            </p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" @click="tanya = false"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                    Periksa Lagi
                </button>

                <form method="POST" action="{{ $aksi }}">
                    @csrf
                    @method($metode)
                    <button type="submit"
                            class="px-4 py-2 text-white text-xs font-bold rounded-lg transition
                                   {{ $tervalidasi ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-500 hover:bg-emerald-600' }}">
                        {{ $tervalidasi ? 'Ya, Cabut' : 'Ya, Validasi' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
