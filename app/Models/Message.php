<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    // Mass assignment agar data bisa disimpan via Message::create()
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message',
    ];

    /**
     * Relasi ke Conversation (Satu pesan milik satu percakapan)
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Relasi ke User (Satu pesan dikirim oleh satu user)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}