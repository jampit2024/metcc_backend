<?php

namespace App\Http\Controllers;

use App\Models\ExamQuestion;
use App\Models\ExamSubject;
use App\Models\QuestionBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectQuestionController extends Controller
{
    public function subjects(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $subjects = ExamSubject::query()
            ->withCount([
                'banks',
                'questions',
                'questions as selected_questions_count' => fn ($q) => $q->where('is_selected_for_exam', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ExamSubject $subject) => $this->mapSubject($subject));

        return response()->json(['success' => true, 'data' => $subjects]);
    }

    public function storeSubject(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', 'unique:exam_subjects,code'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $subject = ExamSubject::query()->create([
            'name' => trim($validated['name']),
            'code' => isset($validated['code']) ? strtoupper(trim($validated['code'])) : null,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ExamSubject::query()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subject created.',
            'data' => $this->mapSubject($subject->loadCount([
                'banks',
                'questions',
                'questions as selected_questions_count' => fn ($q) => $q->where('is_selected_for_exam', true),
            ])),
        ], 201);
    }

    public function showSubject(Request $request, ExamSubject $examSubject): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $examSubject->loadCount([
            'banks',
            'questions',
            'questions as selected_questions_count' => fn ($q) => $q->where('is_selected_for_exam', true),
        ]);

        $banks = $examSubject->banks()
            ->withCount([
                'questions',
                'questions as selected_questions_count' => fn ($q) => $q->where('is_selected_for_exam', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (QuestionBank $bank) => $this->mapBank($bank));

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->mapSubject($examSubject),
                'banks' => $banks,
            ],
        ]);
    }

    public function storeBank(Request $request, ExamSubject $examSubject): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('question_banks', 'name')->where(fn ($q) => $q->where('exam_subject_id', $examSubject->id)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $bank = $examSubject->banks()->create([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'status' => 'draft',
            'sort_order' => (int) $examSubject->banks()->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Question bank folder created.',
            'data' => $this->mapBank($bank->loadCount([
                'questions',
                'questions as selected_questions_count' => fn ($q) => $q->where('is_selected_for_exam', true),
            ])),
        ], 201);
    }

    public function showBank(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $questionBank->load(['subject']);
        $questionBank->loadCount([
            'questions',
            'questions as selected_questions_count' => fn ($q) => $q->where('is_selected_for_exam', true),
        ]);

        $questions = $questionBank->questions()
            ->orderByDesc('is_selected_for_exam')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ExamQuestion $question) => $this->mapQuestion($question));

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->mapBank($questionBank),
                'subject' => [
                    'id' => $questionBank->subject?->id,
                    'name' => $questionBank->subject?->name,
                    'code' => $questionBank->subject?->code,
                ],
                'questions' => $questions,
            ],
        ]);
    }

    public function storeQuestion(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $validated = $request->validate([
            'stem' => ['required', 'string', 'min:5', 'max:5000'],
            'question_type' => ['nullable', Rule::in(['multiple_choice', 'true_false'])],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:500'],
            'correct_answer' => ['required', 'string', 'max:10'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'is_selected_for_exam' => ['sometimes', 'boolean'],
        ]);

        $options = array_values(array_map('trim', $validated['options']));
        $correct = strtoupper(trim($validated['correct_answer']));
        $labels = range('A', chr(64 + count($options)));

        if (! in_array($correct, $labels, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Correct answer must match one of the option letters (A, B, C...).',
                'errors' => ['correct_answer' => ['Correct answer must be a valid option letter.']],
            ], 422);
        }

        $selected = (bool) ($validated['is_selected_for_exam'] ?? false);

        $question = $questionBank->questions()->create([
            'exam_subject_id' => $questionBank->exam_subject_id,
            'created_by' => $request->user()->id,
            'stem' => trim($validated['stem']),
            'question_type' => $validated['question_type'] ?? 'multiple_choice',
            'options' => collect($options)->map(fn ($text, $index) => [
                'key' => $labels[$index],
                'text' => $text,
            ])->all(),
            'correct_answer' => $correct,
            'difficulty' => $validated['difficulty'] ?? 'medium',
            'status' => $validated['status'] ?? 'draft',
            'is_selected_for_exam' => $selected,
            'selected_at' => $selected ? now() : null,
            'sort_order' => (int) $questionBank->questions()->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Question added.',
            'data' => $this->mapQuestion($question),
        ], 201);
    }

    public function updateQuestion(Request $request, ExamQuestion $examQuestion): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $validated = $request->validate([
            'stem' => ['sometimes', 'required', 'string', 'min:5', 'max:5000'],
            'options' => ['sometimes', 'required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:500'],
            'correct_answer' => ['sometimes', 'required', 'string', 'max:10'],
            'difficulty' => ['sometimes', Rule::in(['easy', 'medium', 'hard'])],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
            'is_selected_for_exam' => ['sometimes', 'boolean'],
        ]);

        $payload = [];

        if (isset($validated['stem'])) {
            $payload['stem'] = trim($validated['stem']);
        }
        if (isset($validated['difficulty'])) {
            $payload['difficulty'] = $validated['difficulty'];
        }
        if (isset($validated['status'])) {
            $payload['status'] = $validated['status'];
        }
        if (array_key_exists('is_selected_for_exam', $validated)) {
            $payload['is_selected_for_exam'] = (bool) $validated['is_selected_for_exam'];
            $payload['selected_at'] = $payload['is_selected_for_exam'] ? now() : null;
        }

        if (isset($validated['options'])) {
            $options = array_values(array_map('trim', $validated['options']));
            $labels = range('A', chr(64 + count($options)));
            $payload['options'] = collect($options)->map(fn ($text, $index) => [
                'key' => $labels[$index],
                'text' => $text,
            ])->all();

            $correct = strtoupper(trim($validated['correct_answer'] ?? $examQuestion->correct_answer));
            if (! in_array($correct, $labels, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Correct answer must match one of the option letters.',
                ], 422);
            }
            $payload['correct_answer'] = $correct;
        } elseif (isset($validated['correct_answer'])) {
            $payload['correct_answer'] = strtoupper(trim($validated['correct_answer']));
        }

        $examQuestion->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Question updated.',
            'data' => $this->mapQuestion($examQuestion->fresh()),
        ]);
    }

    public function toggleQuestionSelection(Request $request, ExamQuestion $examQuestion): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $next = ! $examQuestion->is_selected_for_exam;
        $examQuestion->update([
            'is_selected_for_exam' => $next,
            'selected_at' => $next ? now() : null,
            'status' => $next && $examQuestion->status === 'draft' ? 'active' : $examQuestion->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => $next ? 'Question selected for exam.' : 'Question removed from exam selection.',
            'data' => $this->mapQuestion($examQuestion->fresh()),
        ]);
    }

    public function destroyQuestion(Request $request, ExamQuestion $examQuestion): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Admin access only.'], 403);
        }

        $examQuestion->delete();

        return response()->json(['success' => true, 'message' => 'Question deleted.']);
    }

    private function mapSubject(ExamSubject $subject): array
    {
        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'description' => $subject->description,
            'is_active' => (bool) $subject->is_active,
            'banks_count' => (int) ($subject->banks_count ?? $subject->banks()->count()),
            'questions_count' => (int) ($subject->questions_count ?? $subject->questions()->count()),
            'selected_questions_count' => (int) ($subject->selected_questions_count ?? $subject->questions()->where('is_selected_for_exam', true)->count()),
        ];
    }

    private function mapBank(QuestionBank $bank): array
    {
        return [
            'id' => $bank->id,
            'exam_subject_id' => $bank->exam_subject_id,
            'name' => $bank->name,
            'description' => $bank->description,
            'status' => $bank->status,
            'questions_count' => (int) ($bank->questions_count ?? $bank->questions()->count()),
            'selected_questions_count' => (int) ($bank->selected_questions_count ?? $bank->questions()->where('is_selected_for_exam', true)->count()),
            'updated_at' => optional($bank->updated_at)?->toIso8601String(),
        ];
    }

    private function mapQuestion(ExamQuestion $question): array
    {
        return [
            'id' => $question->id,
            'question_bank_id' => $question->question_bank_id,
            'exam_subject_id' => $question->exam_subject_id,
            'stem' => $question->stem,
            'question_type' => $question->question_type,
            'options' => $question->options ?? [],
            'correct_answer' => $question->correct_answer,
            'difficulty' => $question->difficulty,
            'status' => $question->status,
            'is_selected_for_exam' => (bool) $question->is_selected_for_exam,
            'selected_at' => optional($question->selected_at)?->toIso8601String(),
        ];
    }
}
