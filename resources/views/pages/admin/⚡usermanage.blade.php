<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

new class extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Properties untuk form
    public $showForm = false;
    public $formType = 'create'; // 'create' or 'edit'
    public $userId = null;

    public $nis = '';
    public $nama = '';
    public $kelas = '';
    public $jurusan = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = 'siswa';
    public $status = false;

    // Properties untuk search dan filter
    public $search = '';
    public $filterKelas = '';
    public $filterJurusan = '';
    public $filterRole = '';
    public $filterStatus = '';

    // Properties untuk statistik
    public $stats = [];

    // Properties untuk delete
    public $deleteId = null;
    public $deleteName = '';

    // List kelas dan jurusan
    public $kelasList = [
        '10' => ['X IPA', 'X IPS', 'X TKJ', 'X RPL', 'X MM', 'X AK'],
        '11' => ['XI IPA', 'XI IPS', 'XI TKJ', 'XI RPL', 'XI MM', 'XI AK'],
        '12' => ['XII IPA', 'XII IPS', 'XII TKJ', 'XII RPL', 'XII MM', 'XII AK'],
    ];

    public $jurusanList = [
        'IPA' => 'Ilmu Pengetahuan Alam',
        'IPS' => 'Ilmu Pengetahuan Sosial',
        'TKJ' => 'Teknik Komputer dan Jaringan',
        'RPL' => 'Rekayasa Perangkat Lunak',
        'MM' => 'Multimedia',
        'AK' => 'Akuntansi',
    ];

    protected function rules()
    {
        $rules = [
            'nis' => 'required|string|max:20',
            'nama' => 'required|string|max:255',
            'kelas' => 'required|string|max:20',
            'jurusan' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'role' => 'required|in:siswa,admin',
            'status' => 'required|boolean',
        ];

        // Validasi NIS unik untuk create
        if ($this->formType === 'create') {
            $rules['nis'] = [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'nis')
            ];
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            // Untuk edit, NIS unik kecuali untuk user yang sedang diedit
            $rules['nis'] = [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'nis')->ignore($this->userId)
            ];
            
            // Password optional untuk edit
            if ($this->password) {
                $rules['password'] = 'string|min:8|confirmed';
            }
        }

        // Validasi email unik
        if ($this->email) {
            $rules['email'] = [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->userId)
            ];
        }

        return $rules;
    }

    protected $messages = [
        'nis.required' => 'NIS wajib diisi.',
        'nis.unique' => 'NIS sudah terdaftar.',
        'nama.required' => 'Nama siswa wajib diisi.',
        'kelas.required' => 'Kelas wajib diisi.',
        'jurusan.required' => 'Jurusan wajib dipilih.',
        'email.email' => 'Format email tidak valid.',
        'email.unique' => 'Email sudah terdaftar.',
        'password.required' => 'Password wajib diisi.',
        'password.min' => 'Password minimal 8 karakter.',
        'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        'role.required' => 'Role wajib dipilih.',
        'status.required' => 'Status wajib dipilih.',
    ];

    public function mount()
    {
        $this->loadStats();
    }

    private function loadStats()
    {
        $this->stats = [
            'total' => User::count(),
            'siswa' => User::where('role', 'siswa')->count(),
            'admin' => User::where('role', 'admin')->count(),
            'active' => User::where('status', true)->count(),
            'inactive' => User::where('status', false)->count(),
            'hasVoted' => User::has('vote')->count(),
        ];
    }

    // Reset form
    private function resetForm()
    {
        $this->reset([
            'nis', 'nama', 'kelas', 'jurusan', 'email',
            'password', 'password_confirmation', 'role', 'status',
            'showForm', 'formType', 'userId'
        ]);
        $this->resetErrorBag();
        $this->role = 'siswa';
        $this->status = true;
    }

    // Show create form
    public function showCreateForm()
    {
        $this->resetForm();
        $this->formType = 'create';
        $this->showForm = true;
    }

    // Show edit form
    public function showEditForm($id)
    {
        $this->resetForm();
        
        $user = User::findOrFail($id);
        $this->userId = $user->id;
        $this->nis = $user->nis;
        $this->nama = $user->nama;
        $this->kelas = $user->kelas;
        $this->jurusan = $user->jurusan;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->status = $user->status;
        
        $this->formType = 'edit';
        $this->showForm = true;
    }

    // Save data
    public function save()
    {
        $this->validate();

        $data = [
            'nis' => $this->nis,
            'nama' => $this->nama,
            'kelas' => $this->kelas,
            'jurusan' => $this->jurusan,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
        ];

        // Jika password diisi (untuk create atau edit dengan password baru)
        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->formType === 'create') {
            User::create($data);
            $message = 'User berhasil ditambahkan!';
        } else {
            // Untuk edit, jika password tidak diubah, jangan update password
            if (!$this->password) {
                unset($data['password']);
            }
            
            User::find($this->userId)->update($data);
            $message = 'User berhasil diperbarui!';
        }

        $this->resetForm();
        $this->loadStats();
        $this->dispatch('swal', [
            'icon' => 'success',
            'title' => 'Berhasil!',
            'text' => $message
        ]);
    }

    // Reset password
    public function resetPassword($id)
    {
        $user = User::findOrFail($id);
        $defaultPassword = Hash::make('password123'); // Default password
        
        $user->update(['password' => $defaultPassword]);
        
        $this->dispatch('swal', [
            'icon' => 'success',
            'title' => 'Berhasil!',
            'text' => "Password {$user->nama} telah direset ke default (password123)"
        ]);
    }

    // Toggle status
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => !$user->status]);
        
        $status = $user->status ? 'diaktifkan' : 'dinonaktifkan';
        $this->loadStats();
        
        $this->dispatch('swal', [
            'icon' => 'success',
            'title' => 'Berhasil!',
            'text' => "Status {$user->nama} telah {$status}"
        ]);
    }

    // Confirm delete
    public function confirmDelete($id)
    {
        $user = User::findOrFail($id);
        
        // Cek apakah user sudah voting
        if ($user->hasVoted()) {
            $this->dispatch('swal', [
                'icon' => 'error',
                'title' => 'Gagal!',
                'text' => "Tidak dapat menghapus {$user->nama} karena sudah melakukan voting."
            ]);
            return;
        }
        
        $this->dispatch('show-delete-confirmation', [
            'id' => $id,
            'name' => $user->nama,
            'type' => 'user'
        ]);
    }

    // Delete data
    public function deleteConfirmed($id)
    {
        try {
            $user = User::findOrFail($id);
            $nama = $user->nama;
            
            // Double check untuk voting
            if ($user->hasVoted()) {
                session()->flash('swal', [
                    'icon' => 'error',
                    'title' => 'Gagal!',
                    'text' => "Tidak dapat menghapus {$nama} karena sudah melakukan voting."
                ]);
                return;
            }
            
            $user->delete();
            
            $this->loadStats();
            
            session()->flash('swal', [
                'icon' => 'success',
                'title' => 'Berhasil!',
                'text' => "User {$nama} berhasil dihapus."
            ]);
            
        } catch (\Exception $e) {
            session()->flash('swal', [
                'icon' => 'error',
                'title' => 'Gagal!',
                'text' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage()
            ]);
        }
    }

    // Cancel form
    public function cancel()
    {
        $this->resetForm();
    }

    // Get filtered data
    public function getUsersProperty()
    {
        return User::query()
            ->with('vote')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('nis', 'like', '%' . $this->search . '%')
                      ->orWhere('nama', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('kelas', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterKelas, function ($query) {
                $query->where('kelas', 'like', $this->filterKelas . '%');
            })
            ->when($this->filterJurusan, function ($query) {
                $query->where('jurusan', $this->filterJurusan);
            })
            ->when($this->filterRole, function ($query) {
                $query->where('role', $this->filterRole);
            })
            ->when($this->filterStatus !== '', function ($query) {
                $query->where('status', $this->filterStatus == '1');
            })
            ->orderBy('kelas')
            ->orderBy('nama')
            ->paginate(15);
    }

    // Get kelas options berdasarkan filter
    public function getFilteredKelasOptionsProperty()
    {
        $options = [];
        foreach ($this->kelasList as $tingkat => $kelasArr) {
            foreach ($kelasArr as $kelas) {
                $options[$kelas] = $kelas;
            }
        }
        return $options;
    }

    // Refresh data
    public function refreshData()
    {
        $this->loadStats();
        $this->dispatch('swal', [
            'icon' => 'success',
            'title' => 'Berhasil!',
            'text' => 'Data telah diperbarui.'
        ]);
    }

    // Export data
    public function exportData()
    {
        $this->dispatch('swal', [
            'icon' => 'info',
            'title' => 'Info',
            'text' => 'Fitur export akan segera tersedia.'
        ]);
    }

    // Import data
    public function importData()
    {
        $this->dispatch('swal', [
            'icon' => 'info',
            'title' => 'Info',
            'text' => 'Fitur import akan segera tersedia.'
        ]);
    }

    public function render()
    {
        return view('pages::admin.⚡usermanage', [
            'users' => $this->users,
        ])->layout('layouts.app')->title('Kelola User');
    }
}

