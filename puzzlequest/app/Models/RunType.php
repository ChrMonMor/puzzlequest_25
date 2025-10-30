<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RunType extends Model
{
    use HasFactory;

    protected $table = 'run_types';
    protected $primaryKey = 'run_type_id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = ['run_type_name'];

    public function runs()
    {
        return $this->hasMany(Run::class, 'run_type', 'run_type_id');
    }
}
