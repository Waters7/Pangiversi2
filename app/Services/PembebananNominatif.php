<?php

namespace App\Services;

use App\Models\DaftarNominatif;
use App\Models\PaguTimKerja;
use App\Models\TahunAnggaran;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Pembebanan sebuah daftar nominatif: sumber dana, akun pembiayaan, dan
 * tim kerja yang pagunya dibebani.
 *
 * Tim kerja yang boleh dipilih mengikuti pagu tim kerja pada akun itu di
 * tahun anggaran daftarnya; tim yang berpagu dari dua sumber dana pada
 * akun yang sama wajib disertai sumber dananya.
 */
class PembebananNominatif
{
    /**
     * Alokasi pagu tim kerja satu tahun anggaran, untuk mengisi pilihan
     * tim kerja menurut akun yang dipilih.
     *
     * @return array<int, list<array{tim: int, nama: string, sumber: ?int}>>
     */
    public function pilihanTim(?TahunAnggaran $tahun): array
    {
        if (! $tahun) {
            return [];
        }

        return $this->alokasi($tahun)
            ->groupBy('id_akun_pembiayaan')
            ->map(fn (Collection $baris) => $baris
                ->map(fn (PaguTimKerja $pagu) => [
                    'tim' => $pagu->id_tim_kerja,
                    'nama' => $pagu->timKerja->nama,
                    'sumber' => $pagu->id_kategori_pembiayaan,
                ])
                ->values()
                ->all())
            ->all();
    }

    /**
     * Tahun anggaran tiap daftar beserta pilihan tim kerjanya, dimuat sekali
     * untuk seluruh daftar yang tampil.
     *
     * @param  Collection<int, DaftarNominatif>  $daftar
     * @return array{tahun: array<int, ?int>, pilihan: array<int, array<int, list<array{tim: int, nama: string, sumber: ?int}>>>}
     */
    public function untukDaftar(Collection $daftar): array
    {
        $semua = TahunAnggaran::all()->keyBy('tahun');
        $aktif = TahunAnggaran::aktif();

        $tahun = $daftar->mapWithKeys(fn (DaftarNominatif $nominatif) => [
            $nominatif->id => ($semua->get($nominatif->tahunPembebanan() ?? 0) ?? $aktif)?->id,
        ]);

        $pilihan = $tahun->filter()->unique()->mapWithKeys(fn (int $id) => [
            $id => $this->pilihanTim($semua->firstWhere('id', $id)),
        ]);

        return ['tahun' => $tahun->all(), 'pilihan' => $pilihan->all()];
    }

    /**
     * Validasi dan simpan pembebanan. Sumber dana yang dikosongkan terisi
     * sendiri bila tim kerja itu hanya berpagu dari satu sumber dana pada
     * akun yang dipilih.
     */
    public function tetapkan(Request $request, DaftarNominatif $nominatif): void
    {
        $alokasi = $this->alokasi($nominatif->tahunAnggaran());

        $data = $request->validate([
            'id_kategori_pembiayaan' => ['nullable', 'exists:kategori_pembiayaan,id'],
            'id_akun_pembiayaan' => ['nullable', 'exists:akun_pembiayaan,id'],
            'id_tim_kerja' => ['nullable', 'exists:tim_kerja,id', $this->timSesuaiAkun($request, $alokasi)],
        ], [
            'id_tim_kerja.exists' => 'Tim kerja itu tidak ditemukan.',
        ]);

        if (filled($data['id_tim_kerja'] ?? null) && blank($data['id_kategori_pembiayaan'] ?? null)) {
            $data['id_kategori_pembiayaan'] = $this->cocok($alokasi, $data['id_akun_pembiayaan'], $data['id_tim_kerja'])
                ->first()?->id_kategori_pembiayaan;
        }

        $nominatif->update([
            'id_kategori_pembiayaan' => $data['id_kategori_pembiayaan'] ?? null,
            'id_akun_pembiayaan' => $data['id_akun_pembiayaan'] ?? null,
            'id_tim_kerja' => $data['id_tim_kerja'] ?? null,
        ]);
    }

    /**
     * @return Collection<int, PaguTimKerja>
     */
    private function alokasi(?TahunAnggaran $tahun): Collection
    {
        if (! $tahun) {
            return collect();
        }

        return PaguTimKerja::with('timKerja', 'sumberDana')
            ->where('id_tahun_anggaran', $tahun->id)
            ->whereHas('timKerja', fn ($query) => $query->where('is_aktif', true))
            ->get()
            ->sortBy(fn (PaguTimKerja $pagu) => [$pagu->timKerja->urutan, $pagu->timKerja->nama, $pagu->sumberDana?->kode])
            ->values();
    }

    /**
     * Tim kerja harus berpagu pada akun yang dipilih — dan pada sumber
     * dananya bila sumber dana diisi atau tim itu berpagu dari lebih dari
     * satu sumber dana.
     *
     * @param  Collection<int, PaguTimKerja>  $alokasi
     */
    private function timSesuaiAkun(Request $request, Collection $alokasi): Closure
    {
        return function (string $atribut, mixed $nilai, Closure $gagal) use ($request, $alokasi): void {
            $akun = $request->input('id_akun_pembiayaan');

            if (blank($akun)) {
                $gagal('Pilih akun pembiayaan sebelum tim kerjanya.');

                return;
            }

            $cocok = $this->cocok($alokasi, $akun, $nilai);

            if ($cocok->isEmpty()) {
                $gagal('Tim kerja itu tidak berpagu pada akun yang dipilih.');

                return;
            }

            $sumber = $request->input('id_kategori_pembiayaan');

            if (filled($sumber) && $cocok->where('id_kategori_pembiayaan', (int) $sumber)->isEmpty()) {
                $gagal('Tim kerja itu tidak berpagu dari sumber dana yang dipilih pada akun ini.');
            } elseif (blank($sumber) && $cocok->count() > 1) {
                $gagal('Tim kerja ini berpagu dari lebih dari satu sumber dana pada akun ini — pilih sumber dananya.');
            }
        };
    }

    /**
     * @param  Collection<int, PaguTimKerja>  $alokasi
     * @return Collection<int, PaguTimKerja>
     */
    private function cocok(Collection $alokasi, mixed $akun, mixed $tim): Collection
    {
        return $alokasi
            ->where('id_akun_pembiayaan', (int) $akun)
            ->where('id_tim_kerja', (int) $tim)
            ->values();
    }
}
