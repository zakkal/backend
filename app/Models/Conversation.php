<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user1_id',
        'user2_id',
    ];

    /**
     * Relasi ke pesan-pesan dalam percakapan ini
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Relasi ke User pertama
     */
    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /**
     * Relasi ke User kedua
     */
    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }
}