<?php

namespace Tests\Feature;

use App\Enums\ArahTiket;
use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Satu invoice tiket, bill hotel, atau invoice biaya penyelenggaraan kerap
 * mencakup seluruh rombongan. Yang mengisi belakangan cukup menyebut
 * nomornya: berkasnya dipakai, sedangkan harga, nominal, dan boarding pass
 * tetap milik masing-masing.
 */
class BerkasBersamaTest extends TestCase
{
    use RefreshDatabase;

    private User $perintis;

    private User $rekan;

    private Usulan $usulanPerintis;

    private Usulan $usulanRekan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $disk = Storage::disk('public');
        foreach (['invoice', 'boarding', 'bill', 'kuitansi', 'bukti'] as $nama) {
            $disk->put("dokumen/asal/{$nama}.pdf", "%PDF {$nama} rombongan");
        }

        $this->perintis = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Rina Pongoh']);
        $this->rekan = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->usulanPerintis = Usulan::factory()->create(['id_user' => $this->perintis->id, 'status' => StatusUsulan::Disetujui->value]);
        $this->usulanRekan = Usulan::factory()->create(['id_user' => $this->rekan->id, 'status' => StatusUsulan::Disetujui->value]);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulanRekan->id]);

        $this->usulanPerintis->tiket()->create([
            'arah' => ArahTiket::Pergi->value, 'kota_asal' => 'Manado', 'kota_tujuan' => 'Medan',
            'nomor_tiket' => '291326261025', 'kode_booking' => 'OG44WM', 'harga' => 9_000_000,
            'boarding_pass' => 'dokumen/asal/boarding.pdf', 'invoice' => 'dokumen/asal/invoice.pdf',
        ]);
        Dokumen::create([
            'id_usulan' => $this->usulanPerintis->id,
            'bill_hotel' => 'dokumen/asal/bill.pdf', 'bill_hotel_no_transaksi' => 'HTL-77', 'bill_hotel_nominal' => 3_000_000,
            'kwintasi' => 'dokumen/asal/kuitansi.pdf',
            'penyelenggaraan_ada' => true, 'penyelenggaraan_nominal' => 1_500_000,
            'penyelenggaraan_invoice' => '019/KW/MUNAS-IKAMAGI', 'penyelenggaraan_bukti' => 'dokumen/asal/bukti.pdf',
        ]);
    }

    private function cek(string $jenis, string $nomor, ?User $oleh = null, ?Usulan $usulan = null): TestResponse
    {
        return $this->actingAs($oleh ?? $this->rekan)->getJson(route('berkas-bersama.cek', [
            'jenis' => $jenis, 'nomor' => $nomor, 'usulan' => ($usulan ?? $this->usulanRekan)->no_usulan,
        ]));
    }

    private function simpan(array $isian): TestResponse
    {
        return $this->actingAs($this->rekan)->post(route('dokumen.store', $this->usulanRekan), $isian);
    }

    private function isiBerkas(?string $path): ?string
    {
        return $path ? Storage::disk('public')->get($path) : null;
    }

    // ── Tombol Cek ──

    public function test_cek_tiket_memberikan_invoice_dan_rute_tanpa_harga(): void
    {
        $jawaban = $this->cek('tiket', ' 2913 26261025 ')
            ->assertOk()
            ->assertJson([
                'ditemukan' => true,
                'data' => ['kota_asal' => 'Manado', 'kota_tujuan' => 'Medan', 'kode_booking' => 'OG44WM'],
                'berkas' => [['label' => 'Invoice tiket', 'tautan' => route('berkas.lihat', 'dokumen/asal/invoice.pdf')]],
            ])
            ->json();

        // Harga dibagi per orang dan boarding pass milik masing-masing.
        $this->assertArrayNotHasKey('harga', $jawaban['data']);
        $this->assertStringNotContainsString('boarding.pdf', json_encode($jawaban));
        $this->assertStringContainsString('Rina Pongoh', $jawaban['pesan']);

        $this->cek('tiket', '999')->assertOk()->assertJson(['ditemukan' => false]);
    }

    /** Usulan yang sedang diisi tidak menemukan berkasnya sendiri. */
    public function test_cek_mengabaikan_berkas_usulan_sendiri(): void
    {
        $this->cek('tiket', '291326261025', $this->perintis, $this->usulanPerintis)->assertJson(['ditemukan' => false]);
        $this->cek('hotel', 'HTL-77', $this->perintis, $this->usulanPerintis)->assertJson(['ditemukan' => false]);
    }

    public function test_cek_bill_hotel_dan_invoice_penyelenggaraan(): void
    {
        $this->cek('hotel', 'htl-77')->assertOk()->assertJson([
            'ditemukan' => true,
            'berkas' => [
                ['label' => 'Bill hotel', 'tautan' => route('berkas.lihat', 'dokumen/asal/bill.pdf')],
                ['label' => 'Kuitansi penyelenggara / hotel', 'tautan' => route('berkas.lihat', 'dokumen/asal/kuitansi.pdf')],
            ],
        ]);

        $this->cek('penyelenggaraan', '019/KW/MUNAS-IKAMAGI')->assertOk()->assertJson([
            'ditemukan' => true,
            'berkas' => [['label' => 'Bukti bayar biaya penyelenggaraan', 'tautan' => route('berkas.lihat', 'dokumen/asal/bukti.pdf')]],
        ]);

        $this->cek('penyelenggaraan', 'INV-BARU')->assertJson(['ditemukan' => false]);
    }

    // ── Menyimpan ──

    public function test_tiket_bernomor_sama_memakai_invoice_rekan_tetapi_harga_dan_boarding_pass_sendiri(): void
    {
        $this->simpan([
            'section' => 'tiket', 'arah' => ArahTiket::Pergi->value,
            'kota_asal' => 'Manado', 'kota_tujuan' => 'Medan', 'nomor_tiket' => '291326261025', 'kode_booking' => 'OG44WM',
            'harga' => 4_500_000,
            'boarding_pass' => UploadedFile::fake()->create('boarding-saya.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $tiket = $this->usulanRekan->tiket()->sole();

        $this->assertNotSame('dokumen/asal/invoice.pdf', $tiket->invoice);
        $this->assertSame('%PDF invoice rombongan', $this->isiBerkas($tiket->invoice));
        $this->assertNotSame('%PDF boarding rombongan', $this->isiBerkas($tiket->boarding_pass));
        $this->assertSame(4_500_000.0, $tiket->harga);
    }

    public function test_nomor_tiket_baru_tetap_wajib_invoice(): void
    {
        $this->simpan([
            'section' => 'tiket', 'arah' => ArahTiket::Pergi->value,
            'kota_asal' => 'Manado', 'kota_tujuan' => 'Medan', 'nomor_tiket' => '999', 'kode_booking' => 'ZZZ999',
            'harga' => 4_500_000,
            'boarding_pass' => UploadedFile::fake()->create('boarding-saya.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('invoice');
    }

    public function test_nomor_transaksi_hotel_sama_memakai_bill_dan_kuitansi_rekan_dengan_nominal_sendiri(): void
    {
        $this->simpan(['section' => 'akomodasi', 'bill_hotel_no_transaksi' => 'HTL-77', 'bill_hotel_nominal' => 1_200_000])
            ->assertSessionHasNoErrors();

        $dokumen = $this->usulanRekan->dokumen()->latest('id')->first();

        $this->assertSame('%PDF bill rombongan', $this->isiBerkas($dokumen->bill_hotel));
        $this->assertSame('%PDF kuitansi rombongan', $this->isiBerkas($dokumen->kwintasi));
        $this->assertNotSame('dokumen/asal/bill.pdf', $dokumen->bill_hotel);
        $this->assertSame(1_200_000.0, $dokumen->bill_hotel_nominal);

        $this->simpan(['section' => 'akomodasi', 'bill_hotel_no_transaksi' => 'HTL-BARU'])
            ->assertSessionHasNoErrors();
    }

    public function test_nomor_transaksi_baru_tetap_wajib_unggah(): void
    {
        $this->simpan(['section' => 'akomodasi', 'bill_hotel_no_transaksi' => 'HTL-BARU', 'bill_hotel_nominal' => 1_200_000])
            ->assertSessionHasErrors(['bill_hotel', 'kwintasi']);
    }

    public function test_invoice_penyelenggaraan_sama_memakai_bukti_bayar_rekan(): void
    {
        $isian = [
            'section' => 'penyelenggaraan', 'penyelenggaraan_ada' => '1', 'penyelenggaraan_nominal' => 750_000,
        ];

        $this->simpan($isian + ['penyelenggaraan_invoice' => 'INV-BARU'])->assertSessionHasErrors('penyelenggaraan_bukti');

        $this->simpan($isian + ['penyelenggaraan_invoice' => '019/kw/munas-ikamagi'])->assertSessionHasNoErrors();

        $dokumen = $this->usulanRekan->dokumen()->latest('id')->first();
        $this->assertSame('%PDF bukti rombongan', $this->isiBerkas($dokumen->penyelenggaraan_bukti));
        $this->assertSame(750_000.0, (float) $dokumen->penyelenggaraan_nominal);
    }

    public function test_formulir_dokumen_menampilkan_tombol_cek(): void
    {
        $this->actingAs($this->rekan)
            ->get(route('dokumen.show', $this->usulanRekan->no_usulan))
            ->assertOk()
            ->assertSee('Cek Nomor Tiket')
            ->assertSee('Cek Nomor Transaksi')
            ->assertSee('Cek Nomor Invoice');
    }
}
