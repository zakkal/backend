<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 
        'username', 
        'email', 
        'password', 
        'role', 
        'is_verified', 
        'google_id',
        'foto_profil', // Kolom untuk menyimpan path di DB
        'bio', 
        'lokasi',
        'organization_id'
    ];

    // Otomatis tambahkan field foto_profil_url saat dikirim ke API
    protected $appends = ['foto_profil_url'];

    /**
     * Accessor untuk URL lengkap foto profil
     */
    public function getFotoProfilUrlAttribute()
    {
        if ($this->foto_profil) {
            // Jika foto berasal dari Google Auth (biasanya diawali https)
            if (filter_var($this->foto_profil, FILTER_VALIDATE_URL)) {
                return $this->foto_profil;
            }
            // Jika foto diupload manual ke storage lokal
            return url('storage/' . $this->foto_profil);
        }
        
        // Default avatar jika user belum upload foto
        return url('assets/images/default-avatar.png');
    }

    // Implementasi JWT (Wajib jika pakai JWT)
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    // Relasi lainnya...
    public function organization() { return $this->hasOne(Organization::class); }
}