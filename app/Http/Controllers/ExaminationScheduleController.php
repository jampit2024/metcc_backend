<?php

namespace App\Http\Controllers;

use App\Enums\ExaminationStatus;
use App\Models\ExaminationRegistration;
use App\Models\ExaminationSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExaminationScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $query = ExaminationSchedule::with(['rooms.proctor:id,name'])
            ->withCount(['rooms', 'registrations'])
            ->orderBy('exam_date')
            ->orderBy('start_time');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('upcoming')) {
            $query->whereDate('exam_date', '>=', Carbon::today()->toDateString());
        }

        $schedules = $query->get()->map(fn (ExaminationSchedule $schedule) => $this->mapSummary($schedule));

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    public function show(Request $request, ExaminationSchedule $examinationSchedule): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $examinationSchedule->load([
            'rooms.proctor:id,name,email',
            'registrations.applicant',
            'registrations.previousSchedule',
        ]);

        $students = $examinationSchedule->registrations
            ->sortBy(fn ($reg) => $reg->applicant?->name)
            ->values()
            ->map(fn ($reg) => [
                'registration_id' => $reg->id,
                'applicant_id' => $reg->applicant_id,
                'applicant_code' => $reg->applicant?->applicant_code,
                'name' => $reg->applicant?->name,
                'email' => $reg->applicant?->email,
                'course_preference' => $reg->applicant?->course_applied,
                'attendance_status' => $reg->attendance_status?->value ?? $reg->attendance_status,
                'result_status' => $reg->result_status?->value ?? $reg->result_status,
                'score' => $reg->score,
                'can_reschedule' => $reg->canReschedule(),
                'was_rescheduled' => (bool) $reg->previous_schedule_id,
                'reschedule_reason' => $reg->reschedule_reason,
                'previous_batch_label' => $reg->previousSchedule
                    ? $this->batchLabel($reg->previousSchedule)
                    : null,
                'rescheduled_at' => optional($reg->rescheduled_at)?->toIso8601String(),
            ]);

        $movedAway = ExaminationRegistration::query()
            ->with(['applicant', 'schedule'])
            ->where('previous_schedule_id', $examinationSchedule->id)
            ->orderByDesc('rescheduled_at')
            ->get()
            ->map(fn ($reg) => [
                'registration_id' => $reg->id,
                'applicant_code' => $reg->applicant?->applicant_code,
                'name' => $reg->applicant?->name,
                'email' => $reg->applicant?->email,
                'reason' => $reg->reschedule_reason,
                'moved_to_schedule_id' => $reg->examination_schedule_id,
                'moved_to_batch_label' => $reg->schedule ? $this->batchLabel($reg->schedule) : null,
                'rescheduled_at' => optional($reg->rescheduled_at)?->toIso8601String(),
            ]);

        $availableTargets = ExaminationSchedule::query()
            ->where('id', '!=', $examinationSchedule->id)
            ->whereNotIn('status', [
                ExaminationStatus::Completed->value,
                ExaminationStatus::Cancelled->value,
            ])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ExaminationSchedule $schedule) => [
                'id' => $schedule->id,
                'label' => $this->batchLabel($schedule),
                'exam_date' => optional($schedule->exam_date)->toDateString(),
                'batch_code' => $schedule->batch_code,
                'time_slot' => $schedule->time_slot,
                'status' => $schedule->status?->value ?? $schedule->status,
            ]);

        $availableRooms = $examinationSchedule->rooms->map(fn ($room) => [
            'id' => $room->id,
            'room_name' => $room->room_name,
            'capacity' => $room->capacity,
            'proctor' => $room->proctor ? [
                'id' => $room->proctor->id,
                'name' => $room->proctor->name,
                'email' => $room->proctor->email,
            ] : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->mapSummary($examinationSchedule),
                'students' => $students,
                'moved_away' => $movedAway,
                'reschedule_targets' => $availableTargets,
                'available_rooms' => $availableRooms,
                'note' => 'Students are scheduled by time slot only. They may take the exam in any available classroom during this batch. Use Reschedule when an examinee cannot attend this day for a valid reason.',
            ],
        ]);
    }

    private function mapSummary(ExaminationSchedule $schedule): array
    {
        $rooms = $schedule->rooms ?? collect();
        $proctors = $rooms->map(fn ($room) => $room->proctor)
            ->filter()
            ->unique('id')
            ->values();

        $dateLabel = optional($schedule->exam_date)->format('M j, Y');
        $timeSlot = $schedule->time_slot
            ?: trim(($this->formatTime($schedule->start_time) ?? '').'–'.($this->formatTime($schedule->end_time) ?? ''), '–');

        return [
            'id' => $schedule->id,
            'title' => $schedule->title ?: 'Entrance Examination',
            'exam_date' => optional($schedule->exam_date)->toDateString(),
            'date_label' => $dateLabel,
            'batch_code' => $schedule->batch_code,
            'batch_label' => trim(($dateLabel ?: '').', '.($schedule->batch_code ?: '')),
            'start_time' => $this->formatTime($schedule->start_time),
            'end_time' => $this->formatTime($schedule->end_time),
            'time_slot' => $timeSlot,
            'course' => $schedule->course ?: 'General Entrance Examination',
            'status' => $schedule->status?->value ?? $schedule->status,
            'expected_examinees' => (int) $schedule->expected_examinees,
            'registered_count' => (int) ($schedule->registrations_count ?? $schedule->registrations()->count()),
            'room_count' => (int) ($schedule->rooms_count ?? $rooms->count()),
            'rooms_label' => $rooms->pluck('room_name')->filter()->implode(', '),
            'proctor_count' => $proctors->count(),
            'proctor_names' => $proctors->pluck('name')->all(),
            'display_name' => trim(($dateLabel ?: '').' · '.($schedule->batch_code ?: '').' · '.($timeSlot ?: '')),
        ];
    }

    private function batchLabel(ExaminationSchedule $schedule): string
    {
        $dateLabel = optional($schedule->exam_date)->format('M j, Y');
        $timeSlot = $schedule->time_slot
            ?: trim(($this->formatTime($schedule->start_time) ?? '').'–'.($this->formatTime($schedule->end_time) ?? ''), '–');

        return trim(($dateLabel ?: '').', '.($schedule->batch_code ?: '').' · '.($timeSlot ?: ''));
    }

    private function formatTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable) {
            return (string) $time;
        }
    }
}
