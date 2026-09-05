<?php

namespace App\Services;

use App\Enums\ArahTiket;
use App\Enums\RuasTransport;
use App\Models\Dokumen;
use App\Models\Usulan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Menyimpan berkas dan rincian pertanggungjawaban per seksi formulir.
 */
class DokService
{
    public function __construct(private SinkronBiayaDokumen $sinkron) {}

    /**
     * @return bool Berhasil menyimpan sesuatu.
     */
    public function store(Request $request, Usulan $usulan, string $seksi): bool
    {
        return match ($seksi) {
            'penugasan' => $this->simpanPenugasan($request, $usulan),
            'tiket' => $this->simpanTiket($request, $usulan),
            'nota' => $this->simpanNota($request, $usulan),
            'akomodasi' => $this->simpanAkomodasi($request, $usulan),
            default => abort(422, 'Tipe dokumen tidak dikenali.'),
        };
    }

    // ── Seksi 1: penugasan ──

    private function simpanPenugasan(Request $request, Usulan $usulan): bool
    {
        $dokumen = $this->dokumen($usulan);

        $request->validate([
            'surat_tugas' => [$this->aturanBerkas($dokumen, 'surat_tugas'), 'file', 'mimes:pdf', 'max:2048'],
            'sppd' => [$this->aturanBerkas($dokumen, 'sppd'), 'file', 'mimes:pdf', 'max:2048'],
        ], [
            'sppd.required' => 'Unggah SPPD yang sudah ditandatangani lengkap.',
        ]);

        return $this->simpanBerkas($request, $usulan, ['surat_tugas', 'sppd']);
    }

    // ── Seksi 2: tiket pergi dan pulang ──

    private function simpanTiket(Request $request, Usulan $usulan): bool
    {
        $arah = ArahTiket::tryFrom((string) $request->input('arah'));

        abort_unless($arah !== null, 422, 'Arah tiket tidak dikenali.');

        $tiket = $usulan->tiket()->firstOrNew(['arah' => $arah->value]);

        $data = $request->validate([
            'kota_asal' => ['required', 'string', 'max:100'],
            'kota_tujuan' => ['required', 'string', 'max:100'],
            'nomor_tiket' => ['required', 'string', 'max:100'],
            'kode_booking' => ['required', 'string', 'max:50'],
            // Harga yang dicatat adalah yang tertera pada tiket, sudah termasuk
            // pajak — bukan tarif dasar, supaya cocok dengan bukti bayarnya.
            'harga' => ['required', 'numeric', 'min:0'],
            'boarding_pass' => [
                $tiket->boarding_pass ? 'nullable' : 'required',
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048',
            ],
        ], [
            'harga.required' => 'Isi harga tiket yang sudah termasuk pajak.',
            'boarding_pass.required' => 'Unggah boarding pass untuk tiket ini.',
        ]);

        if ($request->hasFile('boarding_pass')) {
            $this->hapusBerkasLama($tiket->boarding_pass);
            $data['boarding_pass'] = $request->file('boarding_pass')->store('dokumen/boarding-pass', 'public');
        } else {
            unset($data['boarding_pass']);
        }

        $tiket->fill($data + ['arah' => $arah->value]);
        $tiket->id_usulan = $usulan->id;
        $tiket->save();

        $this->rampungkan($usulan);

        return true;
    }

    // ── Seksi 3: nota transportasi lokal ──

    private function simpanNota(Request $request, Usulan $usulan): bool
    {
        $request->validate([
            'ruas' => ['required', 'array'],
            'ruas.*.nominal' => ['nullable', 'numeric', 'min:0'],
            'ruas.*.keterangan' => ['nullable', 'string', 'max:255'],
            'ruas.*.bukti' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $tersimpan = $usulan->notaTransport->keyBy('urutan');

        foreach (RuasTransport::urutan() as $ruas) {
            $masukan = $request->input("ruas.{$ruas->value}", []);
            $nota = $tersimpan->get($ruas->value) ?? $usulan->notaTransport()->make(['urutan' => $ruas->value]);

            $nota->nominal = $masukan['nominal'] !== null && $masukan['nominal'] !== ''
                ? (float) $masukan['nominal']
                : null;
            $nota->keterangan = $masukan['keterangan'] ?? null;

            if ($request->hasFile("ruas.{$ruas->value}.bukti")) {
                $this->hapusBerkasLama($nota->bukti);
                $nota->bukti = $request->file("ruas.{$ruas->value}.bukti")
                    ->store('dokumen/nota-transport', 'public');
            }

            $nota->id_usulan = $usulan->id;
            $nota->save();
        }

        $this->rampungkan($usulan);

        return true;
    }

    // ── Seksi 4: akomodasi dan bukti biaya ──

    private function simpanAkomodasi(Request $request, Usulan $usulan): bool
    {
        $dokumen = $this->dokumen($usulan);

        $request->validate([
            'bill_hotel' => [$this->aturanBerkas($dokumen, 'bill_hotel'), 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'bill_hotel_no_transaksi' => ['nullable', 'string', 'max:100'],
            'bill_hotel_nominal' => ['nullable', 'numeric', 'min:0'],
            'kwintasi' => [$this->aturanBerkas($dokumen, 'kwintasi'), 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'faktur' => [$this->aturanBerkas($dokumen, 'faktur'), 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $this->simpanBerkas($request, $usulan, ['bill_hotel', 'kwintasi', 'faktur'], [
            'bill_hotel_no_transaksi' => $request->input('bill_hotel_no_transaksi') ?: null,
            'bill_hotel_nominal' => $request->filled('bill_hotel_nominal')
                ? (float) $request->input('bill_hotel_nominal')
                : null,
        ]);

        return true;
    }

    // ── Pembantu ──

    private function dokumen(Usulan $usulan): ?Dokumen
    {
        return Dokumen::where('id_usulan', $usulan->id)->latest('id')->first();
    }

    /**
     * Berkas yang sudah pernah diunggah cukup dibiarkan; yang belum ada wajib.
     */
    private function aturanBerkas(?Dokumen $dokumen, string $kolom): string
    {
        return filled($dokumen?->{$kolom}) ? 'nullable' : 'required';
    }

    /**
     * @param  list<string>  $kolom
     * @param  array<string, mixed>  $tambahan
     */
    private function simpanBerkas(Request $request, Usulan $usulan, array $kolom, array $tambahan = []): bool
    {
        $dokumen = $this->dokumen($usulan);
        $data = $tambahan;

        foreach ($kolom as $satu) {
            if ($request->hasFile($satu)) {
                $this->hapusBerkasLama($dokumen?->{$satu});
                $data[$satu] = $request->file($satu)->store('dokumen/'.$satu, 'public');
            }
        }

        if ($dokumen) {
            $dokumen->update($data);
        } else {
            Dokumen::create(['id_usulan' => $usulan->id] + $data);
        }

        $this->rampungkan($usulan);

        return true;
    }

    /**
     * Setelah seksi mana pun disimpan: nominalnya diselaraskan ke rincian
     * biaya, lalu kelengkapannya diperiksa ulang.
     */
    private function rampungkan(Usulan $usulan): void
    {
        $segar = $usulan->fresh(['tiket', 'notaTransport', 'dokumen', 'laporan', 'keuangan']);

        $this->sinkron->selaraskan($segar);
        $segar->checkCompletion();
    }

    private function hapusBerkasLama(?string $berkas): void
    {
        if (filled($berkas)) {
            Storage::disk('public')->delete($berkas);
        }
    }
}
