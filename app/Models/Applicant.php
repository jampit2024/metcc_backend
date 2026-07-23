<?php

namespace App\Models;

use App\Enums\ApplicantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Applicant extends Model
{
    protected $fillable = [
        'applicant_code',
        'reference_number',
        'name',
        'email',
        'course_applied',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicantStatus::class,
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ExaminationRegistration::class);
    }
}
