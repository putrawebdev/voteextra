<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;


new class extends Component
{
    public $nisn;
    public $password;
    public $showPassword = false;

    protected $rules = [
        'nisn' => 'required|string',
        'password' => 'required|string',
    ];

    protected $messages = [
        'nisn.required' => 'NISN wajib diisi.',
        'password.required' => 'Password wajib diisi.',
    ];

    public function mount()
    {
        // Redirect jika sudah login
        if (Auth::check()) {
            $this->redirectToDashboard();
        }
    }

    private function redirectToDashboard()
    {
        $user = Auth::user();
        
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        } else {
            return redirect()->route('siswa.dashboard');
        }
    }

    public function togglePasswordVisibility()
    {
        $this->showPassword = !$this->showPassword;
    }

    public function login()
    {
        $this->validate();

        // Coba login dengan NISN
        if (Auth::attempt(['nisn' => $this->nisn, 'password' => $this->password])) {
            $user = Auth::user();
            
            // Cek apakah user aktif
            if (!$user->status) {
                Auth::logout();
                session()->flash('error', 'Akun Anda dinonaktifkan. Silahkan hubungi administrator.');
                return;
            }

            // Redirect berdasarkan role
            return $this->redirectToDashboard();
        }

        // Jika login gagal
        session()->flash('error', 'NISN atau password salah.');
    }

    public function render()
    {
        return view('pages::auth.⚡login')
            ->layout('layouts::auth')->title('Auth Login');
    }
};
?>

<!-- resources/views/livewire/auth/login.blade.php -->
<div class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="w-100" style="max-width: 400px;">
        <div class="text-center mb-5">
            <div class="mb-3">
                <i class="bi bi-trophy-fill text-primary" style="font-size: 4rem;"></i>
            </div>
            <h2 class="fw-bold text-primary">Sistem Voting</h2>
            <p class="text-muted">SMK Metland Cibitung</p>
        </div>

        <div class="card shadow border-0">
            <div class="card-body p-4">
                <!-- Flash Messages -->
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form wire:submit.prevent="login">
                    <!-- NISN Field -->
                    <div class="mb-3">
                        <label for="nisn" class="form-label fw-semibold">
                            <i class="bi bi-person-badge me-2"></i>NISN
                        </label>
                        <input type="text" 
                               id="nisn"
                               wire:model="nisn"
                               class="form-control @error('nisn') is-invalid @enderror"
                               placeholder="Masukkan NISN Anda"
                               autofocus>
                        @error('nisn')
                            <div class="invalid-feedback">
                                <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Password Field -->
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">
                            <i class="bi bi-key me-2"></i>Password
                        </label>
                        <div class="input-group">
                            <input :type="showPassword ? 'text' : 'password'" 
                                   id="password"
                                   wire:model="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   placeholder="Masukkan password">
                            <button class="btn btn-outline-secondary" 
                                    type="button"
                                    wire:click="togglePasswordVisibility">
                                <i class="bi bi-eye{{ $showPassword ? '-slash' : '' }}"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="invalid-feedback">
                                <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid mb-3">
                        <button type="submit" 
                                class="btn btn-primary btn-lg"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="login">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </span>
                            <span wire:loading wire:target="login">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Memproses...
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Information -->
                <div class="text-center mt-4">
                    <div class="alert alert-light border">
                        <h6 class="mb-2"><i class="bi bi-info-circle me-2 text-primary"></i>Informasi Login</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li><i class="bi bi-check-circle text-success me-2"></i>Login hanya dengan NISN</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Password default: <code>password123</code></li>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Hubungi admin jika lupa password</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4">
            <p class="text-muted small">
                &copy; {{ date('Y') }} SMK Metland Cibitung<br>
                <small>Sistem Voting Ekstrakurikuler</small>
            </p>
        </div>
    </div>
</div>

@push('styles')
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .card {
        border-radius: 1rem;
        border: none;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    }
</style>
@endpush