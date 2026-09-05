<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengajuan berkelompok hanya alat bantu input: tiap peserta menerima usulan
 * bernomor sendiri karena pertanggungjawabannya perorangan.
 */
class PengajuanKelompokTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // PPK tetap ada supaya terbukti ia memang tidak lagi ditagih keputusan.
        User::factory()->ppk()->create();

        $this->pengusul = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'nama' => 'Ketua Rombongan',
        ]);

        // Usulan perjadin baru terbuka setelah SPD terbit.
        $this->terbitkanSpd($this->pengusul);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    private function dataUsulan(array $ubahan = []): array
    {
        return array_merge([
            'id_spd' => $this->spdMilik($this->pengusul)->id,
            'id_kegiatan' => Kegiatan::factory()->create()->id,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create()->id,
            'no_tugas' => 'TGS-2026-010',
            'lokasi' => 'Manado',
            'instansi' => 'Dinas Kesehatan',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-03',
            'jenis_pengajuan' => 'personal',
            'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf'),
        ], $ubahan);
    }

    // ── Pengajuan personal ──

    public function test_pengajuan_personal_membuat_satu_usulan(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan());

        $this->assertSame(1, Usulan::count());

        $usulan = Usulan::first();

        $this->assertSame(Usulan::PENGAJUAN_PERSONAL, $usulan->jenis_pengajuan);
        $this->assertNull($usulan->kode_rombongan);
        $this->assertFalse($usulan->dibuatkanOrangLain());
        $this->assertTrue($usulan->sudahDikonfirmasi());
    }

    // ── Pengajuan kelompok ──

    public function test_pengajuan_kelompok_membuat_usulan_terpisah_untuk_tiap_peserta(): void
    {
        $rekanA = User::factory()->create(['nama' => 'Rekan Satu']);
        $rekanB = User::factory()->create(['nama' => 'Rekan Dua']);

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekanA->id, $rekanB->id],
        ]));

        // Tiga orang, tiga usulan, tiga pemilik berbeda.
        $this->assertSame(3, Usulan::count());
        $this->assertSame(
            [$this->pengusul->id, $rekanA->id, $rekanB->id],
            Usulan::orderBy('id')->pluck('id_user')->all()
        );
    }

    public function test_setiap_peserta_mendapat_nomor_pengajuan_sendiri(): void
    {
        $rekan = User::factory()->create();

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekan->id],
        ]));

        $nomor = Usulan::pluck('no_usulan');

        $this->assertCount(2, $nomor);
        $this->assertSame(2, $nomor->unique()->count(), 'Nomor pengajuan harus unik per peserta.');
    }

    public function test_usulan_serombongan_ditandai_dengan_kode_yang_sama(): void
    {
        $rekan = User::factory()->create();

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekan->id],
        ]));

        $kode = Usulan::pluck('kode_rombongan');

        $this->assertSame(1, $kode->unique()->count());
        $this->assertNotNull($kode->first());

        // Tiap usulan dapat menelusuri rekan serombongannya.
        $milikPengusul = Usulan::firstWhere('id_user', $this->pengusul->id);
        $this->assertSame(1, $milikPengusul->serombongan()->count());
    }

    public function test_setiap_usulan_punya_keuangan_dan_pertanggungjawaban_sendiri(): void
    {
        $rekan = User::factory()->create();

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekan->id],
        ]));

        // Satu peserta (pemiliknya sendiri) dan satu berkas dokumen per usulan.
        Usulan::all()->each(function (Usulan $usulan): void {
            $this->assertSame(1, $usulan->peserta()->count());
            $this->assertSame($usulan->id_user, $usulan->peserta()->first()->id_user);
            $this->assertSame(1, $usulan->dokumen()->count());
        });
    }

    public function test_usulan_yang_dibuatkan_menunggu_konfirmasi_pemiliknya(): void
    {
        $rekan = User::factory()->create(['nama' => 'Rekan Satu']);

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekan->id],
        ]));

        $milikRekan = Usulan::firstWhere('id_user', $rekan->id);
        $milikPengusul = Usulan::firstWhere('id_user', $this->pengusul->id);

        $this->assertTrue($milikRekan->menungguKonfirmasi());
        $this->assertSame($this->pengusul->id, $milikRekan->id_pembuat);

        // Usulan pengusul sendiri tidak perlu dikonfirmasi.
        $this->assertTrue($milikPengusul->sudahDikonfirmasi());

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $rekan->id,
            'judul' => 'Usulan perjalanan dinas dibuatkan untuk Anda',
        ]);
    }

    public function test_pengajuan_kelompok_tanpa_anggota_ditolak(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan([
                'jenis_pengajuan' => 'kelompok',
                'anggota' => [],
            ]))
            ->assertSessionHasErrors('anggota');

        $this->assertSame(0, Usulan::count());
    }

    public function test_pegawai_yang_sama_tidak_boleh_dipilih_dua_kali(): void
    {
        $rekan = User::factory()->create();

        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan([
                'jenis_pengajuan' => 'kelompok',
                'anggota' => [$rekan->id, $rekan->id],
            ]))
            ->assertSessionHasErrors('anggota.1');
    }

    public function test_daftar_usulan_tidak_membocorkan_usulan_pegawai_lain(): void
    {
        $rekan = User::factory()->create();

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekan->id],
        ]));

        $orangLain = User::factory()->create();
        $usulanOrangLain = Usulan::factory()->create(['id_user' => $orangLain->id]);

        $this->actingAs($rekan)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertDontSee($usulanOrangLain->no_usulan)
            ->assertViewHas('usulan', fn ($daftar) => $daftar->total() === 1);
    }

    // ── Konfirmasi pemilik ──

    public function test_pemilik_dapat_mengonfirmasi_kesediaannya(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        $this->actingAs($rekan)
            ->put(route('usulan.konfirmasi', $usulan))
            ->assertSessionHas('success');

        $this->assertTrue($usulan->fresh()->sudahDikonfirmasi());
        $this->assertNotNull($usulan->fresh()->dikonfirmasi_at);
    }

    public function test_pembatalan_menutup_usulan_dengan_alasan(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        $this->actingAs($rekan)
            ->put(route('usulan.batal-konfirmasi', $usulan), [
                'alasan_batal' => 'Bentrok dengan jadwal mengajar',
            ])
            ->assertSessionHas('success');

        $usulan->refresh();

        $this->assertSame(Usulan::KONFIRMASI_DIBATALKAN, $usulan->konfirmasi);
        $this->assertSame('Bentrok dengan jadwal mengajar', $usulan->alasan_batal);
        // Usulannya berhenti di situ.
        $this->assertSame(StatusUsulan::Ditolak->value, $usulan->status);
    }

    public function test_pembatalan_tidak_mengganggu_usulan_rekan_serombongan(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        $this->actingAs($rekan)->put(route('usulan.batal-konfirmasi', $usulan));

        $milikPengusul = Usulan::firstWhere('id_user', $this->pengusul->id);

        $this->assertSame(StatusUsulan::Disetujui->value, $milikPengusul->fresh()->status);
    }

    public function test_pembuat_diberi_tahu_saat_pemilik_mengundurkan_diri(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        $this->actingAs($rekan)->put(route('usulan.batal-konfirmasi', $usulan));

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pengusul->id,
            'judul' => 'Peserta mengundurkan diri',
        ]);
    }

    public function test_orang_lain_tidak_dapat_mengubah_kesediaan_pemilik(): void
    {
        [$usulan] = $this->usulanDibuatkan();

        $this->actingAs(User::factory()->create())
            ->put(route('usulan.konfirmasi', $usulan))
            ->assertForbidden();
    }

    public function test_kesediaan_terkunci_setelah_ppk_memvalidasi(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        $usulan->update(['status' => StatusUsulan::Disetujui->value]);

        $this->actingAs($rekan)
            ->put(route('usulan.konfirmasi', $usulan))
            ->assertForbidden();
    }

    public function test_usulan_yang_dibuat_sendiri_tidak_meminta_konfirmasi(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan());

        $usulan = Usulan::first();

        $this->assertFalse($usulan->konfirmasiMasihTerbuka());

        $this->actingAs($this->pengusul)
            ->put(route('usulan.konfirmasi', $usulan))
            ->assertForbidden();
    }

    // ── Mengirim draf dari halaman detail ──

    public function test_pemilik_dapat_mengirim_drafnya(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::Draft->value,
        ]);

        $this->actingAs($this->pengusul)
            ->put(route('usulan.ajukan', $usulan))
            ->assertSessionHas('success');

        $this->assertSame(StatusUsulan::Disetujui->value, $usulan->fresh()->status);
    }

    public function test_draf_orang_lain_tidak_dapat_dikirim(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => User::factory()->create()->id,
            'status' => StatusUsulan::Draft->value,
        ]);

        $this->actingAs($this->pengusul)
            ->put(route('usulan.ajukan', $usulan))
            ->assertForbidden();
    }

    public function test_usulan_yang_sudah_berlaku_tidak_dapat_dikirim_ulang(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->actingAs($this->pengusul)
            ->put(route('usulan.ajukan', $usulan))
            ->assertForbidden();
    }

    public function test_usulan_yang_belum_dikonfirmasi_ditolak_pengirimannya(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        $this->actingAs($rekan)
            ->put(route('usulan.ajukan', $usulan))
            ->assertSessionHas('error');

        $this->assertSame(StatusUsulan::Draft->value, $usulan->fresh()->status);
    }

    public function test_tombol_kirim_verifikasi_ppk_tampil_pada_draf_sendiri(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::Draft->value,
        ]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.show', $usulan))
            ->assertOk()
            ->assertSee('Ajukan Usulan Perjadin')
            ->assertSee(route('usulan.ajukan', $usulan), false);
    }

    // ── Konfirmasi sebagai gerbang menuju PPK ──

    public function test_usulan_yang_dibuatkan_tertahan_sebelum_dikonfirmasi(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        // Belum berlaku: pemiliknya harus menyatakan kesediaan lebih dulu.
        $this->assertSame(StatusUsulan::Draft->value, $usulan->status);

        $this->assertSame(
            StatusUsulan::Disetujui->value,
            Usulan::firstWhere('id_user', $this->pengusul->id)->status
        );
    }

    public function test_konfirmasi_membuat_usulan_berlaku(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        $this->actingAs($rekan)->put(route('usulan.konfirmasi', $usulan));

        $this->assertSame(StatusUsulan::Disetujui->value, $usulan->fresh()->status);
    }

    public function test_pemilik_diberi_tahu_setelah_mengonfirmasi(): void
    {
        $ppk = User::firstWhere('role', User::ROLE_PPK);
        [$usulan, $rekan] = $this->usulanDibuatkan();

        // Rekan sudah punya notifikasi "dibuatkan untuk Anda", jadi yang
        // diperiksa adalah kabar bahwa usulannya kini berlaku.
        $this->assertDatabaseMissing('notifikasi', [
            'id_user' => $rekan->id,
            'judul' => 'Usulan perjalanan dinas Anda tercatat',
        ]);

        $this->actingAs($rekan)->put(route('usulan.konfirmasi', $usulan));

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $rekan->id,
            'id_usulan' => $usulan->id,
            'judul' => 'Usulan perjalanan dinas Anda tercatat',
        ]);

        // PPK tidak lagi ditagih keputusan: SPD yang mengesahkan penugasannya.
        $this->assertDatabaseMissing('notifikasi', ['id_user' => $ppk->id]);
    }

    public function test_daftar_usulan_menawarkan_aksi_konfirmasi_kepada_pemiliknya(): void
    {
        [$usulan, $rekan] = $this->usulanDibuatkan();

        $this->actingAs($rekan)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertSee('Konfirmasi')
            ->assertSee(route('usulan.konfirmasi', $usulan), false);
    }

    /**
     * @return array{0: Usulan, 1: User}
     */
    private function usulanDibuatkan(): array
    {
        $rekan = User::factory()->create(['nama' => 'Rekan Satu']);

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekan->id],
        ]));

        return [Usulan::firstWhere('id_user', $rekan->id), $rekan];
    }
}
