<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'nip' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $this->catatLogin($request);

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'nip' => 'NIP atau kata sandi salah.',
        ])->onlyInput('nip');
    }

    public function showRegister()
    {
        return view('register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:50', 'unique:users,nip'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'nama' => $validated['nama'],
            'nip' => $validated['nip'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_DOSEN_TENDIK,
        ]);

        Auth::login($user);
        $this->catatLogin($request);

        return redirect()->route('dashboard');
    }

    /**
     * Catat kapan dan dari mana pengguna terakhir masuk.
     *
     * Ditulis tanpa menyentuh updated_at supaya jejak penyuntingan akun
     * tidak tergeser hanya karena orangnya masuk.
     */
    private function catatLogin(Request $request): void
    {
        $pengguna = $request->user();

        $pengguna?->forceFill([
            'login_terakhir_at' => now(),
            'login_terakhir_ip' => $request->ip(),
        ])->saveQuietly();
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
