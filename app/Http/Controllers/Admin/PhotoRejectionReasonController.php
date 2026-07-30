<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoRejectionReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PhotoRejectionReasonController extends Controller
{
    public function index(): View
    {
        return view('admin.photo-rejection-reasons.index', [
            'reasons' => PhotoRejectionReason::query()->orderBy('sort_order')->orderBy('label')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.photo-rejection-reasons.form', [
            'reason' => new PhotoRejectionReason,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PhotoRejectionReason::query()->create($this->validated($request));

        return redirect()->route('admin.photo-rejection-reasons.index')->with('status', 'Motif créé.');
    }

    public function edit(PhotoRejectionReason $photoRejectionReason): View
    {
        return view('admin.photo-rejection-reasons.form', [
            'reason' => $photoRejectionReason,
        ]);
    }

    public function update(Request $request, PhotoRejectionReason $photoRejectionReason): RedirectResponse
    {
        $photoRejectionReason->update($this->validated($request, $photoRejectionReason));

        return redirect()->route('admin.photo-rejection-reasons.index')->with('status', 'Motif mis à jour.');
    }

    private function validated(Request $request, ?PhotoRejectionReason $reason = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = $data['slug'] ?: Str::slug($data['label']);
        $exists = PhotoRejectionReason::query()->where('slug', $slug)
            ->when($reason, fn ($query) => $query->whereKeyNot($reason->id))
            ->exists();

        if ($exists) {
            abort(422, 'Le slug de ce motif existe déjà.');
        }

        return [
            ...$data,
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
