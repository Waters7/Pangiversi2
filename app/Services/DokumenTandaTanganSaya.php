<?php

namespace App\Services;

use App\Models\DaftarRiil;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Support\Collection;

/**
 * Rincian biaya dan daftar riil yang sudah dikirim tim keuangan ke Rincian
 * Saya dan menunggu tanda tangan pengguna ini sebagai pelaksana. Dipakai
 * kartu dashboard dan popup saat masuk.
 *
 * Berlaku bagi semua peran, sebab pimpinan dan tim keuangan pun bisa
 * menjadi pelaksana. Yang terdekat batas sanggahnya tampil lebih dulu.
 */
class DokumenTandaTanganSaya
{
    /**
     * @return Collection<int, array{jalur: JalurPersetujuan, usulan: Usulan, tautan: string}>
     */
    public function untuk(User $user): Collection
    {
        return DaftarRiil::with(['usulan.keuangan.rincianBiaya', 'rincian'])
            ->whereNotNull('dikirim_ke_pegawai_at')
            ->whereHas('peserta', fn ($query) => $query->where('id_user', $user->id))
            ->orderBy('batas_sanggah')
            ->get()
            ->flatMap(fn (DaftarRiil $berkas) => [$berkas->jalurRincian(), $berkas->jalur()])
            ->filter(fn (JalurPersetujuan $jalur) => $jalur->bolehDitandatanganiPelaksana())
            ->map(fn (JalurPersetujuan $jalur) => [
                'jalur' => $jalur,
                'usulan' => $jalur->berkas()->usulan,
                'tautan' => route(
                    $jalur->jenis() === JalurPersetujuan::RINCIAN ? 'rincian-saya.rincian-biaya' : 'rincian-saya.daftar-riil',
                    ['cari' => $jalur->berkas()->usulan->no_usulan],
                ),
            ])
            ->values();
    }
}
