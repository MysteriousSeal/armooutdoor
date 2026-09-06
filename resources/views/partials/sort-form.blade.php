{{-- $action · $sort · $id · $hidden (optionnel)

     Le même sélecteur pour la page catalogue et les pages catégorie : une
     seule liste d'ordres, un seul dessin. Il se soumet au changement, et
     le bouton du <noscript> fait le même travail sans JavaScript. --}}
<form method="GET" class="sort-form" action="{{ $action }}">
    @foreach ($hidden ?? [] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="sort-field">
        <label for="{{ $id }}">{{ __('store.sort_label') }}</label>
        <div class="sort-select-wrap">
            <select id="{{ $id }}" name="sort" class="sort-select" onchange="this.form.submit()">
                @foreach (\App\Support\ProductSort::OPTIONS as $option)
                    <option value="{{ $option }}" @selected($sort === $option)>
                        {{ __('store.sort_'.str_replace('-', '_', $option)) }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <noscript>
        <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.sort_label') }}</button>
    </noscript>
</form>
