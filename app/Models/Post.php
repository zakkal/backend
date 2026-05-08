<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    protected $fillable = ['user_id', 'caption', 'image_url'];

    // Agar link foto otomatis jadi URL lengkap
    protected $appends = ['full_image_url'];

    public function getFullImageUrlAttribute()
    {
        return $this->image_url ? url(Storage::url('posts/' . $this->image_url)) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
