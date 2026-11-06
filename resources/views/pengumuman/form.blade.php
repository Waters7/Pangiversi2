@extends('app')

@section('title', 'Pengumuman')

@section('content')

@php $baru = ! $pengumuman->exists; @endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('pengumuman.index') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-bold text-slate-800">{{ $baru ? 'Buat Pengumuman' : 'Ubah Pengumuman' }}</h1>
    </div>

    <form method="POST" action="{{ $baru ? route('pengumuman.store') : route('pengumuman.update', $pengumuman) }}"
          class="max-w-2xl bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-5">
        @csrf
        @unless ($baru)
            @method('PUT')
        @endunless

        <div>
            <label for="judul" class="block text-sm font-semibold text-slate-700 mb-1.5">Judul <span class="text-red-500">*</span></label>
            <input type="text" name="judul" id="judul" maxlength="150" required value="{{ old('judul', $pengumuman->judul) }}"
                   class="w-full px-4 py-2.5 border {{ $errors->has('judul') ? 'border-red-500' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            @error('judul') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="isi" class="block text-sm font-semibold text-slate-700 mb-1.5">Isi <span class="text-red-500">*</span></label>
            <textarea name="isi" id="isi" rows="6" maxlength="2000" required
                      class="w-full px-4 py-2.5 border {{ $errors->has('isi') ? 'border-red-500' : 'border-slate-200' }} rounded-xl text-sm leading-relaxed focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ old('isi', $pengumuman->isi) }}</textarea>
            @error('isi') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="mulai_tayang" class="block text-sm font-semibold text-slate-700 mb-1.5">Mulai tayang <span class="text-red-500">*</span></label>
                <input type="date" name="mulai_tayang" id="mulai_tayang" required value="{{ old('mulai_tayang', $pengumuman->mulai_tayang?->toDateString()) }}"
                       class="w-full px-4 py-2.5 border {{ $errors->has('mulai_tayang') ? 'border-red-500' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                @error('mulai_tayang') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="selesai_tayang" class="block text-sm font-semibold text-slate-700 mb-1.5">Selesai tayang</label>
                <input type="date" name="selesai_tayang" id="selesai_tayang" value="{{ old('selesai_tayang', $pengumuman->selesai_tayang?->toDateString()) }}"
                       class="w-full px-4 py-2.5 border {{ $errors->has('selesai_tayang') ? 'border-red-500' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <p class="text-xs text-slate-400 mt-1">Kosongkan bila tayang sampai dihapus.</p>
                @error('selesai_tayang') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
            <a href="{{ route('pengumuman.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Batal</a>
            <button type="submit" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">Simpan</button>
        </div>
    </form>

</div>

@endsection
