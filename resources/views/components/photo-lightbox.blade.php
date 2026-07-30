<div
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="close()"
    x-on:keydown.arrow-right.window="open && next()"
    x-on:keydown.arrow-left.window="open && prev()"
    x-on:click.self="close()"
    class="lightbox-overlay"
>
    <div class="lightbox-panel" x-show="open" x-transition.opacity>
        <button type="button" class="lightbox-close" x-on:click="close()" aria-label="Fermer">
            <x-icons.close class="h-5 w-5" />
        </button>
        <template x-if="total > 1">
            <button type="button" class="lightbox-nav lightbox-nav-prev" x-on:click="prev()" aria-label="Photo précédente">
                <x-icons.chevron-down class="h-6 w-6 rotate-90" />
            </button>
        </template>
        <template x-if="total > 1">
            <button type="button" class="lightbox-nav lightbox-nav-next" x-on:click="next()" aria-label="Photo suivante">
                <x-icons.chevron-down class="h-6 w-6 -rotate-90" />
            </button>
        </template>
        <template x-if="photo">
            <div class="lightbox-body">
                <div class="lightbox-image-wrap">
                    <img :src="photo.image" :alt="photo.title" class="lightbox-image">
                </div>
                <div class="lightbox-info">
                    <h3 x-text="photo.title"></h3>
                    <p class="lightbox-meta" x-show="photo.category" x-text="photo.category"></p>
                    <p class="mt-3 text-sm leading-6 text-black/70" x-show="photo.description" x-text="photo.description"></p>
                    <dl class="lightbox-rights">
                        <div x-show="photo.copyright"><dt>Copyright</dt><dd x-text="photo.copyright"></dd></div>
                        <div x-show="photo.credit"><dt>Crédit</dt><dd x-text="photo.credit"></dd></div>
                        <div x-show="photo.license"><dt>Licence</dt><dd x-text="photo.license"></dd></div>
                        <div x-show="photo.takenAt"><dt>Date</dt><dd x-text="photo.takenAt"></dd></div>
                    </dl>
                    <p class="lightbox-position" x-show="total > 1" x-text="(index + 1) + ' / ' + total"></p>
                    <a :href="photo.url" class="lightbox-full-link">Voir la fiche complète</a>
                </div>
            </div>
        </template>
    </div>
</div>
