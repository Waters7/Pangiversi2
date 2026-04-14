<?php

namespace App\Services;

use App\Models\Dokumen;
use Illuminate\Support\Facades\Storage;

class DokService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function store($request, $usulan, string $type): bool
    {
        $existing = Dokumen::where('id_usulan', $usulan->id)->latest('id')->first();

        $this->validate($request, $type, $existing);

        $fields = match ($type) {
            'penugasan' => ['surat_tugas', 'sppd'],
            'transportasi' => ['boarding_pass', 'faktur'],
            'akomodasi' => ['bill_hotel', 'kwintasi'],
            'laporan' => ['laporan_hasil'],
            default => null,
        };

        abort_unless($fields !== null, 422, 'Tipe dokumen tidak dikenali.');

        $data = [];
        foreach ($fields as $field) {
            if ($request->hasFile($field)) {
                $this->deleteOldFile($existing, $field);
                $data[$field] = $request->file($field)->store('dokumen/'.$field, 'public');
            } else {
                $data[$field] = $existing?->$field;
            }
        }

        abort_unless($data !== null, 422, 'Tipe dokumen tidak dikenali.');

        if ($existing) {
            $existing->update($data);
            $usulan->checkCompletion();

            return $existing->wasChanged();
        }

        Dokumen::create(['id_usulan' => $usulan->id, ...$data]);
        $usulan->checkCompletion();

        return true;
    }

    public function validate($request, $type, ?Dokumen $existing = null)
    {

        switch ($type) {
            case 'penugasan':
                $suratRule = ($existing && $existing->surat_tugas) ? 'nullable' : 'required';
                $sppdRule = ($existing && $existing->sppd) ? 'nullable' : 'required';
                $request->validate([
                    'surat_tugas' => [$suratRule, 'file', 'mimes:pdf', 'max:2048'],
                    'sppd' => [$sppdRule, 'file', 'mimes:pdf', 'max:2048'],
                ]);
                break;

            case 'transportasi':
                $request->validate([
                    'boarding_pass' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                    'faktur' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                ]);
                break;

            case 'akomodasi':
                $request->validate([
                    'bill_hotel' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                    'kwintasi' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                ]);
                break;

            case 'laporan':
                $laporanRule = ($existing && $existing->laporan_hasil) ? 'nullable' : 'required';
                $request->validate([
                    'laporan_hasil' => [$laporanRule, 'file', 'mimes:pdf', 'max:5120'],
                ]);
                break;

            default:
                abort(422, 'Tipe dokumen tidak dikenali.');
                break;
        }

    }

    /**
     * Hapus file lama dari storage saat diganti file baru.
     */
    private function deleteOldFile(?Dokumen $existing, string $field): void
    {
        if ($existing && $existing->$field) {
            Storage::disk('public')->delete($existing->$field);
        }
    }
}
