<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    // Tambahkan baris ini agar Laravel izinkan simpan data
    protected $fillable = [
        'user_id',
        'opportunity_id',
        'comment',
        'parent_id',
    ];

    // Relasi ke User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke Opportunity
    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    // Relasi untuk Balasan (Replies)
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
