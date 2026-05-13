<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('google_id')->nullable(); // Tambahkan ini untuk login google
            $table->string('password')->nullable(); // Nullable jika user login via Google
            
            // Kolom Role & Verifikasi Akun
            $table->enum('role', ['super_admin', 'admin', 'user'])->default('user');
            $table->boolean('is_verified')->default(false); 
            
            // Relasi ke Tabel Organisasi (Penting untuk Fitur Upgrade)
            $table->unsignedBigInteger('organization_id')->nullable(); 

            // Profil & Media
            $table->string('foto_profil')->nullable();
            $table->text('bio')->nullable();
            $table->string('lokasi')->nullable();
            
            $table->rememberToken();
            $table->timestamps();

            // Foreign Key Constraint (Pastikan tabel organizations dibuat sebelum/bersamaan)
            // $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};