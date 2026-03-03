<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminQuestionCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/QuestionCategories/Index', [
            'categories' => QuestionCategory::withCount('questions')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/QuestionCategories/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'theological_basis' => ['nullable', 'string'],
            'what_it_reveals' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer'],
        ]);

        QuestionCategory::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return redirect('/admin/question-categories')->with('success', 'Category created.');
    }

    public function edit(QuestionCategory $questionCategory): Response
    {
        return Inertia::render('Admin/QuestionCategories/Form', [
            'category' => $questionCategory,
        ]);
    }

    public function update(Request $request, QuestionCategory $questionCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'theological_basis' => ['nullable', 'string'],
            'what_it_reveals' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer'],
        ]);

        $questionCategory->update($validated);

        return redirect('/admin/question-categories')->with('success', 'Category updated.');
    }

    public function destroy(QuestionCategory $questionCategory): RedirectResponse
    {
        $questionCategory->delete();

        return redirect('/admin/question-categories')->with('success', 'Category deleted.');
    }
}
