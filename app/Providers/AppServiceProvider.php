<?php

namespace App\Providers;

use App\Enums\Kemampuan;
use App\Models\User;
use App\Services\PenentuHakAkses;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tabel peran dibaca sekali per permintaan, bukan pada tiap @can.
        $this->app->singleton(PenentuHakAkses::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->daftarkanGateKemampuan();
        $this->bagikanNotifikasi();
    }

    /**
     * Turunkan satu Gate untuk setiap kemampuan, sehingga route cukup memakai
     * `can:<nama-kemampuan>` dan definisi hak akses tetap terpusat di enum peran.
     */
    private function daftarkanGateKemampuan(): void
    {
        foreach (Kemampuan::cases() as $kemampuan) {
            Gate::define(
                $kemampuan->value,
                fn (User $user) => $user->punyaKemampuan($kemampuan),
            );
        }
    }

    /**
     * Sediakan lonceng notifikasi pada layout utama tanpa perlu diteruskan
     * dari setiap controller.
     */
    private function bagikanNotifikasi(): void
    {
        ViewFacade::composer('app', function (View $view): void {
            $pengguna = Auth::user();

            $view->with([
                'notifikasiBelumDibaca' => $pengguna?->notifikasi()->belumDibaca()->count() ?? 0,
                'notifikasiTerbaru' => $pengguna?->notifikasi()->limit(5)->get() ?? collect(),
            ]);
        });
    }
}
