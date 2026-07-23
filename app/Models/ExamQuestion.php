<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    protected $fillable = [
        'question_bank_id',
        'exam_subject_id',
        'created_by',
        'stem',
        'question_type',
        'options',
        'correct_answer',
        'difficulty',
        'status',
        'is_selected_for_exam',
        'selected_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_selected_for_exam' => 'boolean',
            'selected_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class, 'exam_subject_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
