<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SiswaMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Cek apakah user sudah login
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $user = Auth::user();

        // Cek apakah user adalah siswa
        if ($user->role !== 'siswa') {
            // Jika admin mencoba akses halaman siswa, redirect ke dashboard admin
            return redirect()->route('admin.dashboard')
                ->with('error', 'Admin tidak dapat mengakses halaman siswa.');
        }

        // Cek apakah user aktif
        if (!$user->status) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Akun Anda dinonaktifkan. Silahkan hubungi administrator.');
        }
        return $next($request);
    }
}
