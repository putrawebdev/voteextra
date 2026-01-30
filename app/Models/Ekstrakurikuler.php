<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Ekstrakurikuler extends Model
{
    protected $fillable = ['nama', 'deskripsi', 'pembina', 'kuota'];

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function getJumlahPemilihAttribute()
    {
        return $this->votes()->count();
    }

    // Accessor untuk persentase kuota
    public function getPersentaseKuotaAttribute(): float
    {
        if ($this->kuota == 0) {
            return 0;
        }
        
        return round(($this->jumlah_pemilih / $this->kuota) * 100, 1);
    }

    // Accessor untuk status kuota
    public function getStatusKuotaAttribute(): string
    {
        $persentase = $this->persentase_kuota;
        
        if ($persentase >= 100) {
            return 'penuh';
        } elseif ($persentase >= 80) {
            return 'hampir_penuh';
        } elseif ($this->jumlah_pemilih > 0) {
            return 'tersedia';
        } else {
            return 'kosong';
        }
    }

    // Method untuk cek apakah masih bisa menerima pemilih
    public function bisaDipilih(): bool
    {
        return $this->jumlah_pemilih < $this->kuota;
    }

    // Method untuk sisa kuota
    public function sisaKuota(): int
    {
        return max(0, $this->kuota - $this->jumlah_pemilih);
    }

    // Scope untuk filter status
    public function scopeFilterByStatus($query, $status)
    {
        return $query->where(function($q) use ($status) {
            if ($status === 'penuh') {
                $q->whereRaw('(SELECT COUNT(*) FROM votes WHERE votes.ekstrakurikuler_id = ekstrakurikulers.id) >= kuota');
            } elseif ($status === 'hampir_penuh') {
                $q->whereRaw('(SELECT COUNT(*) FROM votes WHERE votes.ekstrakurikuler_id = ekstrakurikulers.id) >= kuota * 0.8')
                  ->whereRaw('(SELECT COUNT(*) FROM votes WHERE votes.ekstrakurikuler_id = ekstrakurikulers.id) < kuota');
            } elseif ($status === 'tersedia') {
                $q->whereRaw('(SELECT COUNT(*) FROM votes WHERE votes.ekstrakurikuler_id = ekstrakurikulers.id) > 0')
                  ->whereRaw('(SELECT COUNT(*) FROM votes WHERE votes.ekstrakurikuler_id = ekstrakurikulers.id) < kuota');
            } elseif ($status === 'kosong') {
                $q->whereRaw('(SELECT COUNT(*) FROM votes WHERE votes.ekstrakurikuler_id = ekstrakurikulers.id) = 0');
            }
        });
    }
}