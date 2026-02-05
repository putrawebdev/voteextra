<?php

use Livewire\Component;

new class extends Component
{
    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        
        return redirect()->route('login');
    }
    public function render()
    {
        return view('pages::auth.⚡logout')
            ->layout('layouts::auth')->title('Auth Logout');
    }
};
?>

<!-- resources/views/livewire/auth/logout.blade.php -->
<div>
    <button type="button" 
            class="dropdown-item text-danger"
            wire:click="logout"
            onclick="return confirm('Yakin ingin logout?')">
        <i class="bi bi-box-arrow-right me-2"></i>Logout
    </button>
</div>