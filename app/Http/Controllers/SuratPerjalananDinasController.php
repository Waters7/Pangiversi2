<?php

namespace App\Http\Controllers;

use App\Enums\Golongan;
use App\Enums\Kemampuan;
use App\Models\LokasiTujuan;
use App\Models\Notifikasi;
use App\Models\SpdPelaksana;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Services\EkspresiTanggal;
use App\Services\KertasCetak;
use App\Services\NotifikasiService;
use App\Services\PenomoranPerjadin;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SuratPerjalananDinasController extends Controller
{
    public function __construct(
        private EkspresiTanggal $tanggal,
        private NotifikasiService $notifikasi,
        private PenomoranPerjadin $penomoran,
    ) {}

    public function index(Request $request): View
    {
        $bolehLihatSemua = $this->bolehMelihatSemua($request);

        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');
        $cari = $request->input('cari');

        $dasar = fn () => SuratPerjalananDinas::query()
            ->when(! $bolehLihatSemua, fn ($q) => $this->batasiMilikSendiri($q, $request));

        // Tahun dan bulan dibaca dari tanggal surat, bukan tanggal dibuatnya:
        // arsip disusun menurut kapan suratnya berlaku.
        $spd = $dasar()
            ->when($cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('tempat_tujuan', 'like', "%{$cari}%")
                ->orWhere('maksud', 'like', "%{$cari}%")
                ->orWhereHas('pelaksana', fn ($p) => $p
                    ->where('nomor_surat', 'like', "%{$cari}%")
                    ->orWhere('nama', 'like', "%{$cari}%"))))
            ->with('pelaksana', 'pembuat')
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_surat', $tahun))
            ->when($bulan, fn ($q) => $q->whereMonth('tanggal_surat', $bulan))
            ->orderByDesc('tanggal_surat')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Hanya tahun yang benar-benar punya surat yang ditawarkan, supaya
        // tombolnya tidak memuat tahun kosong.
        $tahunTersedia = $dasar()
            ->whereNotNull('tanggal_surat')
            ->selectRaw($this->tanggal->tahun('tanggal_surat').' as tahun')
            ->distinct()
            ->pluck('tahun')
            ->filter()
            ->map(fn ($nilai) => (int) $nilai)
            ->sortDesc()
            ->values();

        // Jumlah per bulan pada tahun yang sedang dilihat, agar tombol bulan
        // langsung memperlihatkan mana yang berisi.
        $jumlahBulan = $dasar()
            ->whereNotNull('tanggal_surat')
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_surat', $tahun))
            ->selectRaw($this->tanggal->bulan('tanggal_surat').' as bulan, count(*) as jumlah')
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan')
            ->mapWithKeys(fn ($jumlah, $kunci) => [(int) $kunci => (int) $jumlah]);

        return view('spd.index', compact(
            'spd', 'bolehLihatSemua', 'tahun', 'bulan', 'cari', 'tahunTersedia', 'jumlahBulan',
        ));
    }

    public function create(Request $request): View
    {
        return view('spd.create', $this->bekalFormulir($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->periksa($request);

        $spd = DB::transaction(function () use ($data, $request) {
            // Tanggal dikeluarkan mengikuti tanggal pembuatan SPD. Operator +
            // mempertahankan nilai operand kiri, jadi tanggal pilihan pimpinan
            // atau administrator — bila ada — menang atas hari ini.
            $spd = SuratPerjalananDinas::create($this->kolomPokok($data) + [
                'id_pembuat' => $request->user()->id,
                'tanggal_surat' => today(),
                'surat_tugas' => $this->simpanSuratTugas($request),
            ]);

            $this->simpanPelaksana($spd, $data);
            $this->simpanPengikut($spd, $data);

            return $spd;
        });

        $this->kabarkanTerbit($spd, $request->user());

        return redirect()
            ->route('spd.show', $spd)
            ->with('success', 'Surat Perjalanan Dinas berhasil disimpan.');
    }

    /**
     * Beri tahu pelaksana bahwa SPD-nya sudah terbit.
     *
     * Usulan perjadin baru boleh diajukan setelah SPD ada. Tanpa kabar ini
     * pelaksana tidak punya cara lain selain masuk berkali-kali memeriksa
     * sendiri, dan berkasnya menganggur sementara ia menunggu.
     *
     * @param  list<int>  $lewati  Pengguna yang sudah pernah dikabari.
     */
    private function kabarkanTerbit(SuratPerjalananDinas $spd, User $pembuat, array $lewati = []): void
    {
        $penerima = $spd->pelaksana()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            // Pembuatnya sudah tahu — ia baru saja menerbitkannya sendiri.
            ->reject(fn (User $orang) => $orang->is($pembuat))
            ->reject(fn (User $orang) => in_array($orang->id, $lewati, true));

        if ($penerima->isEmpty()) {
            return;
        }

        $this->notifikasi->kirimKeBanyak(
            $penerima,
            'Surat Perjalanan Dinas terbit',
            "SPD perjalanan dinas ke {$spd->tempat_tujuan} sudah terbit. "
            .'Usulan perjadin sudah dapat diajukan.',
            ['tipe' => Notifikasi::TIPE_SUKSES, 'url' => route('spd.show', $spd)],
        );
    }

    public function show(Request $request, SuratPerjalananDinas $spd): View
    {
        $this->pastikanBolehMelihat($request, $spd);

        $spd->load('pelaksana.user', 'pengikut', 'pembuat');

        return view('spd.show', compact('spd'));
    }

    public function edit(Request $request, SuratPerjalananDinas $spd): View
    {
        $this->pastikanBolehMengubah($request, $spd);

        $spd->load('pelaksana', 'pengikut');

        return view('spd.edit', $this->bekalFormulir($request->user(), $spd) + compact('spd'));
    }

    public function update(Request $request, SuratPerjalananDinas $spd): RedirectResponse
    {
        $this->pastikanBolehMengubah($request, $spd);

        $data = $this->periksa($request, $spd);

        $bolehPengikut = $request->user()->punyaKemampuan(Kemampuan::MengisiPengikutSpd);
        $sudahDikabari = $spd->pelaksana()->pluck('id_user')->filter()->values()->all();

        $suratTugasBaru = $this->simpanSuratTugas($request);

        DB::transaction(function () use ($spd, $data, $bolehPengikut, $suratTugasBaru) {
            // Berkas lama dipertahankan bila tidak ada unggahan baru.
            $spd->update($this->kolomPokok($data) + ($suratTugasBaru ? ['surat_tugas' => $suratTugasBaru] : []));

            // Pelaksana disusun ulang seluruhnya: barisnya dapat bertambah,
            // berkurang, atau berpindah urutan, sehingga menyamakan satu per
            // satu lebih rumit daripada menulis ulang.
            $spd->pelaksana()->delete();
            $this->simpanPelaksana($spd, $data);

            // Pengikut hanya disusun ulang oleh yang berwenang mengisinya.
            // Tanpa penjagaan ini, pelaksana yang menyunting suratnya sendiri
            // akan menghapus pengikut yang dipasang pimpinan — formulirnya
            // memang tidak memuat bagian itu, jadi kiriman tanpa pengikut
            // akan terbaca sebagai perintah mengosongkan.
            if ($bolehPengikut) {
                $spd->pengikut()->delete();
                $this->simpanPengikut($spd, $data);
            }
        });

        // Pelaksana yang baru ditambahkan lewat penyuntingan juga perlu tahu
        // SPD-nya sudah ada; tanpa ini hanya yang tercantum sejak awal yang
        // dikabari, dan sisanya menunggu tanpa pernah diberi tahu.
        $this->kabarkanTerbit($spd->fresh(), $request->user(), $sudahDikabari);

        return redirect()
            ->route('spd.show', $spd)
            ->with('success', 'Surat Perjalanan Dinas berhasil diperbarui.');
    }

    public function destroy(Request $request, SuratPerjalananDinas $spd): RedirectResponse
    {
        $this->pastikanBolehMengubah($request, $spd);

        $spd->delete();

        return redirect()->route('spd.index')->with('success', 'Surat Perjalanan Dinas dihapus.');
    }

    /**
     * Pratinjau sebelum disimpan: dokumen dirender dari masukan formulir
     * tanpa menyentuh basis data.
     */
    public function pratinjau(Request $request): Response
    {
        $data = $this->periksa($request);

        $spd = new SuratPerjalananDinas($data + [
            // Belum tersimpan, jadi tanggal terbitnya hari ini.
            'tanggal_surat' => today(),
            'lama_hari' => SuratPerjalananDinas::hitungLamaHari(
                $data['tanggal_berangkat'],
                $data['tanggal_kembali'],
            ),
        ]);

        // Nomor dirakit sama seperti saat disimpan, supaya pratinjaunya
        // memperlihatkan nomor surat yang benar-benar akan tercetak.
        $pelaksana = collect(array_values($data['pelaksana']))
            ->map(fn (array $orang, int $i) => new SpdPelaksana(array_merge($orang, [
                'urutan' => $i + 1,
                'nomor_surat' => $this->nomorBaru($orang, $spd),
            ])));

        $pengikut = collect($data['pengikut'] ?? [])
            ->filter(fn ($ikut) => filled($ikut['nama'] ?? null))
            ->values();

        return $this->render($spd, $pelaksana, $pengikut, 'stream');
    }

    public function cetak(Request $request, SuratPerjalananDinas $spd): Response
    {
        $this->pastikanBolehMelihat($request, $spd);

        $spd->load('pelaksana', 'pengikut');

        // ?tampil=1 dipakai bingkai pratinjau agar dokumennya terbuka di
        // tempat, bukan terunduh.
        $cara = $request->boolean('tampil') ? 'stream' : 'unduh';

        return $this->render($spd, $spd->pelaksana, $spd->pengikut, $cara);
    }

    // ── Pembantu ──

    /**
     * Kolom milik berkas SPD itu sendiri, di luar pelaksana dan pengikut.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function kolomPokok(array $data): array
    {
        return [
            'dikeluarkan_di' => $data['dikeluarkan_di'],
            'maksud' => $data['maksud'],
            'alat_angkut' => $data['alat_angkut'],
            'tempat_berangkat' => $data['tempat_berangkat'],
            'tempat_tujuan' => $data['tempat_tujuan'],
            'tanggal_berangkat' => $data['tanggal_berangkat'],
            'tanggal_kembali' => $data['tanggal_kembali'],
            'lama_hari' => SuratPerjalananDinas::hitungLamaHari(
                $data['tanggal_berangkat'],
                $data['tanggal_kembali'],
            ),
            'instansi_pembebanan' => $data['instansi_pembebanan'] ?? null,
            // Akun pembebanan diisi PPK saat verifikasi dan tanda tangan,
            // bukan oleh pembuat SPD.
            'keterangan_lain' => $data['keterangan_lain'] ?? null,
            'no_tugas' => filled($data['no_tugas'] ?? null) ? trim($data['no_tugas']) : null,
        ]
        // Kuncinya hanya ada bila periksa() meloloskannya, yaitu ketika
        // penggunanya berwenang. Peran lain tidak menyentuh kolom ini sama
        // sekali, sehingga tanggal terbitnya tetap seperti semula.
        + (isset($data['tanggal_surat']) ? ['tanggal_surat' => $data['tanggal_surat']] : []);
    }

    /**
     * Simpan unggahan surat tugas; null bila tidak ada berkas baru.
     */
    private function simpanSuratTugas(Request $request): ?string
    {
        return $request->hasFile('surat_tugas')
            ? $request->file('surat_tugas')->store('dokumen/surat-tugas', 'public')
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function simpanPelaksana(SuratPerjalananDinas $spd, array $data): void
    {
        foreach (array_values($data['pelaksana']) as $urutan => $orang) {
            $spd->pelaksana()->create([
                'urutan' => $urutan + 1,
                'id_user' => $orang['id_user'] ?? null,
                'nomor_surat' => $this->nomorBaru($orang, $spd),
                'nama' => $orang['nama'],
                'nip' => $orang['nip'] ?? null,
                'pangkat_golongan' => $orang['pangkat_golongan'] ?? null,
                'jabatan_instansi' => $orang['jabatan_instansi'] ?? null,
                'tingkat_biaya' => $orang['tingkat_biaya'] ?? null,
            ]);
        }
    }

    /**
     * Nomor SPD berikutnya, berpola sama dengan nomor perjadin.
     *
     * Pratinjau memakai jalur yang sama agar nomor yang diperlihatkan sama
     * dengan yang nanti tersimpan.
     *
     * @param  array<string, mixed>  $orang
     */
    private function nomorBaru(array $orang, SuratPerjalananDinas $spd): string
    {
        $pemilik = isset($orang['id_user']) ? User::find($orang['id_user']) : null;

        return $this->penomoran->nomorSpd($pemilik, $spd->tanggal_berangkat);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function simpanPengikut(SuratPerjalananDinas $spd, array $data): void
    {
        foreach ($data['pengikut'] ?? [] as $ikut) {
            if (blank($ikut['nama'] ?? null)) {
                continue;
            }

            $spd->pengikut()->create([
                'nama' => $ikut['nama'],
                'tanggal_lahir' => $ikut['tanggal_lahir'] ?? null,
                'keterangan' => $ikut['keterangan'] ?? null,
            ]);
        }
    }

    /**
     * @param  Collection<int, SpdPelaksana>  $pelaksana
     * @param  Collection<int, mixed>  $pengikut
     */
    private function render(SuratPerjalananDinas $spd, $pelaksana, $pengikut, string $cara): Response
    {
        $pdf = Pdf::loadView('spd.cetak', [
            'spd' => $spd,
            'daftarPelaksana' => $pelaksana,
            'pengikut' => $pengikut,
            'ppk' => User::where('role', User::ROLE_PPK)->first(),
            // Pengesahan keberangkatan awal ditandatangani Direktur.
            'direktur' => User::where('role', User::ROLE_PIMPINAN)
                ->where('jabatan', 'like', '%Direktur%')
                ->whereRaw('LOWER(jabatan) not like ?', ['%wakil%'])
                ->first(),
        ])->setPaper(KertasCetak::UKURAN);

        $nomor = $pelaksana->first()?->nomor_surat ?? 'SPD';
        $berkas = 'SPD_'.str_replace(['/', ' '], ['-', ''], $nomor).'.pdf';

        return $cara === 'unduh' ? $pdf->download($berkas) : $pdf->stream($berkas);
    }

    /**
     * @return array<string, mixed>
     */
    private function periksa(Request $request, ?SuratPerjalananDinas $spd = null): array
    {
        $data = $request->validate([
            'dikeluarkan_di' => ['required', 'string', 'max:100'],

            'pelaksana' => ['required', 'array', 'min:1', 'max:'.SuratPerjalananDinas::MAKS_PELAKSANA],
            'pelaksana.*.nama' => ['required', 'string', 'max:255'],
            'pelaksana.*.nip' => ['required', 'string', 'max:50'],
            'pelaksana.*.id_user' => ['nullable', 'exists:users,id'],
            'pelaksana.*.pangkat_golongan' => ['nullable', 'string', 'max:100'],
            'pelaksana.*.jabatan_instansi' => ['nullable', 'string', 'max:255'],
            'pelaksana.*.tingkat_biaya' => ['nullable', 'string', 'max:100'],

            'maksud' => ['required', 'string'],
            'alat_angkut' => ['required', 'string', 'max:100'],
            'tempat_berangkat' => ['required', 'string', 'max:150'],
            'tempat_tujuan' => ['required', 'string', 'max:150'],
            'tanggal_berangkat' => ['required', 'date'],
            'tanggal_kembali' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],

            'pengikut' => ['nullable', 'array', 'max:'.SuratPerjalananDinas::MAKS_PENGIKUT],
            'pengikut.*.nama' => ['nullable', 'string', 'max:255'],
            'pengikut.*.tanggal_lahir' => ['nullable', 'date'],
            'pengikut.*.keterangan' => ['nullable', 'string', 'max:255'],

            'instansi_pembebanan' => ['nullable', 'string', 'max:255'],
            'keterangan_lain' => ['nullable', 'string'],

            // Surat tugas dilampirkan di sini supaya usulan perjadin tidak
            // perlu mengunggah dan menyalin nomornya lagi.
            'no_tugas' => ['nullable', 'string', 'max:255'],
            'surat_tugas' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'surat_tugas.mimes' => 'Surat tugas harus berupa PDF, JPG, atau PNG.',
            'surat_tugas.max' => 'Ukuran surat tugas paling besar 5 MB.',
            'pelaksana.max' => 'Paling banyak '.SuratPerjalananDinas::MAKS_PELAKSANA.' pelaksana dalam satu SPD.',
            'pengikut.max' => 'Paling banyak '.SuratPerjalananDinas::MAKS_PENGIKUT.' pengikut.',
            'tanggal_kembali.after_or_equal' => 'Tanggal kembali tidak boleh mendahului tanggal berangkat.',
        ]);

        // Pengikut hanya diterima dari yang berwenang mencantumkannya; kiriman
        // peran lain dibuang di sini, bukan diabaikan di penyimpanan, supaya
        // formulir yang diakali dari luar pun tidak menyelundupkannya.
        if (! $request->user()->punyaKemampuan(Kemampuan::MengisiPengikutSpd)) {
            unset($data['pengikut']);
        }

        // Tanggal terbit diperiksa terpisah dan hanya ikut bila penggunanya
        // berwenang, sehingga kiriman dari peran lain tidak pernah sampai ke
        // kolomPokok() sekalipun formulirnya diakali dari luar.
        if ($this->bolehMengubahTanggal($request)) {
            $data += $request->validate([
                'tanggal_surat' => ['required', 'date'],
            ], [
                'tanggal_surat.required' => 'Tanggal dikeluarkan wajib diisi.',
            ]);
        }

        return $data;
    }

    /**
     * Tanggal terbit SPD hanya boleh disesuaikan pimpinan dan administrator —
     * atau siapa pun selama super administrator membuka kuncinya untuk
     * keperluan tanggal mundur.
     *
     * Bagi yang lain tanggalnya mengikuti tanggal pembuatan, supaya tanggal
     * pada dokumen tidak berselisih dengan kapan surat benar-benar terbit.
     */
    private function bolehMengubahTanggal(Request $request): bool
    {
        return $request->user()->bolehMengubahTanggalSpd();
    }

    /**
     * @return array<string, mixed>
     */
    private function bekalFormulir(User $pengguna, ?SuratPerjalananDinas $spd = null): array
    {
        return [
            'pengguna' => $pengguna,
            // Tahun mengikuti tanggal surat diterbitkan, bukan tahun
            // berjalan: SPD lama yang disunting tidak boleh berpindah tahun.
            'tahunSurat' => $spd?->tanggal_surat?->year ?? now()->year,
            'alatAngkut' => SuratPerjalananDinas::alatAngkutOptions(),
            'daftarGolongan' => Golongan::terkelompok(),
            // Lokasi diambil dari data referensi yang sama dengan formulir
            // usulan perjadin, supaya penulisan kotanya seragam.
            'lokasiTujuan' => LokasiTujuan::aktif()->orderBy('nama')->get(),
            // Golongan ikut dibawa agar kolom pangkat terisi sendiri saat
            // pelaksana dipilih dari daftar pegawai.
            'calonPelaksana' => User::whereKeyNot($pengguna->id)
                ->orderBy('nama')
                ->get(['id', 'nama', 'nip', 'jabatan', 'golongan']),
            'maksPelaksana' => SuratPerjalananDinas::MAKS_PELAKSANA,
            'maksPengikut' => SuratPerjalananDinas::MAKS_PENGIKUT,
        ];
    }

    /**
     * Pengguna biasa hanya melihat SPD yang dibuatnya atau yang mencantumkan
     * dirinya sebagai pelaksana.
     *
     * @param  Builder<SuratPerjalananDinas>  $query
     */
    private function batasiMilikSendiri($query, Request $request): void
    {
        $query->where(function ($q) use ($request) {
            $q->where('id_pembuat', $request->user()->id)
                ->orWhereHas('pelaksana', fn ($p) => $p->where('id_user', $request->user()->id));
        });
    }

    /**
     * Hanya administrator dan Tim SDM yang berhak melihat seluruh SPD.
     *
     * Peran lain — termasuk PPK, pimpinan, dan bendahara — hanya melihat
     * SPD yang dibuatnya sendiri atau yang mencantumkan namanya sebagai
     * pelaksana, karena SPD memuat nama, NIP, dan pangkat pegawai.
     */
    private function bolehMelihatSemua(Request $request): bool
    {
        return $request->user()->punyaKemampuan(Kemampuan::MelihatSemuaSpd);
    }

    private function pastikanBolehMelihat(Request $request, SuratPerjalananDinas $spd): void
    {
        if ($this->bolehMelihatSemua($request)) {
            return;
        }

        abort_unless($spd->milik($request->user()), 403, 'Surat Perjalanan Dinas ini bukan milik Anda.');
    }

    private function pastikanBolehMengubah(Request $request, SuratPerjalananDinas $spd): void
    {
        abort_unless(
            $spd->bolehDiubahOleh($request->user()),
            403,
            'Surat Perjalanan Dinas ini bukan milik Anda.',
        );
    }
}
