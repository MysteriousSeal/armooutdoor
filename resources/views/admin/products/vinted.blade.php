@extends('layouts.admin')

@section('title', 'Vinted listing')

@section('content')
    @php
        $priceValue = old('price', $listing->priceInput());
        $titleValue = old('title', $listing->title);
        $descriptionValue = old('description', $listing->description);
    @endphp

    <div class="admin-list-page vinted-page">
        <header class="admin-list-hero vinted-hero">
            <div class="admin-list-hero-row">
                <div class="vinted-hero-main">
                    {{-- Le trajet plutôt que le nom seul : cette page prend ce
                         qui est dans le catalogue et l'emmène ailleurs, et
                         c'est la seule chose qu'il faut comprendre en
                         arrivant. --}}
                    <p class="admin-list-kicker vinted-hero-kicker">
                        <span>Catalog</span>
                        <svg viewBox="0 0 24 10" width="26" height="10" aria-hidden="true" focusable="false">
                            <path d="M0 5h20m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="vinted-hero-dest">Vinted</span>
                    </p>
                    <h2 class="admin-list-title">Vinted listing</h2>
                    <p class="admin-list-lede vinted-hero-lede">
                        {{-- Dire ce que la page fait et ce qu'elle ne fait pas :
                             sans cela on attend un dépôt qui n'arrive jamais. --}}
                        Write it once, keep it between two postings, and carry it across field by field.
                        <strong>Nothing is sent from this page.</strong>
                    </p>
                </div>
                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-secondary vinted-hero-back">Back to product</a>
            </div>
        </header>

        {{-- De quel article on parle. Deux annonces ouvertes côte à côte se
             ressemblent, et la photo du catalogue est ce qui les sépare le
             plus vite. --}}
        <div class="vinted-product">
            @if (filled($product->image))
                <img src="{{ $product->imageUrl() }}" alt="" width="52" height="52" class="vinted-product-thumb" loading="lazy">
            @else
                <span class="vinted-product-thumb is-empty" aria-hidden="true"></span>
            @endif

            <div class="vinted-product-main">
                <a href="{{ route('admin.products.edit', $product) }}" class="vinted-product-name">{{ $product->localizedName() }}</a>
                <span class="vinted-product-meta">
                    <span class="vinted-product-sku">{{ $product->sku }}</span>
                    <span>Shop price <strong>{{ format_euros($product->price_cents) }}</strong></span>
                    {{-- Le coût d'achat en face du prix : c'est l'écart entre
                         les deux qui décide de ce qu'on accepte sur Vinted. --}}
                    <span>
                        Avg cost
                        @if ($costCents === null)
                            <strong title="No purchase history for this product">—</strong>
                        @else
                            <strong>{{ format_euros($costCents) }}</strong>
                        @endif
                    </span>
                    <span>{{ number_format($product->quantity) }} in stock</span>
                </span>
            </div>

            <span class="vinted-state {{ $listing->exists ? 'is-saved' : '' }}">
                @if ($listing->exists)
                    {{-- La locale de l'application est le français, celle de
                         la boutique ; le back-office, lui, est en anglais de
                         bout en bout. On la nomme plutôt que de la laisser
                         suivre celle du site. --}}
                    Saved {{ $listing->updated_at->locale('en')->diffForHumans() }}
                @else
                    Not written yet
                @endif
            </span>
        </div>

        <form
            method="POST"
            action="{{ route('admin.products.vinted.update', $product) }}"
            enctype="multipart/form-data"
            class="vinted-form"
        >
            @csrf
            @method('PUT')

            {{-- Numérotées parce que c'en est une : ce sont les champs du
                 formulaire de Vinted, dans l'ordre où il les demande. On
                 descend la page en le remplissant. --}}
            <ol class="vinted-steps">
                <li class="vinted-step">
                    <div class="vinted-step-head">
                        <span class="vinted-step-number" aria-hidden="true">1</span>
                        <div>
                            <label class="vinted-step-label" for="vinted-title">Title</label>
                            <p class="vinted-step-hint">Vinted cuts it around 60 characters on a phone.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary vinted-copy" data-copy-from="vinted-title">Copy</button>
                    </div>

                    <input
                        id="vinted-title"
                        type="text"
                        name="title"
                        value="{{ $titleValue }}"
                        maxlength="255"
                        placeholder="Cagoule camo respirante — taille unique"
                        class="form-control vinted-input @error('title') is-invalid @enderror"
                        data-counter-for="vinted-title-count"
                        data-counter-ideal="60"
                    >
                    <p class="vinted-count"><span id="vinted-title-count"></span></p>
                    @error('title')<p class="form-error">{{ $message }}</p>@enderror
                </li>

                <li class="vinted-step">
                    <div class="vinted-step-head">
                        <span class="vinted-step-number" aria-hidden="true">2</span>
                        <div>
                            <label class="vinted-step-label" for="vinted-description">Description</label>
                            <p class="vinted-step-hint">Plain text. Vinted shows no formatting, so line breaks are all you get.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary vinted-copy" data-copy-from="vinted-description">Copy</button>
                    </div>

                    <textarea
                        id="vinted-description"
                        name="description"
                        rows="10"
                        maxlength="5000"
                        placeholder="État, taille réelle, matière, ce qui se voit à l'usage."
                        class="form-control vinted-textarea @error('description') is-invalid @enderror"
                        data-counter-for="vinted-description-count"
                    >{{ $descriptionValue }}</textarea>
                    <p class="vinted-count"><span id="vinted-description-count"></span></p>
                    @error('description')<p class="form-error">{{ $message }}</p>@enderror
                </li>

                <li class="vinted-step">
                    <div class="vinted-step-head">
                        <span class="vinted-step-number" aria-hidden="true">3</span>
                        <div>
                            <label class="vinted-step-label" for="vinted-price">Price</label>
                            {{-- Vinted attend une virgule et pas de symbole :
                                 le bouton copie ce que son champ accepte, pas
                                 ce que la page affiche. --}}
                            <p class="vinted-step-hint">Copied with a comma and no symbol, the way Vinted's own field wants it.</p>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-secondary vinted-copy"
                            data-copy-from="vinted-price"
                            data-copy-decimal-comma
                        >Copy</button>
                    </div>

                    <div class="vinted-price-row">
                        <div class="vinted-price-field">
                            <input
                                id="vinted-price"
                                type="number"
                                name="price"
                                value="{{ $priceValue }}"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="form-control vinted-input @error('price') is-invalid @enderror"
                            >
                            <span class="vinted-price-unit" aria-hidden="true">€</span>
                        </div>

                        {{-- Les deux bornes en regard : au-dessus du coût on
                             gagne, au-dessus du prix boutique on se fait
                             concurrence à soi-même. --}}
                        <span class="vinted-price-compare">
                            <span>Shop <strong>{{ format_euros($product->price_cents) }}</strong></span>
                            <span>Cost {{ $costCents === null ? '—' : format_euros($costCents) }}</span>
                        </span>
                    </div>
                    @error('price')<p class="form-error">{{ $message }}</p>@enderror
                </li>

                <li class="vinted-step">
                    <div class="vinted-step-head">
                        <span class="vinted-step-number" aria-hidden="true">4</span>
                        <div>
                            <span class="vinted-step-label">Photos</span>
                            {{-- Pourquoi elles ne viennent pas de la fiche. --}}
                            <p class="vinted-step-hint">
                                The listing's own photos — worn, on a table, in the light of the room.
                                The catalogue's images stay where they are.
                            </p>
                        </div>
                        <span class="vinted-step-count">{{ $listing->exists ? $listing->images->count() : 0 }}</span>
                    </div>

                    @if ($listing->exists && $listing->images->isNotEmpty())
                        <ul class="vinted-photos">
                            @foreach ($listing->images as $index => $image)
                                <li class="vinted-photo">
                                    <img src="{{ $image->thumbnailUrl() }}" alt="" loading="lazy">
                                    <input type="hidden" name="order[]" value="{{ $image->id }}">
                                    {{-- La première est celle que Vinted montre
                                         dans le fil : elle se dit, elle ne se
                                         devine pas au rang. --}}
                                    <span class="vinted-photo-rank {{ $index === 0 ? 'is-cover' : '' }}">
                                        {{ $index === 0 ? 'Cover' : $index + 1 }}
                                    </span>
                                    <span class="vinted-photo-actions">
                                        {{-- Vinted n'accepte pas le WebP que
                                             la boutique stocke : le lien rend
                                             la même photo en JPEG, sans
                                             toucher au fichier. --}}
                                        {{-- `download` nomme le fichier côté
                                             navigateur : sans lui, tout
                                             repose sur l'en-tête de la
                                             réponse, que Chrome ignore dès
                                             qu'il croit voir une rafale de
                                             téléchargements. --}}
                                        <a
                                            href="{{ route('admin.products.vinted.photo', ['product' => $product, 'image' => $image]) }}"
                                            download="{{ $image->downloadName() }}"
                                            class="vinted-photo-download"
                                        >JPG</a>
                                        <label class="vinted-photo-remove">
                                            <input type="checkbox" name="remove_images[]" value="{{ $image->id }}">
                                            Remove
                                        </label>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <label class="vinted-drop" for="vinted-images">
                        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                            <path d="M4 15.5V17a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1.5M12 3.5v11m0-11L8 7.5m4-4 4 4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="vinted-drop-main">Add photos</span>
                        <span class="vinted-drop-note">JPEG, PNG or WebP, up to 8 MB each</span>
                        <input
                            id="vinted-images"
                            type="file"
                            name="images[]"
                            accept="image/*"
                            multiple
                            class="vinted-drop-input"
                        >
                    </label>
                    <p class="vinted-drop-picked" data-picked-for="vinted-images" hidden></p>
                    @error('images.*')<p class="form-error">{{ $message }}</p>@enderror
                </li>
            </ol>

            <div class="vinted-actions">
                <button type="submit" class="btn btn-primary">Save listing</button>
                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/admin-vinted-listing.js') }}" defer></script>
@endpush
