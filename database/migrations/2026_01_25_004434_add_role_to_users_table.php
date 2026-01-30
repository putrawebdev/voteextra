<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'siswa'])->default('siswa');
            $table->string('nisn')->unique();
            $table->string('kelas');
            $table->string('jurusan');
            $table->boolean('has_voted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'nisn', 'kelas', 'jurusan', 'has_voted']);
        });
    }
};