<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.categories', [
            'editing' => $request->filled('id')
                ? Category::query()->findOrFail($request->integer('id'))
                : null,
            'showForm' => $request->query('action') === 'new' || $request->filled('id'),
            'categories' => Category::query()
                ->withCount('products')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:0'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $category = ! empty($data['id'])
            ? Category::query()->findOrFail($data['id'])
            : new Category;
        $slug = Str::slug($data['slug'] ?: $data['name']);
        validator(['slug' => $slug], [
            'slug' => ['required', 'max:120', Rule::unique('categories', 'slug')->ignore($category->id)],
        ])->validate();

        $category->fill([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ])->save();

        return redirect()->route('admin.categories')->with(
            'success',
            $category->wasRecentlyCreated ? 'Category added.' : 'Category updated.'
        );
    }

    public function delete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:categories,id'],
        ]);
        $category = Category::query()->findOrFail($data['id']);

        if ($category->products()->exists()) {
            return back()->with('error', 'Move or delete the products in this category first.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
