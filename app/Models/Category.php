<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon'];

    // Relasi balik ke Opportunity
    public function opportunities()
    {
        return $this->belongsToMany(Opportunity::class, 'category_opportunity');
    }
}