?>

<div>
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex p-3 justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold">
                        <i class="bi bi-people-fill"></i> Kelola User/Siswa
                    </h2>
                    <p class="text-muted">Management data user dan siswa SMK Metland Cibitung</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" wire:click="refreshData">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-success" wire:click="exportData">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                    <button class="btn btn-warning" wire:click="importData">
                        <i class="bi bi-file-earmark-arrow-up"></i> Import
                    </button>
                    <button class="btn btn-primary" wire:click="showCreateForm">
                        <i class="bi bi-person-plus"></i> Tambah User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4 p-3">
        <div class="col-md-2 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total User</h6>
                            <h2 class="mb-0">{{ $stats['total'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-people" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Siswa</h6>
                            <h2 class="mb-0">{{ $stats['siswa'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-person-check" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Admin</h6>
                            <h2 class="mb-0">{{ $stats['admin'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-shield-check" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Aktif</h6>
                            <h2 class="mb-0">{{ $stats['active'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Nonaktif</h6>
                            <h2 class="mb-0">{{ $stats['inactive'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-x-circle" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card bg-purple text-white" style="background-color: #6f42c1;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Sudah Voting</h6>
                            <h2 class="mb-0">{{ $stats['hasVoted'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-check2-all" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-2 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               placeholder="Cari NIS, nama, atau email..." 
                               wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" wire:model.live="filterKelas">
                        <option value="">Semua Kelas</option>
                        {{-- @foreach($ as $key => $value)
                            <option value="{{ $key }}">{{ $value }}</option>
                        @endforeach --}}
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" wire:model.live="filterJurusan">
                        <option value="">Semua Jurusan</option>
                        @foreach($jurusanList as $key => $value)
                            <option value="{{ $key }}">{{ $key }} - {{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" wire:model.live="filterRole">
                        <option value="">Semua Role</option>
                        <option value="siswa">Siswa</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" wire:model.live="filterStatus">
                        <option value="">Semua Status</option>
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <div class="d-flex justify-content-end">
                        <span class="text-muted">
                            {{ $users->total() }} data
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Modal -->
    @if($showForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-person-badge"></i>
                            {{ $formType === 'create' ? 'Tambah' : 'Edit' }} User
                        </h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="cancel"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="save">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">NIS <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('nis') is-invalid @enderror" 
                                           wire:model="nis"
                                           placeholder="Contoh: 20230001"
                                           autofocus>
                                    @error('nis') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('nama') is-invalid @enderror" 
                                           wire:model="nama"
                                           placeholder="Nama lengkap siswa">
                                    @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                    <select class="form-select @error('kelas') is-invalid @enderror" wire:model="kelas">
                                        <option value="">Pilih Kelas</option>
                                        @foreach($kelasList as $tingkat => $kelasArr)
                                            <optgroup label="Kelas {{ $tingkat }}">
                                                @foreach($kelasArr as $k)
                                                    <option value="{{ $k }}">{{ $k }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    @error('kelas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jurusan <span class="text-danger">*</span></label>
                                    <select class="form-select @error('jurusan') is-invalid @enderror" wire:model="jurusan">
                                        <option value="">Pilih Jurusan</option>
                                        @foreach($jurusanList as $key => $value)
                                            <option value="{{ $key }}">{{ $key }} - {{ $value }}</option>
                                        @endforeach
                                    </select>
                                    @error('jurusan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" 
                                           class="form-control @error('email') is-invalid @enderror" 
                                           wire:model="email"
                                           placeholder="email@example.com">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select @error('role') is-invalid @enderror" wire:model="role">
                                        <option value="siswa">Siswa</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select @error('status') is-invalid @enderror" wire:model="status">
                                        <option value="1">Aktif</option>
                                        <option value="0">Nonaktif</option>
                                    </select>
                                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        Password {{ $formType === 'create' ? '<span class="text-danger">*</span>' : '' }}
                                    </label>
                                    <input type="password" 
                                           class="form-control @error('password') is-invalid @enderror" 
                                           wire:model="password"
                                           placeholder="{{ $formType === 'create' ? 'Password minimal 8 karakter' : 'Kosongkan jika tidak diubah' }}">
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Konfirmasi Password</label>
                                    <input type="password" 
                                           class="form-control @error('password_confirmation') is-invalid @enderror" 
                                           wire:model="password_confirmation"
                                           placeholder="Ulangi password">
                                    @error('password_confirmation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <div class="d-flex">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <div class="small">
                                        <strong>Catatan:</strong>
                                        <ul class="mb-0">
                                            <li>Password default untuk reset: <code>password123</code></li>
                                            <li>User dengan role "Admin" memiliki akses penuh</li>
                                            <li>User nonaktif tidak dapat login</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="button" class="btn btn-secondary" wire:click="cancel">
                                    <i class="bi bi-x-circle"></i> Batal
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if($users->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-people" style="font-size: 4rem; color: #e9ecef;"></i>
                    <h4 class="mt-3">Tidak ada data</h4>
                    <p class="text-muted">Belum ada data user/siswa.</p>
                    <button class="btn btn-primary" wire:click="showCreateForm">
                        <i class="bi bi-person-plus"></i> Tambah Data Pertama
                    </button>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <th>NIS</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Jurusan</th>
                                <th>Status</th>
                                <th>Voting</th>
                                <th width="150" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $index => $user)
                                <tr>
                                    <td>{{ $users->firstItem() + $index }}</td>
                                    <td>
                                        <span class="fw-semibold">{{ $user->nis }}</span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="d-block">{{ $user->nama }}</strong>
                                            <small class="text-muted">{{ $user->email ?? 'Tidak ada email' }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">
                                            {{ $user->kelas }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info">
                                            {{ $user->jurusan }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-{{ $user->status ? 'success' : 'danger' }}">
                                                <i class="bi bi-{{ $user->status ? 'check-circle' : 'x-circle' }} me-1"></i>
                                                {{ $user->status ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                            <span class="badge bg-{{ $user->role === 'admin' ? 'warning' : 'secondary' }} ms-1">
                                                {{ $user->role === 'admin' ? 'Admin' : 'Siswa' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($user->hasVoted())
                                            <span class="badge bg-success">
                                                <i class="bi bi-check2-all me-1"></i>Sudah
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-clock me-1"></i>Belum
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    wire:click="showEditForm({{ $user->id }})"
                                                    title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" 
                                                    wire:click="resetPassword({{ $user->id }})"
                                                    onclick="return confirm('Reset password {{ $user->nama }} ke default?')"
                                                    title="Reset Password">
                                                <i class="bi bi-key"></i>
                                            </button>
                                            <button class="btn btn-outline-{{ $user->status ? 'danger' : 'success' }}" 
                                                    wire:click="toggleStatus({{ $user->id }})"
                                                    onclick="return confirm('{{ $user->status ? 'Nonaktifkan' : 'Aktifkan' }} {{ $user->nama }}?')"
                                                    title="{{ $user->status ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                <i class="bi bi-{{ $user->status ? 'toggle-off' : 'toggle-on' }}"></i>
                                            </button>
                                            @if(!$user->hasVoted())
                                                <button class="btn btn-outline-danger" 
                                                        wire:click="confirmDelete({{ $user->id }})"
                                                        title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-outline-secondary" 
                                                        title="Tidak dapat dihapus karena sudah voting"
                                                        disabled>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <p class="text-muted mb-0">
                            Menampilkan {{ $users->firstItem() }} - {{ $users->lastItem() }} 
                            dari {{ $users->total() }} data
                        </p>
                    </div>
                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Legend and Information -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info bg-opacity-10 border-info">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle text-info"></i> Informasi & Panduan
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-success me-2">
                                    <i class="bi bi-check-circle"></i> Aktif
                                </span>
                                <div>
                                    <small class="fw-semibold">User Aktif</small>
                                    <p class="text-muted small mb-0">Dapat login dan voting</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-danger me-2">
                                    <i class="bi bi-x-circle"></i> Nonaktif
                                </span>
                                <div>
                                    <small class="fw-semibold">User Nonaktif</small>
                                    <p class="text-muted small mb-0">Tidak dapat login</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-warning me-2">
                                    <i class="bi bi-shield-check"></i> Admin
                                </span>
                                <div>
                                    <small class="fw-semibold">Role Admin</small>
                                    <p class="text-muted small mb-0">Akses penuh ke sistem</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-secondary me-2">
                                    <i class="bi bi-person-check"></i> Siswa
                                </span>
                                <div>
                                    <small class="fw-semibold">Role Siswa</small>
                                    <p class="text-muted small mb-0">Hanya dapat voting</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-success me-2">
                                    <i class="bi bi-check2-all"></i> Sudah Voting
                                </span>
                                <div>
                                    <small class="fw-semibold">Sudah Voting</small>
                                    <p class="text-muted small mb-0">Telah memilih ekstrakurikuler</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-secondary me-2">
                                    <i class="bi bi-clock"></i> Belum Voting
                                </span>
                                <div>
                                    <small class="fw-semibold">Belum Voting</small>
                                    <p class="text-muted small mb-0">Belum memilih ekstrakurikuler</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-light mt-3">
                        <div class="d-flex">
                            <i class="bi bi-lightbulb text-warning me-3" style="font-size: 1.2rem;"></i>
                            <div>
                                <h6 class="mb-1">Tips & Panduan</h6>
                                <ul class="mb-0 small">
                                    <li>User yang sudah melakukan voting <strong>tidak dapat dihapus</strong>.</li>
                                    <li>Gunakan fitur reset password untuk mengembalikan password ke default.</li>
                                    <li>Nonaktifkan user jika sudah tidak membutuhkan akses.</li>
                                    <li>Pastikan NIS unik untuk setiap user.</li>
                                    <li>Gunakan fitur import untuk menambahkan data dalam jumlah banyak.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .modal {
        backdrop-filter: blur(3px);
    }
    .badge {
        font-size: 0.75em;
    }
    .table td {
        vertical-align: middle;
    }
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
    }
    .bg-purple {
        background-color: #6f42c1 !important;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        // SweetAlert untuk konfirmasi delete
        @this.on('show-delete-confirmation', (event) => {
            const id = event[0].id;
            const name = event[0].name;
            
            Swal.fire({
                title: 'Hapus User',
                text: `Apakah Anda yakin ingin menghapus "${name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return new Promise((resolve) => {
                        @this.call('deleteConfirmed', id);
                        // Beri waktu untuk proses
                        setTimeout(() => {
                            resolve();
                        }, 1000);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    // Refresh data setelah konfirmasi
                    @this.dispatch('refreshData');
                }
            });
        });

        // Auto-focus ke input NIS ketika modal dibuka
        @this.on('showFormChanged', (value) => {
            if (value) {
                setTimeout(() => {
                    const nisInput = document.querySelector('input[wire\\:model="nis"]');
                    if (nisInput) nisInput.focus();
                }, 100);
            }
        });

        // Close modal dengan ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && @this.showForm) {
                @this.call('cancel');
            }
        });
    });

    // Handle SweetAlert dari session flash
    document.addEventListener('DOMContentLoaded', function() {
        @if(session('swal'))
            Swal.fire({
                icon: '{{ session('swal')['icon'] }}',
                title: '{{ session('swal')['title'] }}',
                text: '{{ session('swal')['text'] }}',
                timer: 3000,
                showConfirmButton: false
            });
        @endif
    });
</script>
@endpush