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
        'google_id',    // TAMBAHKAN INI
        'foto_profil', 
        'bio', 
        'lokasi'
    ];
    

    public function organization()
    {
        return $this->hasOne(Organization::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'user_skills');
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function opportunities()
{
    return $this->hasMany(Opportunity::class);
}

    // Implementasi JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Casting atribut ke tipe data tertentu.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean', // Tambahkan ini agar lebih konsisten
        ];
    }
}