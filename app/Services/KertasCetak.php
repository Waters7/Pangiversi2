<?php

namespace App\Services;

/**
 * Ukuran kertas seluruh dokumen cetak PANGI.
 *
 * Satuan kerja mencetak di kertas folio (foolscap / F4, 8,5 × 13 inci ≈
 * 216 × 330 mm) — bukan A4. DomPDF mengenalnya sebagai "folio". Semua
 * pemanggil Pdf::loadView() memakai konstanta ini supaya ukurannya diganti
 * di satu tempat, bukan dicari satu per satu di tiap pengontrol.
 */
final class KertasCetak
{
    public const UKURAN = 'folio';

    /**
     * Ukuran yang boleh dipilih administrator pada pengaturan dokumen.
     *
     * @var array<string, string>
     */
    public const PILIHAN = [
        'folio' => 'Folio / F4 — 21,6 × 33 cm',
        'a4' => 'A4 — 21 × 29,7 cm',
        'legal' => 'Legal — 21,6 × 35,6 cm',
        'letter' => 'Letter — 21,6 × 27,9 cm',
    ];

    public const TEGAK = 'portrait';

    public const MENDATAR = 'landscape';
}
