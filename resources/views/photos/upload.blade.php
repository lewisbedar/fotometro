<x-layouts.app title="Ajouter une photo - fotométro">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-black/55">Contribuer</p>
            <h1 class="mt-2 text-3xl font-semibold">Ajouter une photo</h1>
            <p class="mt-2 text-sm text-black/60">Votre photo sera examinée par un modérateur avant d'être publiée sur la fiche de la station.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('photos.upload.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div
                x-data="{ dragging: false, fileName: null, preview: null }"
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                x-on:click="$refs.fileInput.click()"
                class="flex min-h-48 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 text-center transition"
                x-bind:class="dragging ? 'border-black bg-black/10' : 'border-black/20'"
            >
                <input
                    type="file"
                    name="file"
                    x-ref="fileInput"
                    accept="image/jpeg,image/png,image/webp"
                    required
                    class="hidden"
                    x-on:change="fileName = $refs.fileInput.files[0]?.name; preview = fileName ? URL.createObjectURL($refs.fileInput.files[0]) : null"
                >
                <template x-if="preview">
                    <img :src="preview" alt="" class="max-h-48 rounded-md object-contain">
                </template>
                <p class="text-sm font-semibold" x-text="fileName ?? 'Glissez une photo ici, ou cliquez pour en choisir une'"></p>
                <p class="text-xs text-black/50">JPEG, PNG ou WebP</p>
            </div>
            @error('file') <p class="text-sm text-red-700">{{ $message }}</p> @enderror

            @include('admin.photos.partials.location-selector')

            <div class="space-y-3 rounded-lg border border-black/10 bg-black/[0.02] p-4">
                <h2 class="text-base font-semibold">Catégories</h2>
                <x-category-checklist :categories="$categories" name="photo_category_ids" :selected="old('photo_category_ids', [])" />
            </div>

            <label class="block text-sm font-semibold">Description
                <textarea name="description" class="mt-1 w-full rounded-md border border-black/15 p-2">{{ old('description') }}</textarea>
            </label>

            <button class="w-full rounded-md bg-black px-4 py-2.5 font-semibold text-white hover:bg-black/85">
                Envoyer pour modération
            </button>
        </form>
    </div>
</x-layouts.app>
