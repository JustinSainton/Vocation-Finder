<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\VocationalCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminVocationalCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/VocationalCategories/Index', [
            'categories' => VocationalCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/VocationalCategories/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ministry_connection' => ['nullable', 'string'],
            'career_pathways' => ['nullable', 'array'],
            'sort_order' => ['required', 'integer'],
        ]);

        VocationalCategory::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return redirect('/admin/vocational-categories')->with('success', 'Category created.');
    }

    public function edit(VocationalCategory $vocationalCategory): Response
    {
        return Inertia::render('Admin/VocationalCategories/Form', [
            'category' => $vocationalCategory,
        ]);
    }

    public function update(Request $request, VocationalCategory $vocationalCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ministry_connection' => ['nullable', 'string'],
            'career_pathways' => ['nullable', 'array'],
            'sort_order' => ['required', 'integer'],
        ]);

        $vocationalCategory->update($validated);

        return redirect('/admin/vocational-categories')->with('success', 'Category updated.');
    }

    public function destroy(VocationalCategory $vocationalCategory): RedirectResponse
    {
        $vocationalCategory->delete();

        return redirect('/admin/vocational-categories')->with('success', 'Category deleted.');
    }
}
