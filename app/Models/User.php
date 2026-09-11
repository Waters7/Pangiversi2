<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'nama', 'email', 'no_hp', 'foto', 'nip', 'password', 'role', 'jabatan', 'golongan', 'id_unit', 'id_atasan',
    'nama_bank', 'nomor_rekening', 'nama_rekening',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_administrator';

    public const ROLE_PIMPINAN = 'pimpinan';

    public const ROLE_PPK = 'ppk';

    public const ROLE_BENDAHARA = 'bendahara';

    public const ROLE_TIM_KEUANGAN = 'tim_keuangan';

    public const ROLE_TIM_SDM = 'tim_sdm';

    public const ROLE_DOSEN_TENDIK = 'dosen_tendik';

    public const ROLE_PEGAWAI_EKSTERNAL = 'pegawai_eksternal';

    public const ROLE_OUTSOURCING = 'outsourcing';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'login_terakhir_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function usulan()
    {
        return $this->hasMany(Usulan::class, 'id_user');
    }

    /**
     * @return BelongsTo<UnitKerja, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'id_unit');
    }

    /**
     * Atasan langsung yang menjadi approver level pertama bagi pengguna ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function atasan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_atasan');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function bawahan(): HasMany
    {
        return $this->hasMany(User::class, 'id_atasan');
    }

    /**
     * @return HasMany<PesertaUsulan, $this>
     */
    public function keikutsertaan(): HasMany
    {
        return $this->hasMany(PesertaUsulan::class, 'id_user');
    }

    /**
     * Notifikasi in-app milik pengguna ini.
     *
     * @return HasMany<Notifikasi, $this>
     */
    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'id_user')->latest();
    }

    /**
     * Jejak audit dari tindakan yang dilakukan pengguna ini.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_user');
    }

    /**
     * Peran pengguna sebagai enum — sumber tunggal untuk label dan hak akses.
     */
    public function getPeranAttribute(): PeranPengguna
    {
        return PeranPengguna::dari($this->role);
    }

    /**
     * Apakah pengguna ini memiliki sebuah kemampuan.
     */
    public function punyaKemampuan(Kemampuan $kemampuan): bool
    {
        return $this->peran->punya($kemampuan);
    }

    public function isSuperAdmin(): bool
    {
        return $this->peran === PeranPengguna::SuperAdministrator;
    }

    /**
     * Dipertahankan sebagai alias agar pemeriksaan "kewenangan penuh" yang
     * tersebar di controller dan Blade tetap berjalan.
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Direktur Poltekkes Kemenkes Manado — bukan wakil direktur.
     *
     * Laporan perjalanan dinas hanya ditandatangani Direktur, jadi konfirmasi
     * laporan diperiksa terhadap jabatan ini, bukan sekadar peran pimpinan.
     */
    public function isDirektur(): bool
    {
        return $this->peran === PeranPengguna::Pimpinan
            && mb_strtolower(trim((string) $this->jabatan)) === 'direktur';
    }

    public function isPPK(): bool
    {
        return $this->peran === PeranPengguna::Ppk;
    }

    public function isPimpinan(): bool
    {
        return $this->peran === PeranPengguna::Pimpinan;
    }

    public function isBendahara(): bool
    {
        return $this->peran === PeranPengguna::Bendahara;
    }

    public function isTimKeuangan(): bool
    {
        return $this->peran === PeranPengguna::TimKeuangan;
    }

    public function isTimSDM(): bool
    {
        return $this->peran === PeranPengguna::TimSdm;
    }

    /**
     * Pengusul tanpa kewenangan tambahan apa pun.
     */
    public function isPegawai(): bool
    {
        return $this->peran->pengusulBiasa();
    }

    /**
     * Berwenang memberi keputusan atas usulan perjalanan dinas.
     */
    public function bisaMenyetujui(): bool
    {
        return $this->punyaKemampuan(Kemampuan::MemvalidasiUsulan);
    }

    /**
     * Boleh membuka halaman validasi — termasuk pimpinan yang hanya memantau.
     */
    public function bisaMembukaPersetujuan(): bool
    {
        return $this->bisaMenyetujui() || $this->bisaMelihatSemuaUsulan();
    }

    /**
     * Akses membuka modul keuangan (baca).
     */
    public function bisaAksesKeuangan(): bool
    {
        return $this->punyaKemampuan(Kemampuan::MelihatKeuangan);
    }

    /**
     * Akses menginput dan mengubah rincian biaya perjalanan.
     */
    public function bisaMengelolaBiaya(): bool
    {
        return $this->punyaKemampuan(Kemampuan::MengelolaBiaya);
    }

    /**
     * Menyatakan nominal biaya sudah diperiksa — tugas tim keuangan,
     * bukan bendahara yang membayarnya.
     */
    public function bisaMemvalidasiBiaya(): bool
    {
        return $this->punyaKemampuan(Kemampuan::MemvalidasiBiaya);
    }

    /**
     * Membuka modul keuangan tanpa satu pun kewenangan mengubahnya —
     * PPK dan pimpinan memantau rincian biaya, pembayaran, dan buktinya
     * tiap usulan, tetapi tidak menyusun angka, memvalidasi, maupun
     * mencatat pembayaran.
     */
    public function hanyaMelihatKeuangan(): bool
    {
        return $this->punyaKemampuan(Kemampuan::MelihatKeuangan)
            && ! $this->bisaMengelolaBiaya()
            && ! $this->bisaMemvalidasiBiaya()
            && ! $this->bisaMencatatPembayaran()
            && ! $this->isAdmin();
    }

    /**
     * Akses mencatat pembayaran beserta bukti transfernya.
     */
    public function bisaMencatatPembayaran(): bool
    {
        return $this->punyaKemampuan(Kemampuan::MencatatPembayaran);
    }

    public function bisaMelihatLaporan(): bool
    {
        return $this->punyaKemampuan(Kemampuan::MelihatLaporan);
    }

    public function bisaMelihatSemuaUsulan(): bool
    {
        return $this->punyaKemampuan(Kemampuan::MelihatSemuaUsulan);
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return PeranPengguna::options();
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->peran->label();
    }

    /**
     * Rekening bank sudah lengkap dan siap dipakai untuk transfer.
     */
    /**
     * Akun yang belum pernah dipakai masuk sama sekali.
     */
    public function belumPernahMasuk(): bool
    {
        return $this->login_terakhir_at === null;
    }

    public function punyaRekening(): bool
    {
        return filled($this->nama_bank)
            && filled($this->nomor_rekening)
            && filled($this->nama_rekening);
    }

    public function getRekeningRingkasAttribute(): ?string
    {
        return $this->punyaRekening()
            ? "{$this->nama_bank} · {$this->nomor_rekening} a.n. {$this->nama_rekening}"
            : null;
    }

    /**
     * Nomor WhatsApp dalam format internasional tanpa tanda baca,
     * sebagaimana diminta tautan wa.me — misalnya 081234 menjadi 6281234.
     */
    public function getNomorWhatsappAttribute(): ?string
    {
        $angka = preg_replace('/\D/', '', (string) $this->no_hp);

        if (blank($angka)) {
            return null;
        }

        return match (true) {
            str_starts_with($angka, '62') => $angka,
            str_starts_with($angka, '0') => '62'.mb_substr($angka, 1),
            default => '62'.$angka,
        };
    }

    public function punyaWhatsapp(): bool
    {
        return $this->nomor_whatsapp !== null;
    }

    /**
     * Alamat foto profil yang siap dipasang pada tag img.
     *
     * Mengembalikan null bila pengguna belum mengunggah foto atau berkasnya
     * sudah tidak ada, sehingga tampilan jatuh ke avatar inisial.
     */
    public function getUrlFotoAttribute(): ?string
    {
        if (blank($this->foto) || ! Storage::disk('public')->exists($this->foto)) {
            return null;
        }

        // asset() mengikuti host yang sedang dipakai, sedangkan Storage::url()
        // terkunci pada APP_URL — foto akan gagal dimuat bila aplikasi dibuka
        // lewat localhost padahal APP_URL berisi alamat IP jaringan lokal.
        return asset('storage/'.$this->foto);
    }

    public function punyaFoto(): bool
    {
        return $this->url_foto !== null;
    }
}
