@props([
    'categories',
    'selected' => [],
    'name' => null,
    'alpineModel' => null,
])
@php
    $selectedIds = collect($selected)->map(fn ($id) => (int) $id)->all();
    $roots = $categories->whereNull('parent_id')->sortBy([['sort_order', 'asc'], ['name', 'asc']]);
    $childrenByParent = $categories->whereNotNull('parent_id')->groupBy('parent_id');
@endphp
<div {{ $attributes->class(['space-y-3']) }}>
    @foreach ($roots as $root)
        @php
            $children = ($childrenByParent[$root->id] ?? collect())->sortBy([['sort_order', 'asc'], ['name', 'asc']]);
        @endphp
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-black/45">{{ $root->name }}</p>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
                {{-- The root category itself is selectable too, for photos that only fit at that general level (no specific sub-category applies). --}}
                <label class="cursor-pointer select-none rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-black/10 has-[:checked]:bg-black has-[:checked]:text-white hover:bg-black/5 has-[:checked]:hover:bg-black">
                    <input
                        type="checkbox"
                        value="{{ $root->id }}"
                        class="sr-only"
                        @if ($alpineModel)
                            x-model="{{ $alpineModel }}"
                        @else
                            name="{{ $name }}[]"
                            @checked(in_array($root->id, $selectedIds, true))
                        @endif
                    >
                    Général
                </label>
                @foreach ($children as $child)
                    <label class="cursor-pointer select-none rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-black/10 has-[:checked]:bg-black has-[:checked]:text-white hover:bg-black/5 has-[:checked]:hover:bg-black">
                        <input
                            type="checkbox"
                            value="{{ $child->id }}"
                            class="sr-only"
                            @if ($alpineModel)
                                x-model="{{ $alpineModel }}"
                            @else
                                name="{{ $name }}[]"
                                @checked(in_array($child->id, $selectedIds, true))
                            @endif
                        >
                        {{ $child->name }}
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
