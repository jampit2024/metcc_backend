<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSubject extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function banks(): HasMany
    {
        return $this->hasMany(QuestionBank::class, 'exam_subject_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class, 'exam_subject_id');
    }
}
