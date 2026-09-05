<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Penulis berkas Excel (.xlsx) seperlunya, tanpa pustaka tambahan.
 *
 * Sebuah xlsx sebenarnya hanyalah arsip zip berisi beberapa berkas XML, jadi
 * laporan bergaya — judul yang digabung, kepala tabel bertingkat, angka
 * berformat ribuan — dapat dihasilkan langsung dari PHP. Cakupannya sengaja
 * dibatasi pada yang dipakai laporan PANGI: teks, angka, penggabungan sel,
 * lebar kolom, dan beberapa gaya baku.
 *
 * @phpstan-type Sel array{isi: mixed, gaya: string, angka: bool}
 */
class PenulisXlsx
{
    /** Gaya yang tersedia, dipetakan ke indeks cellXfs pada styles.xml. */
    private const GAYA = [
        'polos' => 0,
        'judul' => 1,
        'subjudul' => 2,
        'kepala' => 3,
        'teks' => 4,
        'teks-tengah' => 5,
        'angka' => 6,
        'total' => 7,
        'total-teks' => 8,
        'tebal' => 9,
        'tanggal' => 10,
    ];

    /** @var list<list<array<string, mixed>>> */
    private array $baris = [];

    /** @var list<string> */
    private array $gabungan = [];

    /** @var array<int, float> */
    private array $lebarKolom = [];

    /** @var array<int, float> */
    private array $tinggiBaris = [];

    private int $bekuBaris = 0;

    public function __construct(private string $namaSheet = 'Sheet1') {}

    /**
     * Tambahkan satu baris.
     *
     * Tiap sel boleh berupa nilai biasa, atau array {isi, gaya, angka}
     * untuk mengatur gayanya.
     *
     * @param  list<mixed>  $sel
     */
    public function baris(array $sel, string $gayaBaku = 'polos'): static
    {
        $this->baris[] = array_map(
            fn (mixed $isi) => $this->normalkan($isi, $gayaBaku),
            $sel
        );

        return $this;
    }

    public function barisKosong(int $jumlah = 1): static
    {
        for ($i = 0; $i < $jumlah; $i++) {
            $this->baris[] = [];
        }

        return $this;
    }

    /**
     * @param  array<int, float>  $lebar  Indeks kolom mulai 0 => lebar karakter.
     */
    public function lebarKolom(array $lebar): static
    {
        $this->lebarKolom = $lebar;

        return $this;
    }

    public function tinggiBaris(int $nomorBaris, float $tinggi): static
    {
        $this->tinggiBaris[$nomorBaris] = $tinggi;

        return $this;
    }

    /**
     * @param  string  $rentang  Misalnya "A1:O1".
     */
    public function gabung(string $rentang): static
    {
        $this->gabungan[] = $rentang;

        return $this;
    }

    /**
     * Bekukan sejumlah baris teratas agar kepala tabel tetap terlihat.
     */
    public function bekukan(int $baris): static
    {
        $this->bekuBaris = $baris;

        return $this;
    }

    public function jumlahBaris(): int
    {
        return count($this->baris);
    }

    /**
     * Susun berkas xlsx dan kembalikan isinya sebagai string biner.
     */
    public function keString(): string
    {
        $sementara = tempnam(sys_get_temp_dir(), 'pangi-xlsx-');

        if ($sementara === false) {
            throw new RuntimeException('Tidak dapat membuat berkas sementara untuk Excel.');
        }

        $zip = new ZipArchive;

        if ($zip->open($sementara, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat menyusun arsip Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relsUtama());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relsWorkbook());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());
        $zip->close();

        $isi = file_get_contents($sementara);
        unlink($sementara);

        if ($isi === false) {
            throw new RuntimeException('Gagal membaca kembali berkas Excel yang disusun.');
        }

