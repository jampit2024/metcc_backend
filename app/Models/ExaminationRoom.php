<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationRoom extends Model
{
    protected $fillable = [
        'examination_schedule_id',
        'room_name',
        'capacity',
        'proctor_id',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchedule::class, 'examination_schedule_id');
    }

    public function proctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proctor_id');
    }
}
