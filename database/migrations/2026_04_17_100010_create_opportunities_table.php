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
    Schema::create('opportunities', function (Blueprint $table) {
        $table->id();
        $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
        $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
        $table->string('judul');
        $table->text('deskripsi');
        $table->string('lokasi');
        $table->text('maps_url')->nullable();
        $table->string('foto')->nullable(); // <-- TAMBAHKAN INI UNTUK SIMPAN NAMA FILE FOTO
        $table->enum('tipe', ['online', 'offline']);
        $table->date('tanggal_mulai');
        $table->date('tanggal_selesai');
        $table->integer('kuota');
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->enum('status', ['open', 'closed'])->default('open');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
