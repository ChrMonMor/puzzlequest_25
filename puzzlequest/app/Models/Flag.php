<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flag extends Model
{
    use HasFactory;

    protected $table = 'flags';
    protected $primaryKey = 'flag_id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'flag_number',
        'flag_long',
        'flag_lat',
    ];

    public function run()
    {
        return $this->belongsTo(Run::class, 'run_id', 'run_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'flag_id', 'flag_id');
    }
}
