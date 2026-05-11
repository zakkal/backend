<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    // Beritahu Laravel kalau tabel ini GAK ADA kolom updated_at
    const UPDATED_AT = null; 

    protected $fillable = [
        'user_id', 
        'opportunity_id'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function opportunity() {
        return $this->belongsTo(Opportunity::class);
    }
}