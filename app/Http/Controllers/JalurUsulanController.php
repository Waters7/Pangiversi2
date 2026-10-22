<?php

namespace App\Http\Controllers;

use App\Enums\JenisPerjadin;
use App\Models\AuditLog;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\Notifikasi;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Memindahkan usulan ke jalur pengajuan lain, misalnya usulan luar kota
 * lintas pulau yang terlanjur diajukan lewat jalur dalam satu provinsi.
 *
 * Jalur menentukan berkas pertanggungjawaban yang ditagih, jadi yang salah
 * jalur ditagih berkas yang keliru. Perubahan ini hak peran tertentu —
 * bawaannya super administrator — dan selalu disertai alasan.
 */
class JalurUsulanController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
    ) {}

    public function update(Request $request, Usulan $usulan): RedirectResponse
    {
        $jalur = JenisPerjadin::tryFrom((string) $request->input('jalur'));

        $data = $request->validate([
            'jalur' => ['required', Rule::enum(JenisPerjadin::class)],
            'id_kategori_perjadin' => ['required', Rule::in($jalur?->idKategori() ?? [])],
            'alasan' => ['required', 'string', 'max:500'],
        ], [
            'jalur.required' => 'Pilih jalur pengajuan yang baru.',
            'id_kategori_perjadin.required' => 'Pilih kategori perjalanan dinas untuk jalur itu.',
            'id_kategori_perjadin.in' => 'Kategori itu tidak tersedia pada jalur yang dipilih.',
            'alasan.required' => 'Tuliskan alasan perubahan jalurnya.',
        ]);

        $usulan->loadMissing('kategoriPerjadin', 'user');
        $jalurLama = $usulan->jalur();
        $kategoriLama = $usulan->kategoriPerjadin;
        $kategoriBaru = KategoriPerjadin::findOrFail($data['id_kategori_perjadin']);

        if ($jalurLama === $jalur && $kategoriLama?->is($kategoriBaru)) {
            return back()->with('error', 'Jalur dan kategorinya masih sama — tidak ada yang diubah.');
        }

        // Jalur supervisi memaksakan jenis kegiatannya sendiri.
        $kegiatan = $jalur->namaKegiatan() ? Kegiatan::firstOrCreate(['nama' => $jalur->namaKegiatan()]) : null;

        $usulan->update(array_filter([
            'jenis_perjadin' => $jalur->value,
            'id_kategori_perjadin' => $kategoriBaru->id,
            'id_kegiatan' => $kegiatan?->id,
        ], fn ($nilai) => $nilai !== null));

        $perubahan = "dari {$jalurLama->label()} ke {$jalur->label()}";

        if (! $kategoriLama?->is($kategoriBaru)) {
            $perubahan .= ', kategori '.($kategoriLama?->nama ?? '—')." menjadi {$kategoriBaru->nama}";
        }

        $this->audit->catat(
            AuditLog::AKSI_DIPERBARUI,
            "Jalur usulan {$usulan->no_usulan} diubah {$perubahan}. Alasan: {$data['alasan']}",
            ['usulan' => $usulan, 'objek' => 'jalur'],
        );

        // Pelaksana perlu tahu, karena berkas yang ditagih darinya ikut berubah.
        if ($usulan->user && $usulan->id_user !== $request->user()->id) {
            $this->notifikasi->kirim(
                $usulan->user,
                'Jalur perjalanan dinas diubah',
                "Jalur usulan {$usulan->no_usulan} diubah {$perubahan} oleh {$request->user()->nama}. "
                    .'Berkas pertanggungjawaban yang ditagih kini mengikuti jalur baru.',
                [
                    'usulan' => $usulan,
                    'tipe' => Notifikasi::TIPE_INFO,
                    'url' => route('dokumen.show', $usulan->no_usulan),
                ],
            );
        }

        $pesan = "Jalur usulan diubah {$perubahan}.";

        if ($jalur->butuhSpd($kategoriBaru) && ! $usulan->punyaSpdBertandaTangan()) {
            $pesan .= ' Jalur ini berdasar SPD bertanda tangan, sedangkan usulan ini belum memilikinya — lengkapi lewat Edit Usulan.';
        }

        if (! $kategoriLama?->is($kategoriBaru)) {
            $pesan .= ' Kategorinya ikut berubah, jadi periksa kembali rincian biayanya.';
        }

        return redirect()->route('usulan.show', $usulan)->with('success', $pesan);
    }
}
