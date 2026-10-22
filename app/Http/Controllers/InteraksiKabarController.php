<?php

namespace App\Http\Controllers;

use App\Enums\JenisReaksi;
use App\Models\KomentarKabar;
use App\Models\ReaksiKabar;
use App\Models\TindakLanjut;
use App\Models\Usulan;
use App\Services\KabarPerjalanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Komentar dan reaksi pada kartu news feed dashboard eksekutif.
 *
 * Kembali ke kartu yang sama setelah menyimpan, supaya pembaca tidak
 * kehilangan tempatnya di lini masa.
 */
class InteraksiKabarController extends Controller
{
    public function komentar(Request $request, string $jenis, int $id): RedirectResponse
    {
        $this->pastikanKabarAda($jenis, $id);

        $data = $request->validate([
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'isi.required' => 'Tulis komentarnya lebih dulu.',
            'isi.max' => 'Komentar paling panjang 1.000 karakter.',
        ]);

        KomentarKabar::create([
            'jenis' => $jenis,
            'id_kabar' => $id,
            'id_user' => $request->user()->id,
            'isi' => trim($data['isi']),
        ]);

        return back()->withFragment(self::jangkar($jenis, $id));
    }

    public function hapusKomentar(Request $request, KomentarKabar $komentar): RedirectResponse
    {
        abort_unless($komentar->bolehDihapusOleh($request->user()), 403, 'Hanya penulisnya yang dapat menghapus komentar ini.');

        $komentar->delete();

        return back()->withFragment(self::jangkar($komentar->jenis, $komentar->id_kabar));
    }

    /**
     * Pilih reaksi; memilih reaksi yang sama sekali lagi mencabutnya.
     */
    public function reaksi(Request $request, string $jenis, int $id): RedirectResponse
    {
        $this->pastikanKabarAda($jenis, $id);

        $data = $request->validate(['reaksi' => ['required', Rule::enum(JenisReaksi::class)]]);
        $pilihan = JenisReaksi::from($data['reaksi']);

        $kunci = ['jenis' => $jenis, 'id_kabar' => $id, 'id_user' => $request->user()->id];
        $tersimpan = ReaksiKabar::where($kunci)->first();

        if ($tersimpan?->reaksi === $pilihan) {
            $tersimpan->delete();
        } else {
            ReaksiKabar::updateOrCreate($kunci, ['reaksi' => $pilihan]);
        }

        return back()->withFragment(self::jangkar($jenis, $id));
    }

    /** Id elemen kartu pada halaman news feed. */
    public static function jangkar(string $jenis, int $id): string
    {
        return "kabar-{$jenis}-{$id}";
    }

    private function pastikanKabarAda(string $jenis, int $id): void
    {
        $ada = match ($jenis) {
            KabarPerjalanan::JENIS_PERJALANAN => Usulan::whereKey($id)->exists(),
            KabarPerjalanan::JENIS_TINDAK_LANJUT => TindakLanjut::whereKey($id)->exists(),
            default => false,
        };

        abort_unless($ada, 404);
    }
}
