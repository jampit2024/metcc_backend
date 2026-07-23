<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ResultStatus;
use App\Models\ExaminationRegistration;
use App\Models\ExaminationSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExamResultController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $query = ExaminationRegistration::query()
            ->with([
                'applicant:id,applicant_code,name,email,course_applied',
                'schedule:id,title,exam_date,start_time,end_time,time_slot,batch_code,status',
            ]);

        if ($scheduleId = $request->query('schedule_id')) {
            $query->where('examination_schedule_id', $scheduleId);
        }

        if ($date = $request->query('date')) {
            $query->whereHas('schedule', fn ($q) => $q->whereDate('exam_date', $date));
        }

        if ($batch = $request->query('batch')) {
            $query->whereHas('schedule', fn ($q) => $q->where('batch_code', $batch));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->whereHas('applicant', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('applicant_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($outcome = $request->query('outcome')) {
            $this->applyOutcomeFilter($query, $outcome);
        }

        $rows = $query->get()
            ->map(fn (ExaminationRegistration $registration) => $this->mapRow($registration))
            ->sortBy([
                fn ($row) => $row['exam_date'] ?? '',
                fn ($row) => $row['time_slot'] ?? '',
                fn ($row) => mb_strtolower($row['student_name'] ?? ''),
            ])
            ->values();

        // Newest exam days first for complaint review, then time slot, then name.
        $rows = $rows->sort(function (array $a, array $b) {
            $dateCmp = strcmp((string) ($b['exam_date'] ?? ''), (string) ($a['exam_date'] ?? ''));
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            $timeCmp = strcmp((string) ($a['time_slot'] ?? ''), (string) ($b['time_slot'] ?? ''));
            if ($timeCmp !== 0) {
                return $timeCmp;
            }

            return strcasecmp((string) ($a['student_name'] ?? ''), (string) ($b['student_name'] ?? ''));
        })->values();

        $summary = [
            'total' => $rows->count(),
            'passed' => $rows->where('outcome', 'passed')->count(),
            'failed' => $rows->where('outcome', 'failed')->count(),
            'absent' => $rows->where('outcome', 'absent')->count(),
            'pending' => $rows->where('outcome', 'pending')->count(),
            'average_score' => round((float) $rows->whereNotNull('score')->avg('score'), 1),
        ];

        $filters = [
            'dates' => ExaminationSchedule::query()
                ->select('exam_date')
                ->distinct()
                ->orderByDesc('exam_date')
                ->pluck('exam_date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->values(),
            'batches' => ExaminationSchedule::query()
                ->select('id', 'batch_code', 'exam_date', 'time_slot', 'start_time', 'end_time')
                ->orderByDesc('exam_date')
                ->orderBy('start_time')
                ->get()
                ->map(fn (ExaminationSchedule $schedule) => [
                    'id' => $schedule->id,
                    'batch_code' => $schedule->batch_code,
                    'exam_date' => optional($schedule->exam_date)->toDateString(),
                    'date_label' => optional($schedule->exam_date)->format('M j, Y'),
                    'time_slot' => $schedule->time_slot,
                    'label' => trim(optional($schedule->exam_date)->format('M j, Y').', '.$schedule->batch_code.' · '.$schedule->time_slot),
                ]),
        ];

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'summary' => $summary,
                'filters' => $filters,
            ],
        ]);
    }

    private function applyOutcomeFilter($query, string $outcome): void
    {
        match ($outcome) {
            'absent' => $query->where('attendance_status', AttendanceStatus::Absent),
            'passed' => $query->where('attendance_status', '!=', AttendanceStatus::Absent)
                ->where('result_status', ResultStatus::Passed),
            'failed' => $query->where('attendance_status', '!=', AttendanceStatus::Absent)
                ->where('result_status', ResultStatus::Failed),
            'pending' => $query->where('attendance_status', '!=', AttendanceStatus::Absent)
                ->where('result_status', ResultStatus::Pending),
            default => null,
        };
    }

    private function mapRow(ExaminationRegistration $registration): array
    {
        $schedule = $registration->schedule;
        $applicant = $registration->applicant;
        $attendance = $registration->attendance_status?->value ?? $registration->attendance_status;
        $result = $registration->result_status?->value ?? $registration->result_status;

        $outcome = $attendance === AttendanceStatus::Absent->value
            ? 'absent'
            : ($result ?: 'pending');

        $dateLabel = optional($schedule?->exam_date)->format('M j, Y');

        return [
            'id' => $registration->id,
            'schedule_id' => $registration->examination_schedule_id,
            'applicant_code' => $applicant?->applicant_code,
            'student_name' => $applicant?->name,
            'email' => $applicant?->email,
            'course_preference' => $applicant?->course_applied,
            'exam_title' => $schedule?->title ?: 'Entrance Examination',
            'exam_date' => optional($schedule?->exam_date)->toDateString(),
            'date_label' => $dateLabel,
            'batch_code' => $schedule?->batch_code,
            'batch_label' => trim(($dateLabel ?: '').', '.($schedule?->batch_code ?: '')),
            'time_slot' => $schedule?->time_slot,
            'attendance_status' => $attendance,
            'result_status' => $result,
            'outcome' => $outcome,
            'outcome_label' => match ($outcome) {
                'passed' => 'Pass',
                'failed' => 'Fail',
                'absent' => 'Absent',
                default => 'Pending',
            },
            'score' => $registration->score !== null ? (float) $registration->score : null,
            'display_score' => $registration->score !== null
                ? rtrim(rtrim(number_format((float) $registration->score, 2, '.', ''), '0'), '.')
                : '—',
        ];
    }
}
