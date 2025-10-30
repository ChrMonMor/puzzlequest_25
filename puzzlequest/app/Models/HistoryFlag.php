<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryFlag extends Model
{
    use HasFactory;

    protected $table = 'history_flags';
    protected $primaryKey = 'history_flag_id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'history_id',
        'history_flag_reached',
        'history_flag_long',
        'history_flag_lat',
        'history_flag_distance',
        'history_flag_type',
        'history_flag_point',
    ];

    public function history()
    {
        return $this->belongsTo(History::class, 'history_id', 'history_id');
    }
}
