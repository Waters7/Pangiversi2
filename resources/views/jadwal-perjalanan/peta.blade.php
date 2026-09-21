@extends('app')

@section('title', 'Peta '.$judul)

@push('head')
    {{-- Leaflet dimuat dari CDN; petak petanya dari OpenStreetMap, tanpa kunci API. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"
          integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        #petaPerjadin { height: 520px; z-index: 0; }
        .leaflet-popup-content { margin: 12px 14px; font-family: inherit; }
        .leaflet-popup-content-wrapper { border-radius: 12px; }
        .penanda-kota {
            background: #0d9488; color: #fff; border: 2px solid #fff; border-radius: 9999px;
            box-shadow: 0 4px 12px -2px rgba(13,148,136,.55); display: flex; align-items: center;
            justify-content: center; font-weight: 800; font-size: 12px;
        }
        .penanda-kota--dalam { background: #0ea5e9; box-shadow: 0 4px 12px -2px rgba(14,165,233,.55); }
    </style>
@endpush

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('jadwal-perjalanan') }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600">← Jadwal Perjalanan</a>
            <h1 class="text-xl font-bold text-slate-800 mt-1">Peta Kota Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Kota-kota tujuan pegawai beserta jumlah perjalanan dan orang yang berangkat — klik penanda untuk rinciannya.
            </p>
        </div>

        <form method="GET" action="{{ route('jadwal-perjalanan.peta', $jenis) }}" class="flex items-center gap-2 shrink-0">
            <label for="tahun" class="text-xs font-semibold text-slate-500">Tahun</label>
            <select name="tahun" id="tahun" onchange="this.form.submit()"
                    class="px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <option value="">Semua tahun</option>
                @foreach ($tahunTersedia as $t)
                    <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Dua pemetaan: dalam kota & sekitarnya, dan luar kota --}}
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
        @foreach ([
            \App\Services\PemetaPerjalanan::DALAM_KOTA => 'Dalam Kota & Sekitarnya',
            \App\Services\PemetaPerjalanan::LUAR_KOTA => 'Luar Kota',
        ] as $kode => $label)
            <a href="{{ route('jadwal-perjalanan.peta', array_filter(['jenis' => $kode, 'tahun' => $tahun])) }}"
               class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap
                      {{ $jenis === $kode ? 'bg-teal-500 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-2xl font-bold text-teal-700">{{ $peta['ringkasan']['kota'] }}</p>
            <p class="text-xs text-slate-400">Kota tujuan</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-2xl font-bold text-slate-800">{{ $peta['ringkasan']['perjalanan'] }}</p>
            <p class="text-xs text-slate-400">Perjalanan dinas{{ $tahun ? " tahun $tahun" : '' }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-2xl font-bold text-slate-800">{{ $peta['ringkasan']['pegawai'] }}</p>
            <p class="text-xs text-slate-400">Pegawai yang berangkat</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-800 text-sm">{{ $judul }}</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    @if ($dalamKota)
                        Tujuan di Sulawesi Utara — Manado beserta kota dan kabupaten di sekitarnya. Angka pada penanda adalah jumlah perjalanan.
                    @else
                        Tujuan di luar Sulawesi Utara, ke seluruh Indonesia. Angka pada penanda adalah jumlah perjalanan.
                    @endif
                </p>
            </div>
            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $dalamKota ? 'bg-sky-50 text-sky-700 border border-sky-100' : 'bg-teal-50 text-teal-700 border border-teal-100' }}">
                {{ count($peta['kota']) }} kota terpetakan
            </span>
        </div>

        @if ($peta['kota'] === [])
            <div class="px-6 py-16 text-center text-sm text-slate-400">
                Belum ada perjalanan {{ $dalamKota ? 'dalam kota' : 'luar kota' }}{{ $tahun ? " pada tahun $tahun" : '' }} yang dapat dipetakan.
            </div>
        @else
            <div id="petaPerjadin" data-dalam-kota="{{ $dalamKota ? '1' : '0' }}"></div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">Daftar Kota</h3>
                <p class="text-xs text-slate-400 mt-0.5">Diurutkan dari yang paling sering dituju; klik baris untuk menyorotnya di peta.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="text-left px-6 py-3 font-semibold">Kota</th>
                            <th class="text-center px-4 py-3 font-semibold">Perjalanan</th>
                            <th class="text-center px-4 py-3 font-semibold">Pegawai</th>
                            <th class="text-left px-4 py-3 font-semibold">Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($peta['kota'] as $i => $kota)
                            <tr class="hover:bg-teal-50/60 cursor-pointer transition" data-kota="{{ $i }}">
                                <td class="px-6 py-3">
                                    <p class="font-semibold text-slate-800">{{ $kota['nama'] }}</p>
                                    @if ($kota['provinsi']) <p class="text-xs text-slate-400">{{ $kota['provinsi'] }}</p> @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full bg-teal-100 text-teal-700">{{ $kota['perjalanan'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-slate-700">{{ $kota['pegawai'] }}</td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ $kota['terakhir'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada kota yang terpetakan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">Belum Terpetakan</h3>
                <p class="text-xs text-slate-400 mt-0.5">Kota yang koordinatnya belum dikenal sistem.</p>
            </div>
            @if ($peta['tanpaKoordinat'] === [])
                <p class="px-6 py-8 text-center text-xs text-slate-400">Semua kota tujuan sudah terpetakan.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($peta['tanpaKoordinat'] as $kota)
                        <li class="px-6 py-3 flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold text-slate-700">{{ $kota['nama'] }}</span>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $kota['perjalanan'] }} perjalanan</span>
                        </li>
                    @endforeach
                </ul>
                @can('mengelola-master-data')
                    <p class="px-6 py-3 text-xs text-slate-400 border-t border-slate-100">
                        Isi lintang dan bujurnya pada <a href="{{ route('master.lokasi') }}" class="text-teal-600 font-semibold hover:underline">Master Data → Lokasi Tujuan</a> agar tampil di peta.
                    </p>
                @else
                    <p class="px-6 py-3 text-xs text-slate-400 border-t border-slate-100">
                        Administrator dapat mengisi koordinatnya pada Master Data → Lokasi Tujuan.
                    </p>
                @endcan
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"
        integrity="sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    (function () {
        const wadah = document.getElementById('petaPerjadin');
        if (! wadah || typeof L === 'undefined') return;

        const dalamKota = wadah.dataset.dalamKota === '1';
        const kota = @json($peta['kota']);

        const peta = L.map('petaPerjadin', { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(peta);

        const terbanyak = Math.max(...kota.map(k => k.perjalanan), 1);
        const penanda = [];

        const teks = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        kota.forEach((k, i) => {
            // Ukuran penanda mengikuti banyaknya perjalanan, antara 28 dan 52 piksel.
            const ukuran = 28 + Math.round(24 * k.perjalanan / terbanyak);
            const ikon = L.divIcon({
                className: '',
                html: `<div class="penanda-kota ${dalamKota ? 'penanda-kota--dalam' : ''}" style="width:${ukuran}px;height:${ukuran}px">${k.perjalanan}</div>`,
                iconSize: [ukuran, ukuran],
                iconAnchor: [ukuran / 2, ukuran / 2],
                popupAnchor: [0, -ukuran / 2],
            });

            const baris = k.daftar.map(d => `
                <li style="padding:6px 0;border-top:1px solid #f1f5f9">
                    <div style="font-weight:600;color:#1e293b">${teks(d.pegawai)}</div>
                    <div style="font-size:11px;color:#64748b">${teks(d.tanggal)} · ${teks(d.kegiatan)} · ${teks(d.status)}</div>
                </li>`).join('');

            const isi = `
                <div style="min-width:220px;max-width:300px">
                    <div style="font-weight:800;font-size:14px;color:#0f172a">${teks(k.nama)}</div>
                    ${k.provinsi ? `<div style="font-size:11px;color:#64748b">${teks(k.provinsi)}</div>` : ''}
                    <div style="margin:8px 0;font-size:12px;color:#334155">
                        <strong>${k.perjalanan}</strong> perjalanan · <strong>${k.pegawai}</strong> pegawai
                        ${k.terakhir ? `· terakhir ${teks(k.terakhir)}` : ''}
                    </div>
                    <ul style="list-style:none;margin:0;padding:0;font-size:12px;max-height:200px;overflow:auto">${baris}</ul>
                    ${k.perjalanan > k.daftar.length ? `<div style="font-size:11px;color:#94a3b8;margin-top:6px">…dan ${k.perjalanan - k.daftar.length} perjalanan lainnya</div>` : ''}
                </div>`;

            penanda[i] = L.marker([k.lintang, k.bujur], { icon: ikon, title: k.nama }).addTo(peta).bindPopup(isi);
        });

        if (dalamKota) {
            // Manado dan sekitarnya: cukup dekat untuk membedakan Tomohon dari Bitung.
            peta.setView([1.35, 124.85], 10);
            if (kota.length > 1) peta.fitBounds(L.featureGroup(penanda).getBounds().pad(0.35), { maxZoom: 11 });
        } else {
            peta.fitBounds(L.featureGroup(penanda).getBounds().pad(0.2), { maxZoom: 7 });
        }

        document.querySelectorAll('tr[data-kota]').forEach(tr => {
            tr.addEventListener('click', () => {
                const p = penanda[Number(tr.dataset.kota)];
                if (! p) return;
                peta.flyTo(p.getLatLng(), Math.max(peta.getZoom(), dalamKota ? 11 : 8), { duration: 0.6 });
                p.openPopup();
                wadah.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    })();
</script>
@endpush
