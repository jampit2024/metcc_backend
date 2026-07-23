<?php

namespace App\Models;

use App\Enums\ExaminationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaminationSchedule extends Model
{
    protected $fillable = [
        'title',
        'exam_date',
        'start_time',
        'end_time',
        'time_slot',
        'venue',
        'batch_code',
        'course',
        'proctor_id',
        'status',
        'expected_examinees',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'status' => ExaminationStatus::class,
            'expected_examinees' => 'integer',
        ];
    }

    public function proctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proctor_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ExaminationRoom::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ExaminationRegistration::class);
    }
}
