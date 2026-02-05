<?php

use Livewire\Component;
use App\Models\Ekstrakurikuler;
use App\Models\Vote;

new class extends Component
{
    public $user;
    public $voteData = null;
    public $ekstrakurikuler = null;
    public $rekomendasiEkskul = [];
    public $stats = [];

    public function mount()
    {
        $this->user = auth()->user();
        $this->loadVoteData();
        $this->loadRekomendasi();
        $this->loadStats();
    }

    private function loadVoteData()
    {
        // Cari data voting siswa
        $vote = Vote::with('ekstrakurikuler')
            ->where('user_id', $this->user->id)
            ->first();
        
        if ($vote) {
            $this->voteData = [
                'ekstrakurikuler' => $vote->ekstrakurikuler->nama,
                'ekstrakurikuler_id' => $vote->ekstrakurikuler->id,
                'deskripsi' => $vote->ekstrakurikuler->deskripsi,
                'pembina' => $vote->ekstrakurikuler->pembina,
                'kuota' => $vote->ekstrakurikuler->kuota,
                'jumlah_pemilih' => $vote->ekstrakurikuler->votes()->count(),
                'waktu_vote' => $vote->created_at->translatedFormat('d F Y H:i'),
                'sisa_kuota' => max(0, $vote->ekstrakurikuler->kuota - $vote->ekstrakurikuler->votes()->count()),
                'persentase' => $vote->ekstrakurikuler->kuota > 0 ? 
                    round(($vote->ekstrakurikuler->votes()->count() / $vote->ekstrakurikuler->kuota) * 100, 1) : 0,
            ];
            $this->ekstrakurikuler = $vote->ekstrakurikuler;
        }
    }

    private function loadRekomendasi()
    {
        // Ambil 4 ekstrakurikuler terpopuler (selain yang sudah dipilih)
        $excludeId = $this->ekstrakurikuler ? $this->ekstrakurikuler->id : null;
        
        $this->rekomendasiEkskul = Ekstrakurikuler::withCount('votes')
            ->when($excludeId, function ($query) use ($excludeId) {
                $query->where('id', '!=', $excludeId);
            })
            ->orderByDesc('votes_count')
            ->limit(4)
            ->get()
            ->map(function ($ekskul) {
                return [
                    'id' => $ekskul->id,
                    'nama' => $ekskul->nama,
                    'deskripsi' => substr($ekskul->deskripsi, 0, 100) . '...',
                    'pembina' => $ekskul->pembina,
                    'kuota' => $ekskul->kuota,
                    'jumlah_pemilih' => $ekskul->votes_count,
                    'sisa_kuota' => max(0, $ekskul->kuota - $ekskul->votes_count),
                    'persentase' => $ekskul->kuota > 0 ? round(($ekskul->votes_count / $ekskul->kuota) * 100, 1) : 0,
                    'status' => $this->getStatusKuota($ekskul->kuota, $ekskul->votes_count),
                ];
            })->toArray();

        // Jika kurang dari 4, tambahkan random
        if (count($this->rekomendasiEkskul) < 4) {
            $needed = 4 - count($this->rekomendasiEkskul);
            $randomEkskul = Ekstrakurikuler::whereNotIn('id', 
                collect($this->rekomendasiEkskul)->pluck('id')->toArray()
            )->inRandomOrder()
            ->limit($needed)
            ->get()
            ->map(function ($ekskul) {
                return [
                    'id' => $ekskul->id,
                    'nama' => $ekskul->nama,
                    'deskripsi' => substr($ekskul->deskripsi, 0, 100) . '...',
                    'pembina' => $ekskul->pembina,
                    'kuota' => $ekskul->kuota,
                    'jumlah_pemilih' => $ekskul->votes()->count(),
                    'sisa_kuota' => max(0, $ekskul->kuota - $ekskul->votes()->count()),
                    'persentase' => $ekskul->kuota > 0 ? 
                        round(($ekskul->votes()->count() / $ekskul->kuota) * 100, 1) : 0,
                    'status' => $this->getStatusKuota($ekskul->kuota, $ekskul->votes()->count()),
                ];
            })->toArray();

            $this->rekomendasiEkskul = array_merge($this->rekomendasiEkskul, $randomEkskul);
        }
    }

    private function loadStats()
    {
        $this->stats = [
            'total_ekskul' => Ekstrakurikuler::count(),
            'total_siswa' => \App\Models\User::where('role', 'siswa')->count(),
            'sudah_voting' => \App\Models\User::where('role', 'siswa')->where('has_voted', true)->count(),
            'belum_voting' => \App\Models\User::where('role', 'siswa')->where('has_voted', false)->count(),
            'persentase_voting' => \App\Models\User::where('role', 'siswa')->count() > 0 ? 
                round((\App\Models\User::where('role', 'siswa')->where('has_voted', true)->count() / 
                       \App\Models\User::where('role', 'siswa')->count()) * 100, 1) : 0,
        ];
    }

    private function getStatusKuota($kuota, $pemilih)
    {
        if ($kuota == 0) return 'secondary';
        
        $persentase = ($pemilih / $kuota) * 100;
        
        if ($persentase >= 100) {
            return 'danger'; // Penuh
        } elseif ($persentase >= 80) {
            return 'warning'; // Hampir penuh
        } elseif ($pemilih > 0) {
            return 'success'; // Tersedia
        } else {
            return 'secondary'; // Kosong
        }
    }

    public function render()
    {
        return view('pages::siswa.⚡dashboard')
            ->layout('layouts::app')->title('Siswa Dashboard');
    }
}

