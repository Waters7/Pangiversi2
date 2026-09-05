<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImporEksporPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $isi): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pegawai.csv', $isi);
    }

    private function header(): string
    {
        return "nama,nip,email,role,jabatan,unit_kode,atasan_nip,nama_bank,nomor_rekening,nama_rekening\n";
    }

    // ── Kewenangan ──

    public function test_tim_sdm_dan_super_administrator_dapat_mengekspor(): void
    {
        foreach ([PeranPengguna::TimSdm, PeranPengguna::SuperAdministrator] as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran->value]))
                ->get(route('administrasi.export'))
                ->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }
    }

    public function test_peran_lain_tidak_dapat_mengekspor_maupun_mengimpor(): void
    {
        foreach ([PeranPengguna::Ppk, PeranPengguna::Bendahara, PeranPengguna::DosenTendik] as $peran) {
            $pengguna = User::factory()->create(['role' => $peran->value]);

            $this->actingAs($pengguna)->get(route('administrasi.export'))->assertForbidden();
            $this->actingAs($pengguna)
                ->post(route('administrasi.import'), ['berkas' => $this->csv($this->header())])
                ->assertForbidden();
        }
    }

    // ── Ekspor ──

    public function test_ekspor_memuat_seluruh_kolom_pengguna(): void
    {
        $unit = UnitKerja::factory()->create(['kode' => 'ADUM']);

        User::factory()->create([
            'nama' => 'Sri Handayani',
            'nip' => 'NIP-1111111111',
            'role' => User::ROLE_BENDAHARA,
            'id_unit' => $unit->id,
            'nama_bank' => 'Bank SulutGo',
            'nomor_rekening' => '5550001111',
            'nama_rekening' => 'Sri Handayani',
        ]);

        $isi = $this->actingAs(User::factory()->administrator()->create())
            ->get(route('administrasi.export'))
            ->streamedContent();

        $this->assertStringContainsString('Sri Handayani', $isi);
        $this->assertStringContainsString('NIP-1111111111', $isi);
        $this->assertStringContainsString('ADUM', $isi);
        $this->assertStringContainsString('5550001111', $isi);
    }

    // ── Impor ──

    public function test_impor_membuat_pengguna_baru(): void
    {
        UnitKerja::factory()->create(['kode' => 'KEP']);

        $isi = $this->header()
            ."Budi Santoso,NIP-2222222222,budi@poltekkes.ac.id,dosen_tendik,Dosen,KEP,,Bank BRI,7770001111,Budi Santoso\n";

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('administrasi.import'), ['berkas' => $this->csv($isi)])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'nip' => 'NIP-2222222222',
            'nama' => 'Budi Santoso',
            'role' => User::ROLE_DOSEN_TENDIK,
            'nomor_rekening' => '7770001111',
        ]);
    }

    public function test_impor_memperbarui_pengguna_yang_nipnya_sudah_ada(): void
    {
        $lama = User::factory()->create([
            'nip' => 'NIP-3333333333',
            'nama' => 'Nama Lama',
            'jabatan' => 'Jabatan Lama',
        ]);

        $isi = $this->header()."Nama Baru,NIP-3333333333,,,Jabatan Baru,,,,,\n";

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('administrasi.import'), ['berkas' => $this->csv($isi)]);

        $lama->refresh();

        $this->assertSame('Nama Baru', $lama->nama);
        $this->assertSame('Jabatan Baru', $lama->jabatan);
        // Jumlah pengguna tidak bertambah karena NIP dipakai sebagai kunci.
        $this->assertSame(2, User::count());
    }

    public function test_impor_menautkan_atasan_berdasarkan_nip(): void
    {
        $atasan = User::factory()->create(['nip' => 'NIP-4444444444']);

        $isi = $this->header()."Staf Baru,NIP-5555555555,,,Dosen,,NIP-4444444444,,,\n";

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('administrasi.import'), ['berkas' => $this->csv($isi)]);

        $this->assertSame($atasan->id, User::firstWhere('nip', 'NIP-5555555555')->id_atasan);
    }

    public function test_baris_tanpa_nip_dilewati_dan_dilaporkan(): void
    {
        $isi = $this->header()."Tanpa NIP,,,,,,,,,\n";

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('administrasi.import'), ['berkas' => $this->csv($isi)])
            ->assertSessionHas('impor_dilewati');

        $this->assertDatabaseMissing('users', ['nama' => 'Tanpa NIP']);
    }

    public function test_peran_yang_tidak_dikenali_membuat_baris_dilewati(): void
    {
        $isi = $this->header()."Peran Aneh,NIP-6666666666,,penguasa_semesta,,,,,,\n";

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('administrasi.import'), ['berkas' => $this->csv($isi)])
            ->assertSessionHas('impor_dilewati');

        $this->assertDatabaseMissing('users', ['nip' => 'NIP-6666666666']);
    }

    public function test_header_toleran_terhadap_huruf_besar_dan_spasi(): void
    {
        $isi = "Nama, NIP ,Email,Role,Jabatan,Unit Kode,Atasan NIP,Nama Bank,Nomor Rekening,Nama Rekening\n"
            ."Rina Wulandari,NIP-7777777777,,,Instruktur,,,,,\n";

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('administrasi.import'), ['berkas' => $this->csv($isi)]);

        $this->assertDatabaseHas('users', ['nip' => 'NIP-7777777777', 'nama' => 'Rina Wulandari']);
    }

    public function test_berkas_selain_csv_ditolak(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('administrasi.import'), [
                'berkas' => UploadedFile::fake()->create('pegawai.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('berkas');
    }

    public function test_impor_tercatat_di_jejak_audit(): void
    {
        $admin = User::factory()->administrator()->create();
        $isi = $this->header()."Agus Salim,NIP-8888888888,,,Dosen,,,,,\n";

        $this->actingAs($admin)->post(route('administrasi.import'), ['berkas' => $this->csv($isi)]);

        $this->assertDatabaseHas('audit_logs', [
            'id_user' => $admin->id,
            'aksi' => 'pengguna',
        ]);
    }
}
