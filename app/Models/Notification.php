<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * Nama tabel (Opsional jika nama tabelnya sudah 'notifications')
     */
    protected $table = 'notifications';

    /**
     * Kolom yang boleh diisi secara massal.
     * Disesuaikan dengan database bahasa Indonesia kamu.
     */
    protected $fillable = [
        'user_id', 
        'judul', 
        'isi', 
        'is_read'
    ];

    /**
     * Casting tipe data.
     * Ini penting agar 'is_read' di Flutter terbaca sebagai Boolean (true/false), bukan 1/0.
     */
    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Relasi ke User (Siapa penerima notifikasi ini)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope untuk mengambil notifikasi yang belum dibaca saja.
     * Contoh penggunaan: Notification::unread()->get();
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}