?>

<div>
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold">
                        <i class="bi bi-person-circle"></i> Dashboard Siswa
                    </h2>
                    <p class="text-muted">Selamat datang, {{ $user->name }}!</p>
                </div>
                <div>
                    <span class="badge {{ $user->has_voted ? 'bg-success' : 'bg-warning' }} fs-6 px-3 py-2">
                        <i class="bi bi-{{ $user->has_voted ? 'check-circle' : 'clock' }} me-1"></i>
                        {{ $user->has_voted ? 'Sudah Voting' : 'Belum Voting' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Ekstrakurikuler</h6>
                            <h2 class="mb-0">{{ $stats['total_ekskul'] }}</h2>
                        </div>
                        <i class="bi bi-trophy" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Siswa Sudah Voting</h6>
                            <h2 class="mb-0">{{ $stats['sudah_voting'] }}</h2>
                        </div>
                        <i class="bi bi-check-circle" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Siswa Belum Voting</h6>
                            <h2 class="mb-0">{{ $stats['belum_voting'] }}</h2>
                        </div>
                        <i class="bi bi-clock" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Partisipasi</h6>
                            <h2 class="mb-0">{{ $stats['persentase_voting'] }}%</h2>
                        </div>
                        <i class="bi bi-percent" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Info & Voting Result -->
    <div class="row mb-4">
        <!-- User Info Card -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-person-badge me-2"></i>Informasi Pribadi
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-person" style="font-size: 2.5rem; color: #667eea;"></i>
                        </div>
                        <h4 class="mt-3 mb-1">{{ $user->name }}</h4>
                        <p class="text-muted">Siswa SMK Metland Cibitung</p>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">
                                    <i class="bi bi-person-badge me-2"></i>NISN
                                </span>
                                <span class="fw-semibold">{{ $user->nisn }}</span>
                            </div>
                        </div>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">
                                    <i class="bi bi-book me-2"></i>Kelas
                                </span>
                                <span class="fw-semibold">{{ $user->kelas }}</span>
                            </div>
                        </div>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">
                                    <i class="bi bi-briefcase me-2"></i>Jurusan
                                </span>
                                <span class="fw-semibold">{{ $user->jurusan }}</span>
                            </div>
                        </div>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">
                                    <i class="bi bi-envelope me-2"></i>Email
                                </span>
                                <span class="fw-semibold text-truncate">{{ $user->email }}</span>
                            </div>
                        </div>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">
                                    <i class="bi bi-calendar-check me-2"></i>Bergabung
                                </span>
                                <span class="fw-semibold">{{ $user->created_at->translatedFormat('d F Y') }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="alert {{ $user->has_voted ? 'alert-success' : 'alert-warning' }}">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-{{ $user->has_voted ? 'check-circle' : 'exclamation-triangle' }} me-2 fs-5"></i>
                                <div>
                                    <h6 class="mb-1">{{ $user->has_voted ? 'Sudah Voting' : 'Belum Voting' }}</h6>
                                    <p class="mb-0 small">
                                        @if($user->has_voted)
                                            Anda sudah melakukan voting ekstrakurikuler.
                                        @else
                                            Anda belum melakukan voting. 
                                            <a href="{{ route('voting.index') }}" class="alert-link">Klik di sini</a> untuk memilih.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Voting Result Card -->
        <div class="col-md-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-trophy me-2"></i>Hasil Pilihan Anda
                    </h5>
                    @if($user->has_voted)
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle me-1"></i>Terkonfirmasi
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if($voteData)
                        <div class="text-center mb-4">
                            <div class="icon-wrapper bg-success bg-opacity-10 p-4 rounded-circle d-inline-block">
                                <i class="bi bi-check-circle" style="font-size: 3rem; color: #28a745;"></i>
                            </div>
                            <h3 class="mt-3 text-success">Voting Berhasil!</h3>
                            <p class="text-muted">Terima kasih telah berpartisipasi dalam voting ekstrakurikuler</p>
                        </div>
                        
                        <div class="card border-success border-2">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-4">
                                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                            <i class="bi bi-trophy-fill" style="font-size: 2rem; color: #28a745;"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h4 class="mb-1">{{ $voteData['ekstrakurikuler'] }}</h4>
                                        <p class="text-muted mb-2">{{ $voteData['deskripsi'] }}</p>
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="bi bi-person-badge text-primary me-2"></i>
                                                    <div>
                                                        <small class="text-muted d-block">Pembina</small>
                                                        <strong>{{ $voteData['pembina'] }}</strong>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="bi bi-calendar-check text-primary me-2"></i>
                                                    <div>
                                                        <small class="text-muted d-block">Waktu Vote</small>
                                                        <strong>{{ $voteData['waktu_vote'] }}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="bi bi-people text-primary me-2"></i>
                                                    <div>
                                                        <small class="text-muted d-block">Kuota / Pemilih</small>
                                                        <strong>{{ $voteData['jumlah_pemilih'] }} / {{ $voteData['kuota'] }}</strong>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-graph-up text-primary me-2"></i>
                                                    <div>
                                                        <small class="text-muted d-block">Status Kuota</small>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                                <div class="progress-bar bg-{{ $voteData['persentase'] >= 100 ? 'danger' : ($voteData['persentase'] >= 80 ? 'warning' : 'success') }}" 
                                                                     style="width: {{ min($voteData['persentase'], 100) }}%">
                                                                </div>
                                                            </div>
                                                            <span class="fw-semibold">{{ $voteData['persentase'] }}%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-4">
                            <div class="d-flex">
                                <i class="bi bi-info-circle me-3" style="font-size: 1.2rem;"></i>
                                <div>
                                    <h6 class="mb-1">Informasi Penting</h6>
                                    <p class="mb-0 small">
                                        Pilihan Anda sudah tercatat. Silakan hubungi pembina 
                                        <strong>{{ $voteData['pembina'] }}</strong> untuk informasi lebih lanjut 
                                        mengenai jadwal kegiatan <strong>{{ $voteData['ekstrakurikuler'] }}</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card border-primary border-1">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-check-circle text-success me-2"></i>Voting Selesai
                                        </h6>
                                        <p class="small text-muted mb-2">
                                            Anda sudah menggunakan hak pilih Anda. Tidak dapat mengubah pilihan.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-warning border-1">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-clock text-warning me-2"></i>Selanjutnya
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            Tunggu pengumuman jadwal kegiatan dari pembina.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="icon-wrapper bg-warning bg-opacity-10 p-4 rounded-circle d-inline-block">
                                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ffc107;"></i>
                            </div>
                            <h3 class="mt-3">Belum Melakukan Voting</h3>
                            <p class="text-muted">Anda belum memilih ekstrakurikuler.</p>
                            <div class="mt-4">
                                <a href="{{ route('voting.index') }}" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>Lakukan Voting Sekarang
                                </a>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Setiap siswa hanya dapat memilih satu ekstrakurikuler
                                </small>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-4">
                            <div class="d-flex">
                                <i class="bi bi-exclamation-triangle me-3" style="font-size: 1.2rem;"></i>
                                <div>
                                    <h6 class="mb-1">Penting!</h6>
                                    <p class="mb-0 small">
                                        Voting hanya dapat dilakukan satu kali. 
                                        Pastikan memilih ekstrakurikuler yang sesuai dengan minat dan bakat Anda.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Rekomendasi Ekstrakurikuler -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-stars me-2"></i>Rekomendasi Ekstrakurikuler Lainnya
                    </h5>
                    <p class="text-muted mb-0">Berikut adalah ekstrakurikuler populer di SMK Metland Cibitung</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($rekomendasiEkskul as $ekskul)
                            <div class="col-md-3 mb-4">
                                <div class="card h-100 border hover-lift">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                                <i class="bi bi-trophy" style="color: #667eea;"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-0">{{ $ekskul['nama'] }}</h5>
                                                <small class="text-muted">{{ $ekskul['pembina'] }}</small>
                                            </div>
                                        </div>
                                        
                                        <p class="text-muted small">{{ $ekskul['deskripsi'] }}</p>
                                        
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">Kuota</small>
                                                <small class="fw-semibold">{{ $ekskul['jumlah_pemilih'] }}/{{ $ekskul['kuota'] }}</small>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-{{ $ekskul['status'] }}" 
                                                     style="width: {{ min($ekskul['persentase'], 100) }}%">
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <small class="text-muted">{{ $ekskul['persentase'] }}% terisi</small>
                                                <small class="fw-semibold">Sisa: {{ $ekskul['sisa_kuota'] }}</small>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                <i class="bi bi-people me-1"></i>
                                                {{ $ekskul['jumlah_pemilih'] }} siswa sudah bergabung
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent border-0">
                                        <button class="btn btn-outline-primary btn-sm w-100" 
                                                onclick="showDetailModal({{ $ekskul['id'] }})">
                                            <i class="bi bi-eye me-1"></i>Lihat Detail
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center py-4">
                                    <i class="bi bi-trophy" style="font-size: 3rem; color: #e9ecef;"></i>
                                    <p class="text-muted mt-3">Tidak ada data ekstrakurikuler</p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Sekolah -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-info-circle me-2"></i>Informasi SMK Metland Cibitung
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex mb-3">
                                <div class="me-3">
                                    <i class="bi bi-calendar-check text-primary" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h6>Jadwal Kegiatan</h6>
                                    <p class="text-muted small mb-0">
                                        Ekstrakurikuler dilaksanakan setiap hari Jumat pukul 14.00 - 16.00 WIB.
                                        Pertemuan pertama akan diumumkan melalui papan pengumuman.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex mb-3">
                                <div class="me-3">
                                    <i class="bi bi-person-check text-success" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h6>Pembina</h6>
                                    <p class="text-muted small mb-0">
                                        Setiap ekstrakurikuler memiliki pembina yang bertanggung jawab.
                                        Hubungi pembina untuk informasi lebih lanjut.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex mb-3">
                                <div class="me-3">
                                    <i class="bi bi-award text-warning" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h6>Prestasi</h6>
                                    <p class="text-muted small mb-0">
                                        Ekstrakurikuler di SMK Metland Cibitung telah meraih berbagai prestasi
                                        di tingkat kota, provinsi, dan nasional.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex mb-3">
                                <div class="me-3">
                                    <i class="bi bi-chat-dots text-info" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h6>Konsultasi</h6>
                                    <p class="text-muted small mb-0">
                                        Untuk konsultasi mengenai ekstrakurikuler, hubungi bagian kesiswaan
                                        atau langsung menghubungi pembina terkait.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-light mt-3">
                        <div class="d-flex">
                            <i class="bi bi-megaphone text-primary me-3" style="font-size: 1.2rem;"></i>
                            <div>
                                <h6 class="mb-1">Pengumuman</h6>
                                <p class="mb-0 small">
                                    Selamat kepada siswa yang telah memilih ekstrakurikuler! 
                                    Pastikan untuk mengikuti pertemuan pertama sesuai jadwal yang telah ditentukan.
                                    Bagi yang belum voting, segera lakukan sebelum batas waktu yang ditentukan.
                                </p>
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
    .hover-lift {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .hover-lift:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    .avatar {
        transition: transform 0.3s;
    }
    .icon-wrapper {
        transition: transform 0.3s;
    }
    .progress-bar {
        border-radius: 3px;
    }
    .card {
        border-radius: 10px;
    }
    .badge {
        font-size: 0.85em;
    }
</style>
@endpush

@push('scripts')
<script>
    // Function untuk show detail modal (akan diimplementasi nanti)
    function showDetailModal(ekskulId) {
        // TODO: Implement modal untuk detail ekstrakurikuler
        alert('Fitur detail ekstrakurikuler akan segera tersedia. ID: ' + ekskulId);
    }
    
    // Auto refresh data setiap 30 detik
    document.addEventListener('livewire:initialized', () => {
        setInterval(() => {
            @this.$refresh();
        }, 30000); // 30 detik
    });
</script>
@endpush