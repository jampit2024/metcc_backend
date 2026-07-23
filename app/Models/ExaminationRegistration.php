<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationRegistration extends Model
{
    protected $fillable = [
        'examination_schedule_id',
        'previous_schedule_id',
        'applicant_id',
        'attendance_status',
        'result_status',
        'score',
        'reschedule_reason',
        'rescheduled_at',
        'rescheduled_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_status' => AttendanceStatus::class,
            'result_status' => ResultStatus::class,
            'score' => 'decimal:2',
            'rescheduled_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchedule::class, 'examination_schedule_id');
    }

    public function previousSchedule(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchedule::class, 'previous_schedule_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function rescheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }

    public function canReschedule(): bool
    {
        $attendance = $this->attendance_status?->value ?? $this->attendance_status;
        $result = $this->result_status?->value ?? $this->result_status;

        if ($attendance === AttendanceStatus::Present->value && $result !== ResultStatus::Pending->value) {
            return false;
        }

        return true;
    }
}
