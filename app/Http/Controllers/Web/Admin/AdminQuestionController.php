<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminQuestionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Question::with('category:id,name');

        $sortBy = $request->input('sort', 'sort_order');
        $sortDir = $request->input('dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        return Inertia::render('Admin/Questions/Index', [
            'questions' => $query->paginate(30)->withQueryString(),
            'sort' => ['by' => $sortBy, 'dir' => $sortDir],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Questions/Form', [
            'categories' => QuestionCategory::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:question_categories,id'],
            'question_text' => ['required', 'string'],
            'conversation_prompt' => ['nullable', 'string'],
            'follow_up_prompts' => ['nullable', 'array'],
            'sort_order' => ['required', 'integer'],
        ]);

        Question::create($validated);

        return redirect('/admin/questions')->with('success', 'Question created.');
    }

    public function edit(Question $question): Response
    {
        return Inertia::render('Admin/Questions/Form', [
            'question' => [
                'id' => $question->id,
                'category_id' => $question->category_id,
                'question_text' => $question->question_text,
                'conversation_prompt' => $question->conversation_prompt,
                'follow_up_prompts' => $question->follow_up_prompts,
                'sort_order' => $question->sort_order,
            ],
            'categories' => QuestionCategory::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:question_categories,id'],
            'question_text' => ['required', 'string'],
            'conversation_prompt' => ['nullable', 'string'],
            'follow_up_prompts' => ['nullable', 'array'],
            'sort_order' => ['required', 'integer'],
        ]);

        $question->update($validated);

        return redirect('/admin/questions')->with('success', 'Question updated.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $question->delete();

        return redirect('/admin/questions')->with('success', 'Question deleted.');
    }
}
