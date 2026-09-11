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

    public const TEGAK = 'portrait';

    public const MENDATAR = 'landscape';
}
