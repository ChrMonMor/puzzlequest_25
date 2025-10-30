<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Question extends Model
{
    use HasFactory;

    protected $table = 'questions';
    protected $primaryKey = 'question_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'flag_id',
        'question_type',
        'question_text',
        'question_answer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function run()
    {
        return $this->belongsTo(Run::class, 'run_id', 'run_id');
    }

    public function flag()
    {
        return $this->belongsTo(Flag::class, 'flag_id', 'flag_id');
    }

    public function questionType()
    {
        return $this->belongsTo(QuestionType::class, 'question_type', 'question_type_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class, 'question_id', 'question_id');
    }
}
