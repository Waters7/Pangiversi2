<?php

namespace App\Models;

use App\Services\FormatLaporanPerjadin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Laporan perjalanan dinas yang disusun langsung di aplikasi.
 */
class LaporanPerjadin extends Model
{
    protected $table = 'laporan_perjadin';

    protected $fillable = [
        'id_usulan',
        'dasar',
        'hasil',
        'id_status_hasil',
        'kesimpulan',
        'diselesaikan_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['diselesaikan_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * @return HasMany<LaporanKegiatan, $this>
     */
    public function kegiatan(): HasMany
    {
        return $this->hasMany(LaporanKegiatan::class, 'id_laporan')->orderBy('urutan');
    }

    /**
     * @return BelongsTo<StatusHasil, $this>
     */
    public function statusHasil(): BelongsTo
    {
        return $this->belongsTo(StatusHasil::class, 'id_status_hasil');
    }

    /**
     * @return HasMany<TindakLanjut, $this>
     */
    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjut::class, 'id_laporan')->orderBy('urutan');
    }

    /**
     * Laporan layak diselesaikan bila sudah memuat sedikitnya satu uraian
     * kegiatan, satu rencana tindak lanjut, dan status hasilnya dipilih.
     */
    public function layakDiselesaikan(): bool
    {
        return $this->kegiatan()->exists()
            && $this->tindakLanjut()->exists()
            && $this->id_status_hasil !== null;
    }

    /**
     * Dasar pelaksanaan, diturunkan dari surat tugas dan maksud perjalanan
     * yang sudah tercatat pada usulannya.
     *
     * Tidak lagi diminta ke pelaksana: keduanya sudah diisi di awal, dan
     * mengetiknya ulang hanya membuka peluang berbeda dari yang tercatat.
     */
    public function dasarPelaksanaan(): string
    {
        $usulan = $this->usulan;

        if (! $usulan) {
            return (string) $this->dasar;
        }

        $bagian = array_filter([
            filled($usulan->no_tugas) ? 'Surat Tugas Nomor '.$usulan->no_tugas : null,
            app(FormatLaporanPerjadin::class)->maksud($usulan),
        ]);

        return implode(' — ', $bagian);
    }

    /**
     * Laporan masih boleh disunting.
     *
     * Terkunci setelah perjalanannya ditutup: sejak itu berkasnya sudah
     * diperiksa tim keuangan dan dibayarkan, sehingga isinya tidak boleh
     * berubah lagi tanpa jejak.
     */
    public function bolehDisunting(): bool
    {
        return $this->usulan?->status !== 'selesai';
    }

    public function sudahSelesai(): bool
    {
        return $this->diselesaikan_at !== null;
    }
}