        return $isi;
    }

    /**
     * Ubah indeks kolom (mulai 0) menjadi hurufnya: 0 => A, 26 => AA.
     */
    public static function huruf(int $indeks): string
    {
        $huruf = '';

        do {
            $huruf = chr(65 + ($indeks % 26)).$huruf;
            $indeks = intdiv($indeks, 26) - 1;
        } while ($indeks >= 0);

        return $huruf;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalkan(mixed $isi, string $gayaBaku): array
    {
        if (is_array($isi)) {
            $nilai = $isi['isi'] ?? '';
            $gaya = $isi['gaya'] ?? $gayaBaku;
            $angka = $isi['angka'] ?? is_numeric($nilai);
        } else {
            $nilai = $isi;
            $gaya = $gayaBaku;
            $angka = false;
        }

        return [
            'isi' => $nilai,
            'gaya' => self::GAYA[$gaya] ?? 0,
            'angka' => (bool) $angka && is_numeric($nilai),
        ];
    }

    private function sheet(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        if ($this->bekuBaris > 0) {
            $xml .= '<pane ySplit="'.$this->bekuBaris.'" topLeftCell="A'.($this->bekuBaris + 1).'"'
                .' activePane="bottomLeft" state="frozen"/>';
        }
        $xml .= '</sheetView></sheetViews>';

        if ($this->lebarKolom !== []) {
            $xml .= '<cols>';
            foreach ($this->lebarKolom as $indeks => $lebar) {
                $nomor = $indeks + 1;
                $xml .= '<col min="'.$nomor.'" max="'.$nomor.'" width="'.$lebar.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        foreach ($this->baris as $indeksBaris => $sel) {
            $nomorBaris = $indeksBaris + 1;
            $tinggi = isset($this->tinggiBaris[$nomorBaris])
                ? ' ht="'.$this->tinggiBaris[$nomorBaris].'" customHeight="1"'
                : '';

            $xml .= '<row r="'.$nomorBaris.'"'.$tinggi.'>';

            foreach ($sel as $indeksKolom => $isi) {
                $xml .= $this->sel(self::huruf($indeksKolom).$nomorBaris, $isi);
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        if ($this->gabungan !== []) {
            $xml .= '<mergeCells count="'.count($this->gabungan).'">';
            foreach ($this->gabungan as $rentang) {
                $xml .= '<mergeCell ref="'.$rentang.'"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
        $xml .= '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>';

        return $xml.'</worksheet>';
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    private function sel(string $referensi, array $isi): string
    {
        $gaya = ' s="'.$isi['gaya'].'"';
        $nilai = $isi['isi'];

        if ($nilai === null || $nilai === '') {
            return '<c r="'.$referensi.'"'.$gaya.'/>';
        }

        if ($isi['angka']) {
            return '<c r="'.$referensi.'"'.$gaya.'><v>'.(0 + $nilai).'</v></c>';
        }

        return '<c r="'.$referensi.'"'.$gaya.' t="inlineStr"><is><t xml:space="preserve">'
            .$this->aman((string) $nilai)
            .'</t></is></c>';
    }

    /**
     * Buang karakter kendali yang membuat Excel menolak membuka berkasnya.
     */
    private function aman(string $teks): string
    {
        $teks = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $teks) ?? '';

        return htmlspecialchars($teks, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function relsUtama(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function relsWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        // Nama sheet Excel: maksimal 31 karakter dan tanpa : \ / ? * [ ]
        $nama = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '-', $this->namaSheet) ?? 'Sheet1', 0, 31);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->aman($nama).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function styles(): string
    {
        $tepi = '<border><left style="thin"><color rgb="FF94A3B8"/></left>'
            .'<right style="thin"><color rgb="FF94A3B8"/></right>'
            .'<top style="thin"><color rgb="FF94A3B8"/></top>'
            .'<bottom style="thin"><color rgb="FF94A3B8"/></bottom><diagonal/></border>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'

            .'<numFmts count="2">'
            .'<numFmt numFmtId="164" formatCode="#,##0"/>'
            .'<numFmt numFmtId="165" formatCode="dd/mm/yyyy"/>'
            .'</numFmts>'

            .'<fonts count="4">'
            .'<font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><name val="Calibri"/></font>'
            .'</fonts>'

            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFCBD5E1"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'

            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'.$tepi.'</borders>'

            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'

            .'<cellXfs count="11">'
            // 0 polos
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            // 1 judul
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center"/></xf>'
            // 2 subjudul
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center"/></xf>'
            // 3 kepala tabel
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            // 4 teks isi
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
            .'<alignment vertical="top" wrapText="1"/></xf>'
            // 5 teks isi rata tengah
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            // 6 angka
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="right" vertical="top"/></xf>'
            // 7 total angka
            .'<xf numFmtId="164" fontId="3" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="right" vertical="center"/></xf>'
            // 8 total teks
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center"/></xf>'
            // 9 tebal tanpa tepi
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            // 10 tanggal
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="top"/></xf>'
            .'</cellXfs>'

            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
