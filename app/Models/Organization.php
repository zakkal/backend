<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'nama_organisasi', 
        'deskripsi', 
        'alamat', 
        'website', 
        'logo' // Tetap gunakan 'logo' sesuai database kamu
    ];

    // Ini supaya field 'foto_url' muncul otomatis di JSON API
    protected $appends = ['foto_url'];

    /**
     * Accessor untuk foto_url
     * Diperbaiki: Menggunakan $this->logo agar nyambung dengan database
     */
    public function getFotoUrlAttribute()
    {
        if ($this->logo) {
            // Memastikan mengarah ke kolom 'logo'
            return url('storage/organizations/' . $this->logo);
        }
        
        // Foto cadangan jika admin belum upload logo
        return url('assets/images/default-org.png');
    }

    // Relasi balik ke User (Pemilik Organisasi)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke banyak lowongan (Opportunities)
    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}