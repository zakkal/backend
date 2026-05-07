<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',      // Wajib ada untuk sinkronisasi DB
        'created_by',   // Wajib ada untuk sinkronisasi DB
        'judul',
        'deskripsi',
        'lokasi',
        'maps_url',
        'foto',
        'tipe',
        'tanggal_mulai',
        'tanggal_selesai',
        'kuota',
        'status',
    ];

    protected $appends = ['foto_url'];

    public function getFotoUrlAttribute()
    {
        if ($this->foto) {
            if (filter_var($this->foto, FILTER_VALIDATE_URL)) {
                return $this->foto;
            }
            return url('storage/' . $this->foto);
        }
        return url('assets/images/default-opportunity.png');
    }

    // Relasi ke Organisasi
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Relasi ke User (Pembuat)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relasi ke Like
    public function likes() 
    {
        return $this->hasMany(Like::class);
    }

    // Relasi ke Komentar
    public function comments() 
    {
        return $this->hasMany(Comment::class);
    }

    // Tambahkan ini di dalam class Opportunity
public function categories()
{
    return $this->belongsToMany(Category::class, 'category_opportunity');
}
}