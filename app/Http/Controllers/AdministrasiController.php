<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdministrasiController extends Controller
{
    /**
     * Display all users with search & role filter.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $roleFilter = $request->input('role');

        $query = User::withCount('usulan');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $query->where('role', $roleFilter);
        }

        $users = $query->latest()->paginate(10)->appends($request->query());

        $totalUsers = User::count();
        $totalAdmin = User::where('role', 'administrator')->count();
        $totalPPK = User::where('role', 'ppk')->count();
        $totalPegawai = User::where('role', 'pegawai')->count();

        return view('administrasi-sistem', compact(
            'users',
            'search',
            'roleFilter',
            'totalUsers',
            'totalAdmin',
            'totalPPK',
            'totalPegawai',
        ));
    }

    /**
     * Store a new user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'nip' => ['required', 'string', 'max:50', 'unique:users,nip'],
            'role' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'nama' => $validated['nama'],
            'email' => $validated['email'] ?? null,
            'nip' => $validated['nip'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('administrasi')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    /**
     * Update user profile (nama, email, nip, role).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'nip' => ['required', 'string', 'max:50', Rule::unique('users', 'nip')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::roleOptions()))],
        ]);

        $user->update($validated);

        return redirect()->route('administrasi')
            ->with('success', "Data pengguna {$user->nama} berhasil diperbarui.");
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('administrasi')
            ->with('success', "Password {$user->nama} berhasil diubah.");
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $nama = $user->nama;
        $user->delete();

        return redirect()->route('administrasi')
            ->with('success', "Pengguna {$nama} berhasil dihapus.");
    }
}
