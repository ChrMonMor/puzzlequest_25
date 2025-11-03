<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Run extends Model
{
    use HasFactory;

    protected $table = 'runs';
    protected $primaryKey = 'run_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'run_type',
        'run_added',
        'run_title',
        'run_description',
        'run_img_icon',
        'run_img_front',
        'run_pin',
        'run_location',
        'run_last_update',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            $model->run_added = now();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function runType()
    {
        return $this->belongsTo(RunType::class, 'run_type', 'run_type_id');
    }

    public function flags()
    {
        return $this->hasMany(Flag::class, 'run_id', 'run_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'run_id', 'run_id');
    }

    public function histories()
    {
        return $this->hasMany(History::class, 'run_id', 'run_id');
    }
}
