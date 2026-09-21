<?php

namespace App\Rules;

use App\Models\Usulan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Satu nomor SPD untuk satu usulan.
 *
 * Nomor SPPD melekat pada tiap pelaksana — kawan serombongan memegang nomor
 * masing-masing — sehingga nomor yang sama muncul dua kali hanya berarti
 * usulan ganda atau salah salin. Dibandingkan tanpa peduli huruf besar-kecil
 * dan spasi tepi supaya "ar.05.02/f.xxx/12/2026" tidak lolos sebagai nomor
 * baru.
 */
class NomorSpdUnik implements ValidationRule
{
    /**
     * @param  Usulan|null  $kecuali  Usulan yang sedang disunting — nomornya sendiri bukan duplikat.
     */
    public function __construct(private ?Usulan $kecuali = null) {}

    public static function normalkan(?string $nomor): string
    {
        return mb_strtolower(trim((string) $nomor));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nomor = self::normalkan(is_string($value) ? $value : null);

        if ($nomor === '') {
            return;
        }

        $pemakai = Usulan::query()
            ->whereRaw('LOWER(TRIM(no_spd)) = ?', [$nomor])
            ->when($this->kecuali, fn ($q) => $q->whereKeyNot($this->kecuali->id))
            ->with('user')
            ->first();

        if ($pemakai === null) {
            return;
        }

        $fail(sprintf(
            'Nomor SPD %s sudah dipakai pada usulan %s (%s). Satu nomor SPD hanya untuk satu usulan — periksa kembali nomornya atau buka usulan yang sudah ada.',
            trim((string) $value),
            $pemakai->no_usulan,
            $pemakai->user?->nama ?? 'pelaksana lain',
        ));
    }
}
