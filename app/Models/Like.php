<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    // Kita matikan updated_at karena di tabel kamu cuma ada created_at
    public $timestamps = false;
    protected static function boot() {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    protected $fillable = ['user_id', 'opportunity_id'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}