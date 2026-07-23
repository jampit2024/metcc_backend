<?php

namespace Database\Seeders;

use App\Models\ExamQuestion;
use App\Models\ExamSubject;
use App\Models\QuestionBank;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubjectQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'rommeljampit21@gmail.com')->first()
            ?? User::query()->first();

        $subjects = [
            ['name' => 'Mathematics', 'code' => 'MATH', 'description' => 'Numbers, algebra, geometry, and problem solving for entrance exam.'],
            ['name' => 'English', 'code' => 'ENG', 'description' => 'Reading comprehension, grammar, and vocabulary.'],
            ['name' => 'Science', 'code' => 'SCI', 'description' => 'General science concepts for college readiness.'],
            ['name' => 'General Knowledge', 'code' => 'GK', 'description' => 'Current events, civics, and general information.'],
            ['name' => 'Abstract Reasoning', 'code' => 'ABS', 'description' => 'Pattern recognition and logical thinking.'],
            ['name' => 'Filipino', 'code' => 'FIL', 'description' => 'Wika, gramatika, at pag-unawa sa teksto.'],
        ];

        foreach ($subjects as $index => $item) {
            $subject = ExamSubject::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );

            $bank = QuestionBank::query()->updateOrCreate(
                [
                    'exam_subject_id' => $subject->id,
                    'name' => $item['name'].' Bank 2026',
                ],
                [
                    'description' => 'Starter question folder for '.$item['name'].'.',
                    'status' => 'draft',
                    'sort_order' => 1,
                ]
            );

            if ($bank->questions()->exists()) {
                continue;
            }

            $samples = $this->sampleQuestions($item['code']);
            foreach ($samples as $qIndex => $sample) {
                ExamQuestion::query()->create([
                    'question_bank_id' => $bank->id,
                    'exam_subject_id' => $subject->id,
                    'created_by' => $admin?->id,
                    'stem' => $sample['stem'],
                    'question_type' => 'multiple_choice',
                    'options' => $sample['options'],
                    'correct_answer' => $sample['correct'],
                    'difficulty' => $sample['difficulty'],
                    'status' => 'active',
                    'is_selected_for_exam' => $qIndex === 0,
                    'selected_at' => $qIndex === 0 ? now() : null,
                    'sort_order' => $qIndex + 1,
                ]);
            }
        }
    }

    private function sampleQuestions(string $code): array
    {
        return match ($code) {
            'MATH' => [
                [
                    'stem' => 'What is 15% of 200?',
                    'options' => [
                        ['key' => 'A', 'text' => '20'],
                        ['key' => 'B', 'text' => '25'],
                        ['key' => 'C', 'text' => '30'],
                        ['key' => 'D', 'text' => '35'],
                    ],
                    'correct' => 'C',
                    'difficulty' => 'easy',
                ],
                [
                    'stem' => 'If 3x + 6 = 21, what is x?',
                    'options' => [
                        ['key' => 'A', 'text' => '3'],
                        ['key' => 'B', 'text' => '5'],
                        ['key' => 'C', 'text' => '7'],
                        ['key' => 'D', 'text' => '9'],
                    ],
                    'correct' => 'B',
                    'difficulty' => 'medium',
                ],
            ],
            'ENG' => [
                [
                    'stem' => 'Choose the correct word: She ____ to school every day.',
                    'options' => [
                        ['key' => 'A', 'text' => 'go'],
                        ['key' => 'B', 'text' => 'goes'],
                        ['key' => 'C', 'text' => 'going'],
                        ['key' => 'D', 'text' => 'gone'],
                    ],
                    'correct' => 'B',
                    'difficulty' => 'easy',
                ],
            ],
            default => [
                [
                    'stem' => 'Sample entrance exam question for '.$code.'. Which option is correct?',
                    'options' => [
                        ['key' => 'A', 'text' => 'Option A'],
                        ['key' => 'B', 'text' => 'Option B'],
                        ['key' => 'C', 'text' => 'Option C'],
                        ['key' => 'D', 'text' => 'Option D'],
                    ],
                    'correct' => 'A',
                    'difficulty' => 'easy',
                ],
            ],
        };
    }
}
