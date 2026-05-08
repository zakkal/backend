<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'created_by',
        'judul',
        'deskripsi',
        'lokasi',
        'maps_url',
        'foto',
        'tipe',
        'tanggal_mulai',
        'tanggal_selesai',
        'kuota',
        'status'
    ];

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization() {
        return $this->belongsTo(Organization::class);
    }

    public function categories() {
        return $this->belongsToMany(Category::class, 'category_opportunity');
    }

    public function likes() {
        return $this->hasMany(Like::class);
    }

    public function comments() {
        return $this->hasMany(Comment::class);
    }
}