<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SpdPelaksana;
use App\Models\SpdPengikut;
use App\Models\SuratPerjalananDinas;
use App\Models\Usulan;
use Carbon\Carbon;

/**
 * Merekam penghapusan usulan perjadin dan SPD sebelum barisnya hilang.
 *
 * Penghapusan ikut menghapus seluruh relasinya, jadi yang tersisa untuk
 * ditelusuri hanya jejak audit. Cuplikan isinya — nomor, pelaksana, tujuan,
 * tanggal, dan maksud perjalanan — disimpan bersama catatan itu supaya
 * riwayat penghapusan tetap menjawab "apa yang dihapus" tanpa barang aslinya.
 *
 * Bentuk cuplikannya sama untuk kedua objek, sehingga satu halaman dapat
 * menampilkannya berdampingan.
 */
class RekamPenghapusan
{
    public function __construct(private AuditService $audit) {}

    public function usulan(Usulan $usulan): AuditLog
    {
        $usulan->loadMissing(['peserta', 'user', 'pembuat', 'kegiatan', 'kategoriPerjadin']);

        $pelaksana = $usulan->peserta->pluck('nama')->filter()->values();

        return $this->audit->catat(AuditLog::AKSI_DIHAPUS, "Usulan {$usulan->no_usulan} dihapus.", [
            'objek' => AuditLog::OBJEK_USULAN,
            'status_lama' => $usulan->status,
            'cuplikan' => [
                'nomor' => $usulan->no_usulan,
                'pelaksana' => ($pelaksana->isNotEmpty() ? $pelaksana : collect([$usulan->user?->nama]))->filter()->values()->all(),
                'tujuan' => $this->gabung([$usulan->lokasi, $usulan->instansi]),
                'tanggal_mulai' => $this->tanggal($usulan->tanggal_mulai),
                'tanggal_selesai' => $this->tanggal($usulan->tanggal_selesai),
                'maksud' => $usulan->uraian,
                'rincian' => array_filter([
                    'Jalur' => $usulan->jenis_perjadin ? $usulan->jalur()->label() : null,
                    'Kategori' => $usulan->kategoriPerjadin?->nama,
                    'Kegiatan' => $usulan->kegiatan?->nama,
                    'No. SPD' => $usulan->no_spd,
                    'No. Surat Tugas' => $usulan->no_tugas,
                    'Diajukan oleh' => $usulan->pembuat?->nama ?? $usulan->user?->nama,
                    'Dibuat' => $usulan->created_at?->translatedFormat('d M Y, H:i'),
                ]),
            ],
        ]);
    }

    public function spd(SuratPerjalananDinas $spd): AuditLog
    {
        $spd->loadMissing(['pelaksana', 'pengikut', 'pembuat', 'usulan']);

        $nomor = $spd->pelaksana->pluck('nomor_surat')->filter()->unique()->values();

        return $this->audit->catat(AuditLog::AKSI_DIHAPUS, 'SPD '.($nomor->first() ?? '(tanpa nomor)').' dihapus.', [
            'objek' => AuditLog::OBJEK_SPD,
            'cuplikan' => [
                'nomor' => $nomor->first(),
                'pelaksana' => $spd->pelaksana->map(fn (SpdPelaksana $orang) => $orang->nama)->filter()->values()->all(),
                'tujuan' => $spd->tempat_tujuan,
                'tanggal_mulai' => $this->tanggal($spd->tanggal_berangkat),
                'tanggal_selesai' => $this->tanggal($spd->tanggal_kembali),
                'maksud' => $spd->maksud,
                'rincian' => array_filter([
                    // Tiap pelaksana memegang nomor suratnya sendiri.
                    'Nomor lain' => $nomor->count() > 1 ? $nomor->slice(1)->implode(', ') : null,
                    'Pengikut' => $spd->pengikut->map(fn (SpdPengikut $ikut) => $ikut->nama)->filter()->implode(', ') ?: null,
                    'Alat angkut' => $spd->alat_angkut,
                    'Tanggal surat' => $spd->tanggal_surat?->translatedFormat('d M Y'),
                    'No. Surat Tugas' => $spd->no_tugas,
                    'Usulan terkait' => $spd->usulan?->no_usulan,
                    'Dibuat oleh' => $spd->pembuat?->nama,
                    'Dibuat' => $spd->created_at?->translatedFormat('d M Y, H:i'),
                ]),
            ],
        ]);
    }

    private function tanggal(mixed $nilai): ?string
    {
        return filled($nilai) ? Carbon::parse($nilai)->toDateString() : null;
    }

    /**
     * @param  array<int, ?string>  $bagian
     */
    private function gabung(array $bagian): ?string
    {
        return collect($bagian)->filter(fn (?string $teks) => filled($teks))->implode(' — ') ?: null;
    }
}
