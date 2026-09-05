@props(['daftar'])

{{-- Mencatat penggantian transport lokal: satu tanggal, satu bukti transfer.
     Dibuat sebagai kotak sendiri supaya tabelnya tetap ringkas dan bendahara
     melihat nominalnya sekali lagi sebelum menyimpan. --}}
<div x-data="{ bayar: false }" class="inline-block">

    <button type="button" @click="bayar = true"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Catat Bayar
    </button>

    <div x-show="bayar" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
         @keydown.escape.window="bayar = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-left" @click.outside="bayar = false">

            <h3 class="font-bold text-slate-800 text-sm mb-1">Catat penggantian transport lokal</h3>
            <p class="text-xs text-slate-500 mb-4">
                {{ $daftar->peserta?->nama ?? '—' }} ·
                <strong class="text-slate-700">Rp {{ number_format($daftar->total_riil, 0, ',', '.') }}</strong>
            </p>

            <form method="POST" action="{{ route('pembayaran.bayar-transport', $daftar) }}" enctype="multipart/form-data">
                @csrf

                <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                    Tanggal transfer <span class="text-red-500">*</span>
                </label>
                <input type="date" name="tanggal_bayar" required value="{{ old('tanggal_bayar', today()->toDateString()) }}"
                       class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('tanggal_bayar') ? 'border-red-500' : 'border-slate-200' }}">
                @error('tanggal_bayar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

                <label class="block text-xs font-semibold text-slate-600 mb-1.5 mt-4">
                    Bukti transfer <span class="text-red-500">*</span>
                </label>
                <input type="file" name="bukti_bayar" required accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full px-3.5 py-2 border rounded-xl text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-600 file:text-xs file:font-semibold {{ $errors->has('bukti_bayar') ? 'border-red-500' : 'border-slate-200' }}">
                <p class="text-[11px] text-slate-400 mt-1">PDF, JPG, atau PNG — maks. 2 MB.</p>
                @error('bukti_bayar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

                <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                    Setelah tercatat, nominal ini tidak lagi ikut dibayarkan pada pelunasan.
                </p>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="bayar = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
                        Simpan Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
