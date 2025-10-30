<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionType extends Model
{
    use HasFactory;

    protected $table = 'question_types';
    protected $primaryKey = 'question_type_id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = ['question_type_name'];

    public function questions()
    {
        return $this->hasMany(Question::class, 'question_type', 'question_type_id');
    }
}
