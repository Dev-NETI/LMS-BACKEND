<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Question::with(['createdBy', 'options']);

        // Apply filters
        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('difficulty')) {
            $query->byDifficulty($request->difficulty);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 50);
        $questions = $query->ordered()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $questions->items(),
            'total' => $questions->total(),
            'page' => $questions->currentPage(),
            'per_page' => $questions->perPage(),
            'last_page' => $questions->lastPage(),
        ]);
    }

    public function getQuestionsByCourse($courseId, Request $request): JsonResponse
    {
        $query = Question::where('course_id', $courseId)
            ->with(['createdBy', 'options']);

        // Apply filters
        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('difficulty')) {
            $query->byDifficulty($request->difficulty);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 50);
        $questions = $query->ordered()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $questions->items(),
            'total' => $questions->total(),
            'page' => $questions->currentPage(),
            'per_page' => $questions->perPage(),
            'last_page' => $questions->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer|exists:main_db.tblcourses,courseid',
            'question_text' => 'required|string|max:2000',
            'question_type' => 'required|in:multiple_choice,checkbox,identification',
            'points' => 'required|numeric|min:0.5',
            'explanation' => 'nullable|string|max:1000',
            'difficulty' => 'required|in:easy,medium,hard',
            'correct_answer' => 'required_if:question_type,identification|nullable|string|max:500',
            'options' => 'required_if:question_type,multiple_choice,checkbox|array|min:2',
            'options.*.text' => 'required|string|max:500',
            'options.*.is_correct' => 'required|boolean',
            'order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Additional validation for question types
        if (in_array($validated['question_type'], ['multiple_choice', 'checkbox'])) {
            $correctCount = collect($validated['options'])->where('is_correct', true)->count();
            
            if ($validated['question_type'] === 'multiple_choice' && $correctCount !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Multiple choice questions must have exactly one correct answer'
                ], 422);
            }
            
            if ($validated['question_type'] === 'checkbox' && $correctCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkbox questions must have at least one correct answer'
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            // Auto-increment order if not provided
            $order = $validated['order'] ?? $this->getNextOrder($validated['course_id']);

            $question = Question::create([
                'course_id' => $validated['course_id'],
                'question_text' => $validated['question_text'],
                'question_type' => $validated['question_type'],
                'points' => $validated['points'],
                'explanation' => $validated['explanation'],
                'difficulty' => $validated['difficulty'],
                'correct_answer' => $validated['correct_answer'] ?? null,
                'order' => $order,
                'created_by_user_id' => Auth::id(),
                'is_active' => true,
            ]);

            // Create options for multiple choice and checkbox questions
            if (isset($validated['options'])) {
                foreach ($validated['options'] as $index => $optionData) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'text' => $optionData['text'],
                        'is_correct' => $optionData['is_correct'],
                        'order' => $index,
                    ]);
                }
            }

            DB::commit();

            $question->load(['createdBy', 'options']);

            return response()->json([
                'success' => true,
                'message' => 'Question created successfully',
                'data' => $question
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create question', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create question'
            ], 500);
        }
    }

    public function show(Question $question): JsonResponse
    {
        $question->load(['createdBy', 'options']);
        
        return response()->json([
            'success' => true,
            'data' => $question
        ]);
    }

    public function update(Request $request, Question $question): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question_text' => 'sometimes|required|string|max:2000',
            'points' => 'sometimes|required|numeric|min:0.5',
            'explanation' => 'nullable|string|max:1000',
            'difficulty' => 'sometimes|required|in:easy,medium,hard',
            'correct_answer' => 'nullable|string|max:500',
            'options' => 'array|min:2',
            'options.*.text' => 'required|string|max:500',
            'options.*.is_correct' => 'required|boolean',
            'order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Additional validation for question types
        if (isset($validated['options'])) {
            $correctCount = collect($validated['options'])->where('is_correct', true)->count();
            
            if ($question->question_type === 'multiple_choice' && $correctCount !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Multiple choice questions must have exactly one correct answer'
                ], 422);
            }
            
            if ($question->question_type === 'checkbox' && $correctCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkbox questions must have at least one correct answer'
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $question->update($validated);

            // Update options if provided
            if (isset($validated['options'])) {
                // Delete existing options
                $question->options()->delete();
                
                // Create new options
                foreach ($validated['options'] as $index => $optionData) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'text' => $optionData['text'],
                        'is_correct' => $optionData['is_correct'],
                        'order' => $index,
                    ]);
                }
            }

            DB::commit();

            $question->load(['createdBy', 'options']);

            return response()->json([
                'success' => true,
                'message' => 'Question updated successfully',
                'data' => $question
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update question', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update question'
            ], 500);
        }
    }

    public function destroy(Question $question): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete associated options
            $question->options()->delete();
            
            // Delete the question
            $question->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Question deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to delete question', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete question'
            ], 500);
        }
    }

    public function updateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'updates' => 'required|array',
            'updates.*.id' => 'required|integer|exists:questions,id',
            'updates.*.order' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->updates as $update) {
                Question::where('id', $update['id'])
                    ->update(['order' => $update['order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Question order updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update question order'
            ], 500);
        }
    }

    public function getNextOrderForCourse($courseId): JsonResponse
    {
        $nextOrder = $this->getNextOrder($courseId);

        return response()->json([
            'success' => true,
            'nextOrder' => $nextOrder
        ]);
    }

    public function duplicate(Question $question): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Create duplicate question
            $duplicate = $question->replicate();
            $duplicate->question_text = $question->question_text . ' (Copy)';
            $duplicate->order = $this->getNextOrder($question->course_id);
            $duplicate->created_by_user_id = Auth::id();
            $duplicate->save();

            // Duplicate options if they exist
            foreach ($question->options as $option) {
                $duplicateOption = $option->replicate();
                $duplicateOption->question_id = $duplicate->id;
                $duplicateOption->save();
            }

            DB::commit();

            $duplicate->load(['createdBy', 'options']);

            return response()->json([
                'success' => true,
                'message' => 'Question duplicated successfully',
                'data' => $duplicate
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to duplicate question', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate question'
            ], 500);
        }
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question_ids' => 'required|array',
            'question_ids.*' => 'integer|exists:questions,id',
            'updates' => 'required|array',
            'updates.difficulty' => 'sometimes|in:easy,medium,hard',
            'updates.is_active' => 'sometimes|boolean',
            'updates.points' => 'sometimes|numeric|min:0.5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updatedCount = Question::whereIn('id', $request->question_ids)
                ->update($request->updates);

            return response()->json([
                'success' => true,
                'message' => 'Questions updated successfully',
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to bulk update questions', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update questions'
            ], 500);
        }
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question_ids' => 'required|array',
            'question_ids.*' => 'integer|exists:questions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Delete options for all questions
            QuestionOption::whereIn('question_id', $request->question_ids)->delete();
            
            // Delete questions
            $deletedCount = Question::whereIn('id', $request->question_ids)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Questions deleted successfully',
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to bulk delete questions', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete questions'
            ], 500);
        }
    }

    private function getNextOrder($courseId): int
    {
        $maxOrder = Question::where('course_id', $courseId)
            ->where('is_active', true)
            ->max('order');

        return ($maxOrder ?? -1) + 1;
    }
}