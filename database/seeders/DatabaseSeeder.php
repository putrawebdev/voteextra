<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin Default
        User::create([
            'nisn' => 'admin001',
            'name' => 'Administrator',
            'kelas' => 'X',
            'jurusan' => 'DKV',
            'email' => 'admin@metland.sch.id',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => true,
            'has_voted' => false,
        ]);

        // Contoh Siswa
        User::create([
            'nisn' => '20230001',
            'name' => 'Ahmad Budi',
            'kelas' => 'X',
            'jurusan' => 'PPLG',
            'email' => 'ahmad@example.com',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
            'status' => true,
            'has_voted' => false,
        ]);
    }
}
