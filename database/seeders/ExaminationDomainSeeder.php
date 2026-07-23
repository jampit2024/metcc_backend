<?php

namespace Database\Seeders;

use App\Enums\ApplicantStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ExaminationStatus;
use App\Enums\ResultStatus;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\ExaminationRegistration;
use App\Models\ExaminationRoom;
use App\Models\ExaminationSchedule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class ExaminationDomainSeeder extends Seeder
{
    public function run(): void
    {
        $proctorRole = Role::where('slug', Role::SLUG_PROCTOR)->firstOrFail();
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_ADMIN))->first();

        $proctorDefs = [
            ['email' => 'proctor@example.com', 'name' => 'Maria D. Santos'],
            ['email' => 'proctor2@example.com', 'name' => 'John Mark Rivera'],
            ['email' => 'proctor3@example.com', 'name' => 'Ana L. Gonzales'],
            ['email' => 'proctor4@example.com', 'name' => 'Michael P. Tampus'],
            ['email' => 'proctor5@example.com', 'name' => 'Rosemarie J. Villa'],
        ];

        $proctors = collect($proctorDefs)->map(fn (array $def) => User::updateOrCreate(
            ['email' => $def['email']],
            [
                'role_id' => $proctorRole->id,
                'name' => $def['name'],
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        ))->values();

        ExaminationRegistration::query()->delete();
        ExaminationRoom::query()->delete();
        ExaminationSchedule::query()->delete();
        Applicant::query()->delete();
        ActivityLog::query()->delete();

        $coursePrefs = ['BSIT', 'BSED', 'BSBA', 'BSN', 'BSCrim', 'BSHM'];
        $applicants = collect();

        for ($i = 1; $i <= 480; $i++) {
            $applicants->push(Applicant::create([
                'applicant_code' => sprintf('APP-2026-%04d', $i),
                'reference_number' => sprintf('REF-2026-%04d', $i),
                'name' => fake()->name(),
                'email' => sprintf('applicant%04d@tcc-exam.test', $i),
                'course_applied' => $coursePrefs[($i - 1) % count($coursePrefs)],
                'status' => $i <= 360 ? ApplicantStatus::Confirmed : ApplicantStatus::Registered,
            ]));
        }

        $today = Carbon::today();
        $rooms = ['Room 101', 'Room 102', 'Room 103', 'Room 104', 'AVR Hall'];

        $batches = [
            [
                'batch_code' => 'Batch A',
                'exam_date' => $today->copy()->subDay(),
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
                'time_slot' => '08:00–09:00',
                'status' => ExaminationStatus::Completed,
                'expected_examinees' => 450,
                'student_slice' => [0, 90],
            ],
            [
                'batch_code' => 'Batch B',
                'exam_date' => $today,
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
                'time_slot' => '08:00–09:00',
                'status' => ExaminationStatus::Ongoing,
                'expected_examinees' => 240,
                'student_slice' => [90, 170],
            ],
            [
                'batch_code' => 'Batch C',
                'exam_date' => $today,
                'start_time' => '09:30:00',
                'end_time' => '10:30:00',
                'time_slot' => '09:30–10:30',
                'status' => ExaminationStatus::Scheduled,
                'expected_examinees' => 240,
                'student_slice' => [170, 250],
            ],
            [
                'batch_code' => 'Batch D',
                'exam_date' => $today,
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'time_slot' => '10:30–11:30',
                'status' => ExaminationStatus::Scheduled,
                'expected_examinees' => 220,
                'student_slice' => [250, 320],
            ],
            [
                'batch_code' => 'Batch E',
                'exam_date' => $today->copy()->addDay(),
                'start_time' => '09:30:00',
                'end_time' => '10:30:00',
                'time_slot' => '09:30–10:30',
                'status' => ExaminationStatus::Scheduled,
                'expected_examinees' => 500,
                'student_slice' => [320, 400],
            ],
            [
                'batch_code' => 'Batch F',
                'exam_date' => $today->copy()->addDays(2),
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
                'time_slot' => '08:00–09:00',
                'status' => ExaminationStatus::Scheduled,
                'expected_examinees' => 460,
                'student_slice' => [400, 460],
            ],
        ];

        foreach ($batches as $batch) {
            [$from, $to] = $batch['student_slice'];
            unset($batch['student_slice']);

            $schedule = ExaminationSchedule::create([
                'title' => 'Entrance Examination',
                'exam_date' => $batch['exam_date']->toDateString(),
                'start_time' => $batch['start_time'],
                'end_time' => $batch['end_time'],
                'time_slot' => $batch['time_slot'],
                'venue' => count($rooms).' available classrooms',
                'batch_code' => $batch['batch_code'],
                'course' => 'General Entrance Examination',
                'proctor_id' => $proctors[0]->id,
                'status' => $batch['status'],
                'expected_examinees' => $batch['expected_examinees'],
            ]);

            // 5 classrooms / 5 different proctors available for this time slot.
            // Students are NOT assigned to a specific room — only to this batch/time.
            $capacity = (int) ceil($batch['expected_examinees'] / count($rooms));
            foreach ($rooms as $index => $roomName) {
                ExaminationRoom::create([
                    'examination_schedule_id' => $schedule->id,
                    'room_name' => $roomName,
                    'capacity' => $capacity,
                    'proctor_id' => $proctors[$index % $proctors->count()]->id,
                ]);
            }

            $batchApplicants = $applicants->slice($from, $to - $from)->values();
            foreach ($batchApplicants as $i => $applicant) {
                $isCompleted = $batch['status'] === ExaminationStatus::Completed;
                ExaminationRegistration::create([
                    'examination_schedule_id' => $schedule->id,
                    'applicant_id' => $applicant->id,
                    'attendance_status' => $isCompleted
                        ? ($i % 8 === 0 ? AttendanceStatus::Absent : AttendanceStatus::Present)
                        : ($batch['status'] === ExaminationStatus::Ongoing
                            ? ($i % 10 === 0 ? AttendanceStatus::Absent : AttendanceStatus::Present)
                            : AttendanceStatus::Pending),
                    'result_status' => $isCompleted
                        ? ($i % 4 === 0 ? ResultStatus::Failed : ResultStatus::Passed)
                        : ResultStatus::Pending,
                    'score' => $isCompleted ? ($i % 4 === 0 ? 55 + ($i % 12) : 78 + ($i % 18)) : null,
                ]);
            }
        }

        $logs = [
            ['action' => 'schedule_created', 'description' => 'Created Batch E — Jun schedule, 09:30–10:30, 5 classrooms / 5 proctors'],
            ['action' => 'proctor_assigned', 'description' => 'Assigned 5 different proctors to available rooms for Batch C (09:30–10:30)'],
            ['action' => 'exam_started', 'description' => 'Batch B (08:00–09:00) marked Ongoing — students may use any open classroom'],
            ['action' => 'applicant_registered', 'description' => 'Registered examinees into Batch D time slot (10:30–11:30)'],
            ['action' => 'schedule_updated', 'description' => 'Clarified room policy: examinees are timed by batch, not fixed to one room'],
            ['action' => 'result_recorded', 'description' => 'Posted results for completed Batch A entrance examination'],
            ['action' => 'user_login', 'description' => ($admin?->name ?? 'Administrator').' signed in to the admin portal'],
        ];

        foreach ($logs as $offset => $log) {
            ActivityLog::create([
                'user_id' => $admin?->id,
                'action' => $log['action'],
                'description' => $log['description'],
                'created_at' => now()->subMinutes(($offset + 1) * 25),
                'updated_at' => now()->subMinutes(($offset + 1) * 25),
            ]);
        }
    }
}
