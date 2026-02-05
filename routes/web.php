<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest.custom'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('auth.login');
    }); 
    Route::livewire('/login', 'pages::auth.login')->name('auth.login'); 
});

// Route untuk admin
// Admin Routes (hanya untuk admin)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('/ekstramanage', 'pages::admin.ekstramanage')->name('ekstramanage');
    Route::livewire('/usermanage', 'pages::admin.usermanage')->name('usermanage'); 
});

// Route untuk siswa 
Route::middleware(['auth', 'siswa'])->prefix('siswa')->name('siswa.')->group(function () {
    Route::livewire('/siswa/dashboard', 'pages::siswa.dashboard')->name('dashboardd'); 
});

// Logout Route
Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();
    
    return redirect()->route('auth.login')->with('success', 'Berhasil logout.');
})->name('logout');
