@extends('app')


@section('content')

@endsection
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Daftar Usulan</h1>
        <p class="text-gray-600 mt-2">Kelola dan lihat semua usulan yang telah dibuat</p>
    </div>

    @if($usulan->count())
        <div class="grid gap-6">
            @foreach($usulan as $item)
                <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900">{{ $item->judul }}</h2>
                                <p class="text-sm text-gray-500 mt-1">{{ $item->created_at->format('d M Y') }}</p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $item->status === 'approved' ? 'bg-green-100 text-green-800' : ($item->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </div>

                        <p class="text-gray-700 mb-4 line-clamp-2">{{ $item->deskripsi }}</p>

                        <div class="flex gap-3">
                            <a href="{{ route('usulan.show', $item) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Lihat Detail
                            </a>
                            <a href="{{ route('usulan.edit', $item) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                                Edit
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($usulan->hasPages())
            <div class="mt-8">
                {{ $usulan->links() }}
            </div>
        @endif
    @else
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-500 text-lg">Belum ada usulan. <a href="{{ route('usulan.create') }}" class="text-blue-600 hover:text-blue-800 font-semibold">Buat yang pertama</a></p>
        </div>
    @endif
</div>
@push('scripts')
<script>
    // Dummy data for frontend development
    const dummyUsulan = [
        {
            id: 1,
            judul: 'Pengembangan Sistem Manajemen Inventori',
            deskripsi: 'Implementasi sistem baru untuk mengelola inventori dengan lebih efisien dan real-time tracking.',
            status: 'approved',
            created_at: '15 Jan 2025'
        },
        {
            id: 2,
            judul: 'Peningkatan Infrastruktur Server',
            deskripsi: 'Upgrade server dan infrastruktur untuk meningkatkan kapasitas dan keamanan data perusahaan.',
            status: 'pending',
            created_at: '14 Jan 2025'
        },
        {
            id: 3,
            judul: 'Workshop Pelatihan Tim Development',
            deskripsi: 'Program pelatihan untuk meningkatkan skill dan kompetensi tim development dalam teknologi terbaru.',
            status: 'rejected',
            created_at: '13 Jan 2025'
        }
    ];
</script>
@endpush