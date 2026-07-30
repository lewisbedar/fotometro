<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PhotoCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.photo-categories.index', [
            'roots' => PhotoCategory::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.photo-categories.form', [
            'category' => new PhotoCategory,
            'parents' => PhotoCategory::query()->whereNull('parent_id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PhotoCategory::query()->create($this->validated($request));

        return redirect()->route('admin.photo-categories.index')->with('status', 'Catégorie créée.');
    }

    public function edit(PhotoCategory $photoCategory): View
    {
        return view('admin.photo-categories.form', [
            'category' => $photoCategory,
            'parents' => PhotoCategory::query()->whereKeyNot($photoCategory->id)->whereNull('parent_id')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, PhotoCategory $photoCategory): RedirectResponse
    {
        $photoCategory->update($this->validated($request, $photoCategory));

        return redirect()->route('admin.photo-categories.index')->with('status', 'Catégorie mise à jour.');
    }

    public function destroy(PhotoCategory $photoCategory): RedirectResponse
    {
        if ($photoCategory->children()->exists()) {
            return back()->withErrors(['category' => 'Cette catégorie a des sous-catégories : supprimez-les ou déplacez-les avant de la supprimer.']);
        }

        $name = $photoCategory->name;
        $photoCategory->delete();

        return redirect()->route('admin.photo-categories.index')->with('status', "Catégorie « {$name} » supprimée.");
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:photo_categories,id'],
        ]);

        foreach ($data['ids'] as $index => $id) {
            PhotoCategory::query()->whereKey($id)->update(['sort_order' => $index]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function validated(Request $request, ?PhotoCategory $category = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:photo_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (($data['parent_id'] ?? null) && $category && (int) $data['parent_id'] === $category->id) {
            abort(422, 'Une catégorie ne peut pas être sa propre parente.');
        }

        $slug = $data['slug'] ?: Str::slug($data['name']);
        $exists = PhotoCategory::query()->where('slug', $slug)
            ->when($category, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();

        if ($exists) {
            abort(422, 'Le slug de catégorie existe déjà.');
        }

        return [
            ...$data,
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
