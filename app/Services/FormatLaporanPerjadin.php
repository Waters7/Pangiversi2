<?php

namespace App\Services;

use App\Models\User;
use App\Models\Usulan;
use Carbon\Carbon;

/**
 * Menyiapkan isi format baku Laporan Perjalanan Dinas, mengikuti berkas
 * yang selama ini dipakai pegawai Poltekkes Kemenkes Manado.
 *
 * Identitas, surat tugas, dan jadwalnya diisi sistem; uraian kegiatan dan
 * rencana tindak lanjut sengaja dikosongkan untuk ditulis pelaksana.
 */
class FormatLaporanPerjadin
{
    /**
     * @return array<string, mixed>
     */
    public function data(Usulan $usulan): array
    {
        $usulan->loadMissing('user', 'kegiatan');

        $mulai = Carbon::parse($usulan->tanggal_mulai);
        $selesai = Carbon::parse($usulan->tanggal_selesai);

        return [
            'usulan' => $usulan,
            'direktur' => $this->direktur(),
            'tmt' => $this->rentangTmt($mulai, $selesai),
            'maksud' => $this->maksud($usulan),
            'tempat' => trim($usulan->instansi.' — '.$usulan->lokasi, ' —'),
            'hariTanggal' => $this->hariTanggal($mulai, $selesai),
        ];
    }

    /**
     * Direktur didahulukan atas wakil direktur sebagai penandatangan.
     */
    public function direktur(): ?User
    {
        return User::where('role', User::ROLE_PIMPINAN)
            ->where('jabatan', 'like', '%Direktur%')
            ->orderByRaw("CASE WHEN jabatan = 'Direktur' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    /**
     * "23–24 Juni 2026" bila masih sebulan, selain itu tanggal lengkap keduanya.
     */
    public function rentangTmt(Carbon $mulai, Carbon $selesai): string
    {
        if ($mulai->isSameDay($selesai)) {
            return $mulai->translatedFormat('d F Y');
        }

        return $mulai->isSameMonth($selesai)
            ? $mulai->format('d').'–'.$selesai->translatedFormat('d F Y')
            : $mulai->translatedFormat('d F Y').' – '.$selesai->translatedFormat('d F Y');
    }

    public function hariTanggal(Carbon $mulai, Carbon $selesai): string
    {
        if ($mulai->isSameDay($selesai)) {
            return $mulai->translatedFormat('l, d F Y');
        }

        return $mulai->translatedFormat('l').' s/d '.$selesai->translatedFormat('l').', '
            .$mulai->format('d').' s/d '.$selesai->translatedFormat('d F Y');
    }

    public function maksud(Usulan $usulan): string
    {
        $uraian = trim((string) $usulan->uraian);

        if ($uraian !== '') {
            return $uraian;
        }

        return trim(($usulan->kegiatan?->nama ?? 'Melaksanakan perjalanan dinas')
            .' di '.$usulan->instansi.', '.$usulan->lokasi, ' ,');
    }
}
