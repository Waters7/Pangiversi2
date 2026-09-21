<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menyajikan berkas unggahan lewat aplikasi, bukan lewat tautan simbolik
 * public/storage.
 *
 * Di hosting bersama tautan simbolik itu kerap tidak diikuti peladen web
 * (Options FollowSymLinks dimatikan), sehingga surat tugas yang jelas ada
 * di storage/app/public dijawab 404. Melewatkan berkas lewat PHP membuat
 * tautannya tidak bergantung pada pengaturan peladen — dan sekalian
 * mensyaratkan pengguna sudah masuk, karena isinya dokumen keuangan.
 */
class BerkasController extends Controller
{
    /**
     * Folder unggahan yang boleh dibuka; di luar ini dijawab 404.
     *
     * @var list<string>
     */
    private const FOLDER = ['dokumen/', 'keuangan/', 'foto-profil/', 'bantuan/'];

    public function lihat(string $path): BinaryFileResponse
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        abort_if(str_contains($path, '..') || ! Str::startsWith($path, self::FOLDER), 404);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($path) && is_file($disk->path($path)), 404);

        // inline: PDF dan gambar terbuka langsung di peramban; nama aslinya
        // tetap dipakai bila pengguna memilih menyimpan.
        return response()->file($disk->path($path), [
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
