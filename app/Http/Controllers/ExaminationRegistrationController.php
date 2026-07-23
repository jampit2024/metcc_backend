<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ExaminationStatus;
use App\Enums\ResultStatus;
use App\Models\ActivityLog;
use App\Models\ExaminationRegistration;
use App\Models\ExaminationSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExaminationRegistrationController extends Controller
{
    public function reschedule(Request $request, ExaminationRegistration $registration): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $validated = $request->validate([
            'target_schedule_id' => ['required', 'integer', 'exists:examination_schedules,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $registration->load(['applicant', 'schedule']);

        $fromSchedule = $registration->schedule;
        if (! $fromSchedule) {
            return response()->json(['success' => false, 'message' => 'Current schedule not found.'], 404);
        }

        if ((int) $validated['target_schedule_id'] === (int) $registration->examination_schedule_id) {
            throw ValidationException::withMessages([
                'target_schedule_id' => 'Choose a different day or batch.',
            ]);
        }

        $attendance = $registration->attendance_status?->value ?? $registration->attendance_status;
        $result = $registration->result_status?->value ?? $registration->result_status;

        if ($attendance === AttendanceStatus::Present->value && $result !== ResultStatus::Pending->value) {
            throw ValidationException::withMessages([
                'registration' => 'This examinee already has a recorded result and cannot be rescheduled.',
            ]);
        }

        $target = ExaminationSchedule::query()->findOrFail($validated['target_schedule_id']);
        $targetStatus = $target->status?->value ?? $target->status;

        if (in_array($targetStatus, [ExaminationStatus::Completed->value, ExaminationStatus::Cancelled->value], true)) {
            throw ValidationException::withMessages([
                'target_schedule_id' => 'That batch is completed or cancelled. Pick another schedule.',
            ]);
        }

        $alreadyOnTarget = ExaminationRegistration::query()
            ->where('examination_schedule_id', $target->id)
            ->where('applicant_id', $registration->applicant_id)
            ->where('id', '!=', $registration->id)
            ->exists();

        if ($alreadyOnTarget) {
            throw ValidationException::withMessages([
                'target_schedule_id' => 'This examinee is already registered in that batch.',
            ]);
        }

        $reason = trim($validated['reason']);
        $fromLabel = $this->batchLabel($fromSchedule);
        $toLabel = $this->batchLabel($target);

        DB::transaction(function () use ($request, $registration, $target, $fromSchedule, $reason, $fromLabel, $toLabel) {
            $registration->update([
                'examination_schedule_id' => $target->id,
                'previous_schedule_id' => $fromSchedule->id,
                'reschedule_reason' => $reason,
                'rescheduled_at' => now(),
                'rescheduled_by' => $request->user()->id,
                'attendance_status' => AttendanceStatus::Pending,
                'result_status' => ResultStatus::Pending,
                'score' => null,
            ]);

            ActivityLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'student_rescheduled',
                'description' => sprintf(
                    'Rescheduled %s (%s) from %s to %s. Reason: %s',
                    $registration->applicant?->name ?: 'examinee',
                    $registration->applicant?->applicant_code ?: 'n/a',
                    $fromLabel,
                    $toLabel,
                    $reason
                ),
                'subject_type' => ExaminationRegistration::class,
                'subject_id' => $registration->id,
            ]);
        });

        $registration->refresh()->load(['applicant', 'schedule', 'previousSchedule']);

        return response()->json([
            'success' => true,
            'message' => 'Examinee moved to the new batch.',
            'data' => [
                'registration_id' => $registration->id,
                'applicant_code' => $registration->applicant?->applicant_code,
                'student_name' => $registration->applicant?->name,
                'from_schedule_id' => $fromSchedule->id,
                'from_batch_label' => $fromLabel,
                'to_schedule_id' => $target->id,
                'to_batch_label' => $toLabel,
                'reason' => $reason,
                'rescheduled_at' => optional($registration->rescheduled_at)?->toIso8601String(),
            ],
        ]);
    }

    private function batchLabel(ExaminationSchedule $schedule): string
    {
        $dateLabel = optional($schedule->exam_date)->format('M j, Y');
        $timeSlot = $schedule->time_slot
            ?: trim(
                (optional($schedule->start_time ? Carbon::parse($schedule->start_time) : null)?->format('H:i') ?? '')
                .'–'
                .(optional($schedule->end_time ? Carbon::parse($schedule->end_time) : null)?->format('H:i') ?? ''),
                '–'
            );

        return trim(($dateLabel ?: '').', '.($schedule->batch_code ?: '').' · '.($timeSlot ?: ''));
    }
}
