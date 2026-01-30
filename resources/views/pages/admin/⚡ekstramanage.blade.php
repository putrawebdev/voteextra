<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Ekstrakurikuler;
use App\Models\Vote;
use Illuminate\Validation\Rule;

new class extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Properties untuk form
    public $showForm = false;
    public $formType = 'create'; // 'create' or 'edit'
    public $ekstraId = null;

    public $nama = '';
    public $deskripsi = '';
    public $pembina = '';
    public $kuota = 30;

    // Properties untuk search
    public $search = '';
    public $filterStatus = '';

    
    // Properties untuk statistik
    public $stats = [];
    
    // Properties untuk delete
   public $deleteId = null;
   public $deleteName = '';

    protected function rules()
    {
        $rules = [
            'nama' => 'required|string|max:255',
            'deskripsi' => 'required|string|min:10',
            'pembina' => 'required|string|max:255',
            'kuota' => 'required|integer|min:1|max:200',
        ];

        // Untuk edit, tambahkan rule unique kecuali untuk data yang sedang diedit
        if ($this->formType === 'edit') {
            $rules['nama'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('ekstrakurikulers')->ignore($this->ekstraId)
            ];
        } else {
            $rules['nama'] = 'required|string|max:255|unique:ekstrakurikulers,nama';
        }

        return $rules;
    }

    protected $messages = [
        'nama.required' => 'Nama ekstrakurikuler wajib diisi.',
        'nama.unique' => 'Nama ekstrakurikuler sudah ada.',
        'deskripsi.required' => 'Deskripsi wajib diisi.',
        'deskripsi.min' => 'Deskripsi minimal 10 karakter.',
        'pembina.required' => 'Nama pembina wajib diisi.',
        'kuota.required' => 'Kuota wajib diisi.',
        'kuota.integer' => 'Kuota harus berupa angka.',
        'kuota.min' => 'Kuota minimal 1 peserta.',
        'kuota.max' => 'Kuota maksimal 200 peserta.',
    ];

    public function mount()
    {
        $this->loadStats();
    }

    private function loadStats()
    {
        $this->stats = [
            'total' => Ekstrakurikuler::count(),
            'totalKuota' => Ekstrakurikuler::sum('kuota'),
            'totalPemilih' => Vote::count(),
            'persentaseTerisi' => Ekstrakurikuler::sum('kuota') > 0 ? 
                round((Vote::count() / Ekstrakurikuler::sum('kuota')) * 100, 1) : 0,
        ];
    }

    // Reset form
    private function resetForm()
    {
        $this->reset([
            'nama', 'deskripsi', 'pembina', 'kuota',
            'showForm', 'formType', 'ekstraId'
        ]);
        $this->resetErrorBag();
        $this->kuota = 30; // Reset ke default
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
        
        $ekstra = Ekstrakurikuler::findOrFail($id);
        $this->ekstraId = $ekstra->id;
        $this->nama = $ekstra->nama;
        $this->deskripsi = $ekstra->deskripsi;
        $this->pembina = $ekstra->pembina;
        $this->kuota = $ekstra->kuota;
        
        $this->formType = 'edit';
        $this->showForm = true;
    }

    // Save data
    public function save()
    {
        $this->validate();

        $data = [
            'nama' => $this->nama,
            'deskripsi' => $this->deskripsi,
            'pembina' => $this->pembina,
            'kuota' => $this->kuota,
        ];

        if ($this->formType === 'create') {
            Ekstrakurikuler::create($data);
            $message = 'Ekstrakurikuler berhasil ditambahkan!';
        } else {
            Ekstrakurikuler::find($this->ekstraId)->update($data);
            $message = 'Ekstrakurikuler berhasil diperbarui!';
        }

        $this->resetForm();
        $this->loadStats();
        $this->dispatch('swal', [
            'icon' => 'success',
            'title' => 'Berhasil!',
            'text' => $message
        ]);
    }

    public function confirmDelete($id)
    {
        $ekstra = Ekstrakurikuler::findOrFail($id);
        
        $this->deleteId = $id;
        $this->deleteName = $ekstra->nama;
        
        // Dispatch event langsung ke JavaScript
        $this->dispatch('show-delete-confirmation', [
            'id' => $id,
            'name' => $ekstra->nama
        ]);
    }

    // Delete data - METHOD YANG DIPANGGIL DARI JAVASCRIPT
    public function deleteConfirmed($id)
    {
        try {
            $ekstra = Ekstrakurikuler::findOrFail($id);
            $nama = $ekstra->nama;
            
            // Cek apakah ada siswa yang sudah memilih ekstrakurikuler ini
            if ($ekstra->votes()->count() > 0) {
                session()->flash('swal', [
                    'icon' => 'error',
                    'title' => 'Gagal!',
                    'text' => "Tidak dapat menghapus {$nama} karena sudah ada siswa yang memilih."
                ]);
                return;
            }
            
            $ekstra->delete();
            
            $this->loadStats();
            
            session()->flash('swal', [
                'icon' => 'success',
                'title' => 'Berhasil!',
                'text' => "Ekstrakurikuler {$nama} berhasil dihapus."
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
    public function getEkstrakurikulersProperty()
    {
        return Ekstrakurikuler::query()
            ->withCount('votes')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('nama', 'like', '%' . $this->search . '%')
                      ->orWhere('pembina', 'like', '%' . $this->search . '%')
                      ->orWhere('deskripsi', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterStatus, function ($query) {
                if ($this->filterStatus === 'penuh') {
                    $query->havingRaw('votes_count >= kuota');
                } elseif ($this->filterStatus === 'tersedia') {
                    $query->havingRaw('votes_count < kuota AND votes_count > 0');
                } elseif ($this->filterStatus === 'kosong') {
                    $query->havingRaw('votes_count = 0');
                } elseif ($this->filterStatus === 'hampir_penuh') {
                    $query->havingRaw('votes_count >= kuota * 0.8 AND votes_count < kuota');
                }
            })
            ->orderBy('nama')
            ->paginate(10);
    }

    // Hitung persentase kuota
    public function getPersentaseKuota($ekstra)
    {
        if ($ekstra->kuota == 0) return 0;
        
        $pemilih = $ekstra->votes_count;
        return round(($pemilih / $ekstra->kuota) * 100, 1);
    }

    // Get status kuota
    public function getStatusKuota($ekstra)
    {
        $persentase = $this->getPersentaseKuota($ekstra);
        
        if ($persentase >= 100) {
            return ['label' => 'Penuh', 'color' => 'danger', 'icon' => 'bi-x-circle'];
        } elseif ($persentase >= 80) {
            return ['label' => 'Hampir Penuh', 'color' => 'warning', 'icon' => 'bi-exclamation-triangle'];
        } elseif ($ekstra->votes_count > 0) {
            return ['label' => 'Tersedia', 'color' => 'success', 'icon' => 'bi-check-circle'];
        } else {
            return ['label' => 'Belum Ada Pemilih', 'color' => 'secondary', 'icon' => 'bi-clock'];
        }
    }

    // Get sisa kuota
    public function getSisaKuota($ekstra)
    {
        return max(0, $ekstra->kuota - $ekstra->votes_count);
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

    public function render()
    {
        return view('pages::admin.⚡ekstramanage', [
            'ekstrakurikulers' => $this->ekstrakurikulers,
        ])->layout('layouts::app')->title('Kelola Ekstrakurikuler');
    }
};
?>

<div>
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex p-3 justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold">
                        <i class="bi bi-trophy"></i> Kelola Ekstrakurikuler
                    </h2>
                    <p class="text-muted">Management data ekstrakurikuler SMK Metland Cibitung</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" wire:click="refreshData">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-primary" wire:click="showCreateForm">
                        <i class="bi bi-plus-circle"></i> Tambah Ekstrakurikuler
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4 p-3">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Ekstrakurikuler</h6>
                            <h2 class="mb-0">{{ $stats['total'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-collection" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Kuota</h6>
                            <h2 class="mb-0">{{ $stats['totalKuota'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-people-fill" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Pemilih</h6>
                            <h2 class="mb-0">{{ $stats['totalPemilih'] ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-check-circle-fill" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Terisi</h6>
                            <h2 class="mb-0">{{ $stats['persentaseTerisi'] ?? 0 }}%</h2>
                        </div>
                        <i class="bi bi-percent" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-2 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               placeholder="Cari nama, pembina, atau deskripsi..." 
                               wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="filterStatus">
                        <option value="">Semua Status</option>
                        <option value="tersedia">Tersedia</option>
                        <option value="hampir_penuh">Hampir Penuh</option>
                        <option value="penuh">Penuh</option>
                        <option value="kosong">Belum Ada Pemilih</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex justify-content-end">
                        <span class="text-muted me-2">
                            {{ $ekstrakurikulers->total() }} data ditemukan
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Modal -->
    @if($showForm)
        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle"></i> Konfirmasi Hapus
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus <strong id="deleteItemName"></strong>?</p>
                        <p class="text-danger small">
                            <i class="bi bi-info-circle"></i> 
                            Data yang sudah dihapus tidak dapat dikembalikan.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Batal
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="bi bi-trash"></i> Ya, Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-trophy"></i>
                            {{ $formType === 'create' ? 'Tambah' : 'Edit' }} Ekstrakurikuler
                        </h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="cancel"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="save">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Ekstrakurikuler <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('nama') is-invalid @enderror" 
                                           wire:model="nama"
                                           placeholder="Contoh: Pramuka, PMR, Basket"
                                           autofocus>
                                    @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pembina <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('pembina') is-invalid @enderror" 
                                           wire:model="pembina"
                                           placeholder="Nama pembina">
                                    @error('pembina') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('deskripsi') is-invalid @enderror" 
                                          wire:model="deskripsi" 
                                          rows="4"
                                          placeholder="Deskripsi kegiatan ekstrakurikuler..."></textarea>
                                @error('deskripsi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">Minimal 10 karakter</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kuota Peserta <span class="text-danger">*</span></label>
                                    <input type="number" 
                                           class="form-control @error('kuota') is-invalid @enderror" 
                                           wire:model="kuota"
                                           min="1" 
                                           max="200">
                                    @error('kuota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <small class="text-muted">Maksimal 200 peserta</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Preview</label>
                                    <div class="border rounded p-3 bg-light">
                                        <small class="text-muted d-block mb-1">Informasi:</small>
                                        <div class="d-flex justify-content-between">
                                            <span>Nama:</span>
                                            <span class="fw-semibold">{{ $nama ?: 'Belum diisi' }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Kuota:</span>
                                            <span class="fw-semibold">{{ $kuota }} peserta</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Pembina:</span>
                                            <span class="fw-semibold">{{ $pembina ?: 'Belum diisi' }}</span>
                                        </div>
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
            @if($ekstrakurikulers->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-trophy" style="font-size: 4rem; color: #e9ecef;"></i>
                    <h4 class="mt-3">Tidak ada data</h4>
                    <p class="text-muted">Belum ada data ekstrakurikuler.</p>
                    <button class="btn btn-primary" wire:click="showCreateForm">
                        <i class="bi bi-plus-circle"></i> Tambah Data Pertama
                    </button>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <th>Nama Ekstrakurikuler</th>
                                <th>Pembina</th>
                                <th>Kuota</th>
                                <th>Pemilih</th>
                                <th>Status</th>
                                <th width="120" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ekstrakurikulers as $index => $ekstra)
                                @php
                                    $status = $this->getStatusKuota($ekstra);
                                    $persentase = $this->getPersentaseKuota($ekstra);
                                    $sisaKuota = $this->getSisaKuota($ekstra);
                                @endphp
                                <tr>
                                    <td>{{ $ekstrakurikulers->firstItem() + $index }}</td>
                                    <td>
                                        <div>
                                            <strong class="d-block">{{ $ekstra->nama }}</strong>
                                            <small class="text-muted">{{ Str::limit($ekstra->deskripsi, 60) }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info">
                                            {{ $ekstra->pembina }}
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="fw-semibold">{{ $ekstra->kuota }}</span>
                                            <small class="text-muted d-block">peserta</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 8px; min-width: 60px;">
                                                <div class="progress-bar bg-{{ $status['color'] }}" 
                                                     role="progressbar" 
                                                     style="width: {{ min($persentase, 100) }}%">
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-semibold">{{ $ekstra->votes_count }}</span>
                                                <small class="text-muted d-block">{{ $persentase }}%</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $status['color'] }}">
                                            <i class="{{ $status['icon'] }} me-1"></i>{{ $status['label'] }}
                                        </span>
                                        @if($sisaKuota > 0 && $persentase < 100)
                                            <small class="d-block text-muted">Sisa: {{ $sisaKuota }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    wire:click="showEditForm({{ $ekstra->id }})"
                                                    title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            @if($ekstra->votes_count == 0)
                                                <button class="btn btn-outline-danger" 
                                                        wire:click="confirmDelete({{ $ekstra->id }})"
                                                        title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-outline-secondary" 
                                                        title="Tidak dapat dihapus karena sudah ada pemilih"
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
                            Menampilkan {{ $ekstrakurikulers->firstItem() }} - {{ $ekstrakurikulers->lastItem() }} 
                            dari {{ $ekstrakurikulers->total() }} data
                        </p>
                    </div>
                    <div>
                        {{ $ekstrakurikulers->links() }}
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
                                    <i class="bi bi-check-circle"></i> Tersedia
                                </span>
                                <div>
                                    <small class="fw-semibold">Tersedia</small>
                                    <p class="text-muted small mb-0">Masih menerima peserta baru</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-warning me-2">
                                    <i class="bi bi-exclamation-triangle"></i> Hampir Penuh
                                </span>
                                <div>
                                    <small class="fw-semibold">Hampir Penuh</small>
                                    <p class="text-muted small mb-0">Kuota terisi ≥80%</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-danger me-2">
                                    <i class="bi bi-x-circle"></i> Penuh
                                </span>
                                <div>
                                    <small class="fw-semibold">Penuh</small>
                                    <p class="text-muted small mb-0">Kuota sudah terpenuhi</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-secondary me-2">
                                    <i class="bi bi-clock"></i> Belum Ada
                                </span>
                                <div>
                                    <small class="fw-semibold">Belum Ada Pemilih</small>
                                    <p class="text-muted small mb-0">Belum ada yang memilih</p>
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
                                    <li>Ekstrakurikuler yang sudah memiliki pemilih <strong>tidak dapat dihapus</strong>.</li>
                                    <li>Pastikan kuota sesuai dengan kapasitas yang tersedia di sekolah.</li>
                                    <li>Perbarui informasi pembina jika ada perubahan.</li>
                                    <li>Gunakan deskripsi yang jelas untuk menarik minat siswa.</li>
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
    .progress {
        min-width: 60px;
    }
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
                title: 'Hapus Ekstrakurikuler',
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
                    // Refresh halaman setelah konfirmasi
                    @this.dispatch('refresh');
                }
            });
        });

        // Auto-focus ke input nama ketika modal dibuka
        @this.on('showFormChanged', (value) => {
            if (value) {
                setTimeout(() => {
                    const namaInput = document.querySelector('input[wire\\:model="nama"]');
                    if (namaInput) namaInput.focus();
                }, 100);
            }
        });

        // Close modal dengan ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && @this.showForm) {
                @this.call('cancel');
            }
        });

        // Prevent form submission on Enter key in textarea
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName === 'TEXTAREA' && !e.shiftKey) {
                e.preventDefault();
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