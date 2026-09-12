<?php

namespace App\Services;

/**
 * Nomor versi aplikasi yang ditampilkan kepada pengguna.
 *
 * Nomornya dibaca dari berkas VERSION di akar proyek, dan bila kodenya
 * hasil git clone, tujuh karakter pertama commit yang sedang tayang ikut
 * disebut — dibaca langsung dari berkas .git, bukan lewat perintah git,
 * karena hosting bersama biasanya mematikan exec().
 */
final class VersiAplikasi
{
    public const BAWAAN = '2.5.0';

    public function nomor(): string
    {
        $berkas = base_path('VERSION');

        if (! is_readable($berkas)) {
            return self::BAWAAN;
        }

        return trim((string) file_get_contents($berkas)) ?: self::BAWAAN;
    }

    /**
     * Tujuh karakter pertama commit yang sedang tayang, atau null bila
     * kodenya bukan hasil git clone.
     */
    public function komit(): ?string
    {
        $git = base_path('.git');
        $head = @file_get_contents($git.'/HEAD');

        if ($head === false) {
            return null;
        }

        $head = trim($head);

        // HEAD terlepas: langsung berisi hash.
        if (! str_starts_with($head, 'ref: ')) {
            return $this->pendek($head);
        }

        $ref = substr($head, 5);
        $hash = @file_get_contents($git.'/'.$ref);

        if ($hash !== false) {
            return $this->pendek(trim($hash));
        }

        // Ref yang sudah dipadatkan tersimpan di packed-refs.
        $terpadat = @file_get_contents($git.'/packed-refs');

        if ($terpadat !== false && preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/m', $terpadat, $m)) {
            return $this->pendek($m[1]);
        }

        return null;
    }

    /**
     * Sebutan lengkap untuk layar: "v2.5.0 · c4ce725".
     */
    public function label(): string
    {
        $komit = $this->komit();

        return 'v'.$this->nomor().($komit ? " · {$komit}" : '');
    }

    private function pendek(string $hash): ?string
    {
        return preg_match('/^[0-9a-f]{40}$/', $hash) ? substr($hash, 0, 7) : null;
    }
}
