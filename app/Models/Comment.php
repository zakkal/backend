<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'opportunity_id',
        'comment',
        'parent_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    // Relasi untuk mengambil balasan dari komentar ini
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    // Relasi balik (opsional): komentar ini balasannya siapa?
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
}