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
    public $showDeleteModal = false;  
    public $showForm = false;
    public $formType = 'create'; // 'create' or 'edit'
    public $userId = null;

    public $name = '';
    public $email = '';
    public $nisn = '';
    public $kelas = '';
    public $jurusan = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = 'siswa';
    public $status = true;
    public $has_voted = false;

    // Properties untuk search dan filter
    public $search = '';
    public $filterKelas = '';
    public $filterJurusan = '';
    public $filterRole = '';
    public $filterStatus = '';
    public $filterVoted = '';

    // Properties untuk statistik
    public $stats = [];

    // Properties untuk delete
    public $deleteId = null;
    public $deleteName = '';
    public $deleteNisn = '';
    public $deleteClass = '';

    // List kelas dan jurusan
    public $kelasList = [
        '10' => ['X PPLG', 'X DKV', 'X Kuliner'],
        '11' => ['XI PPLG', 'XI DKV', 'XI Kuliner'],
        '12' => ['XII PPLG', 'XII DKV', 'XII Kuliner'],
    ];

    public $jurusanList = [
        'PPLG' => 'Pengembangan Perangkat Lunak dan Gim',
        'DKV' => 'Desain Komunikasi Visual',
        'Kuliner' => 'Kuliner',
        'UMUM' => 'Umum', // Untuk admin/staff
    ];

    protected function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'nisn' => 'required|string|max:20',
            'kelas' => 'required|string|max:20',
            'jurusan' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'role' => 'required|in:siswa,admin',
            'status' => 'required|boolean',
            'has_voted' => 'boolean',
        ];

        // Validasi NISN unik untuk create
        if ($this->formType === 'create') {
            $rules['nisn'] = [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'nisn')
            ];
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            // Untuk edit, NISN unik kecuali untuk user yang sedang diedit
            $rules['nisn'] = [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'nisn')->ignore($this->userId)
            ];
            
            // Password optional untuk edit
            if ($this->password) {
                $rules['password'] = 'string|min:8|confirmed';
            }
        }

        // Validasi email unik jika diisi
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
        'name.required' => 'Nama wajib diisi.',
        'nisn.required' => 'NISN wajib diisi.',
        'nisn.unique' => 'NISN sudah terdaftar.',
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
            'hasVoted' => User::where('has_voted', true)->count(),
            'notVoted' => User::where('has_voted', false)->count(),
        ];
    }

    // Reset form
    private function resetForm()
    {
        $this->reset([
            'name', 'email', 'nisn', 'kelas', 'jurusan',
            'password', 'password_confirmation', 'role', 'status', 'has_voted',
            'showForm', 'formType', 'userId'
        ]);
        $this->resetErrorBag();
        $this->role = 'siswa';
        $this->status = true;
        $this->has_voted = false;
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
        $this->name = $user->name;
        $this->email = $user->email;
        $this->nisn = $user->nisn;
        $this->kelas = $user->kelas;
        $this->jurusan = $user->jurusan;
        $this->role = $user->role;
        $this->status = (bool) $user->status;
        $this->has_voted = (bool) $user->has_voted;
        
        $this->formType = 'edit';
        $this->showForm = true;
    }

    // Save data
    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'nisn' => $this->nisn,
            'kelas' => $this->kelas,
            'jurusan' => $this->jurusan,
            'role' => $this->role,
            'status' => $this->status,
            'has_voted' => $this->has_voted,
        ];

        // Jika password diisi (untuk create atau edit dengan password baru)
        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->formType === 'create') {
            // Untuk create, pastikan email_verified_at diisi jika ada email
            if ($this->email) {
                $data['email_verified_at'] = now();
            }
            
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
            'text' => "Password {$user->name} telah direset ke default (password123)"
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
            'text' => "Status {$user->name} telah {$status}"
        ]);
    }

    // Toggle voting status
    public function toggleVoteStatus($id)
    {
        $user = User::findOrFail($id);
        $user->update(['has_voted' => !$user->has_voted]);
        
        $status = $user->has_voted ? 'ditandai sudah voting' : 'ditandai belum voting';
        $this->loadStats();
        
        $this->dispatch('swal', [
            'icon' => 'success',
            'title' => 'Berhasil!',
            'text' => "Status voting {$user->name} telah {$status}"
        ]);
    }

    
    // CONFIRM DELETE
    public function confirmDelete($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->has_voted) {
            session()->flash('error', "Tidak dapat menghapus {$user->name} karena sudah melakukan voting.");
            return;
        }
        
        $this->deleteId = $user->id;
        $this->deleteName = $user->name;
        $this->deleteNisn = $user->nisn;
        $this->deleteClass = $user->kelas;
        
        $this->showDeleteModal = true;
    }

    // DELETE USER
    public function deleteUser()
    {
        if (!$this->deleteId) {
            session()->flash('error', 'ID user tidak valid.');
            return;
        }

        try {
            $user = User::findOrFail($this->deleteId);
            
            if ($user->has_voted) {
                session()->flash('error', "Tidak dapat menghapus {$user->name} karena sudah melakukan voting.");
                $this->closeDeleteModal();
                return;
            }
            
            $userName = $user->name;
            $user->delete();
            
            $this->loadStats();
            $this->closeDeleteModal();
            
            session()->flash('success', "User {$userName} berhasil dihapus.");
            
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage());
            $this->closeDeleteModal();
        }
    }

    // CLOSE DELETE MODAL
    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deleteId = null;
        $this->deleteName = '';
        $this->deleteNisn = '';
        $this->deleteClass = '';
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
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('nisn', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%')
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
            ->when($this->filterVoted !== '', function ($query) {
                $query->where('has_voted', $this->filterVoted == '1');
            })
            ->orderBy('kelas')
            ->orderBy('name')
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

    // Bulk actions
    public function bulkResetPassword()
    {
        $this->dispatch('swal', [
            'icon' => 'info',
            'title' => 'Info',
            'text' => 'Fitur reset password massal akan segera tersedia.'
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

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus
                        </h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeDeleteModal"></button>
                    </div>
                    
                    <!-- Form untuk delete -->
                    <form wire:submit.prevent="deleteUser">
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <div class="d-flex">
                                    <i class="bi bi-exclamation-octagon-fill me-3 fs-4"></i>
                                    <div>
                                        <h5 class="alert-heading">PERHATIAN!</h5>
                                        <p class="mb-1">Data yang dihapus tidak dapat dikembalikan.</p>
                                        <p class="mb-0">Pastikan data yang akan dihapus benar.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card border-danger mb-3">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-danger">Data yang akan dihapus:</h6>
                                    <table class="table table-sm table-bordered">
                                        <tbody>
                                            <tr>
                                                <td width="30%"><strong>Nama</strong></td>
                                                <td>{{ $deleteName }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>NISN</strong></td>
                                                <td>{{ $deleteNisn }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Kelas</strong></td>
                                                <td>{{ $deleteClass }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                                <label class="form-check-label text-danger fw-semibold" for="confirmDelete">
                                    <i class="bi bi-check-circle"></i> Saya yakin ingin menghapus data ini
                                </label>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeDeleteModal">
                                <i class="bi bi-x-circle"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Hapus Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-success text-white">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong class="me-auto">Berhasil!</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    {{ session('success') }}
                </div>
            </div>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong class="me-auto">Error!</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    {{ session('error') }}
                </div>
            </div>
        </div>
    @endif

    @if(session()->has('info'))
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-info text-white">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong class="me-auto">Info</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    {{ session('info') }}
                </div>
            </div>
        </div>
    @endif

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
                               placeholder="Cari NISN, nama, atau email..." 
                               wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" wire:model.live="filterKelas">
                        <option value="">Semua Kelas</option>
                        @foreach($kelasList as $tingkat => $kelasArray)
                            <optgroup label="Kelas {{ $tingkat }}">
                                @foreach($kelasArray as $kelas)
                                    <option value="{{ $kelas }}">{{ $kelas }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
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
            
            <!-- Filter tambahan -->
            <div class="row mt-2">
                <div class="col-md-2">
                    <select class="form-select" wire:model.live="filterVoted">
                        <option value="">Status Voting</option>
                        <option value="1">Sudah Voting</option>
                        <option value="0">Belum Voting</option>
                    </select>
                </div>
                <div class="col-md-10 d-flex justify-content-end">
                    <button class="btn btn-outline-danger btn-sm" wire:click="bulkResetPassword">
                        <i class="bi bi-key"></i> Reset Password Massal
                    </button>
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
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           wire:model="name"
                                           placeholder="Nama lengkap"
                                           autofocus>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">NISN <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('nisn') is-invalid @enderror" 
                                           wire:model="nisn"
                                           placeholder="Nomor Induk Siswa Nasional">
                                    @error('nisn') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                                        <option value="STAFF">STAFF</option>
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

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" 
                                               id="has_voted" wire:model="has_voted">
                                        <label class="form-check-label" for="has_voted">
                                            Sudah Voting?
                                        </label>
                                    </div>
                                    <small class="text-muted">Centang jika user sudah melakukan voting</small>
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
                                            <li>NISN harus unik untuk setiap user</li>
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
                                <th>NISN</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Jurusan</th>
                                <th>Status</th>
                                <th>Voting</th>
                                <th width="180" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $index => $user)
                                <tr>
                                    <td>{{ $users->firstItem() + $index }}</td>
                                    <td>
                                        <span class="fw-semibold">{{ $user->nisn }}</span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="d-block">{{ $user->name }}</strong>
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
                                        @if($user->has_voted)
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
                                                    onclick="return confirm('Reset password {{ $user->name }} ke default?')"
                                                    title="Reset Password">
                                                <i class="bi bi-key"></i>
                                            </button>
                                            <button class="btn btn-outline-{{ $user->status ? 'danger' : 'success' }}" 
                                                    wire:click="toggleStatus({{ $user->id }})"
                                                    onclick="return confirm('{{ $user->status ? 'Nonaktifkan' : 'Aktifkan' }} {{ $user->name }}?')"
                                                    title="{{ $user->status ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                <i class="bi bi-{{ $user->status ? 'toggle-off' : 'toggle-on' }}"></i>
                                            </button>
                                            <button class="btn btn-outline-{{ $user->has_voted ? 'secondary' : 'info' }}" 
                                                    wire:click="toggleVoteStatus({{ $user->id }})"
                                                    onclick="return confirm('{{ $user->has_voted ? 'Tandai belum voting' : 'Tandai sudah voting' }} {{ $user->name }}?')"
                                                    title="{{ $user->has_voted ? 'Tandai belum voting' : 'Tandai sudah voting' }}">
                                                <i class="bi bi-{{ $user->has_voted ? 'x-circle' : 'check-circle' }}"></i>
                                            </button>
                                            @if(!$user->has_voted)
                                                <form method="POST" wire:submit.prevent="confirmDelete({{ $user->id }})" 
                                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus {{ $user->name }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="btn btn-outline-danger" 
                                                            title="Hapus">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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
                        <div class="col-md-3">
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
                        <div class="col-md-3">
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
                        <div class="col-md-3">
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
                        <div class="col-md-3">
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
                    </div>
                    
                    <div class="alert alert-light mt-3">
                        <div class="d-flex">
                            <i class="bi bi-lightbulb text-warning me-3" style="font-size: 1.2rem;"></i>
                            <div>
                                <h6 class="mb-1">Tips & Panduan</h6>
                                <ul class="mb-0 small">
                                    <li>User yang sudah voting <strong>tidak dapat dihapus</strong>.</li>
                                    <li>Gunakan fitur reset password untuk mengembalikan password ke default.</li>
                                    <li>Nonaktifkan user jika sudah tidak membutuhkan akses.</li>
                                    <li>Pastikan NISN unik untuk setiap user.</li>
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
                        setTimeout(() => {
                            resolve();
                        }, 1000);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    @this.dispatch('refreshData');
                }
            });
        });

        // SweetAlert dari Livewire dispatch
        @this.on('swal', (event) => {
            const data = event[0];
            Swal.fire({
                icon: data.icon,
                title: data.title,
                text: data.text,
                timer: 3000,
                showConfirmButton: false
            });
        });

        // Auto-focus ke input name ketika modal dibuka
        @this.on('showFormChanged', (value) => {
            if (value) {
                setTimeout(() => {
                    const nameInput = document.querySelector('input[wire\\:model="name"]');
                    if (nameInput) nameInput.focus();
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