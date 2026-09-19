@extends('layouts.admin')

@section('title', 'Cdiscount listing — Admin')

@section('content')
    @php($currentTemplateId = (int) old('octopia_template_id', $listing?->octopia_template_id))
    @php($answers = $listing?->answers() ?? [])
    @php($perVariant = $listing?->perVariantCodes() ?? [])
    @php($activeVariants = $product->variants->where('is_active', true))

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">
                        <a href="{{ route('admin.products.edit', $product) }}">{{ $product->localizedName() }}</a>
                    </p>
                    <h2 class="admin-list-title">Cdiscount listing</h2>
                    <p class="admin-list-lede">
                        Octopia asks a different thing of every category. Pick the one this product belongs to,
                        answer what it asks, and take the file from the
                        <a href="{{ route('admin.marketplaces.cdiscount') }}">Cdiscount page</a>.
                    </p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-secondary">Back to the product</a>
                </div>
            </div>
            <div class="admin-list-meta">
                @foreach ($checks as $label => $done)
                    <span class="admin-list-chip {{ $done ? 'admin-list-chip--shipped' : 'admin-list-chip--refunded' }}">
                        {{ $label }}{{ $done ? '' : ' missing' }}
                    </span>
                @endforeach
            </div>
        </header>

        <form method="POST" action="{{ route('admin.products.cdiscount.update', $product) }}" class="admin-product-form">
            @csrf
            @method('PUT')

            <section class="order-panel">
                <h3 class="order-panel-title">Category</h3>

                @if ($templates->isEmpty())
                    <p class="form-hint">
                        No Octopia template yet. Add one on the
                        <a href="{{ route('admin.marketplaces.cdiscount') }}">Cdiscount page</a>, then come back.
                    </p>
                @else
                    <div class="form-group">
                        <label for="octopia_template_id">Octopia category</label>
                        <select name="octopia_template_id" id="octopia_template_id" class="form-control">
                            <option value="">Not sold on Cdiscount</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}" @selected($currentTemplateId === $template->id)>
                                    {{ $template->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="form-hint">
                            The template decides the fields below. Leaving it empty removes this listing;
                            the product itself is untouched.
                        </p>
                    </div>
                @endif
            </section>

            @foreach ($templates as $template)
                @php($isCurrent = $currentTemplateId === $template->id)
                {{-- One block per category, only the chosen one shown and sent:
                     its fields are not the next category's. --}}
                <div data-octopia-fields="{{ $template->id }}" @unless($isCurrent) hidden @endunless>
                    <section class="order-panel">
                        <h3 class="order-panel-title">{{ $template->name }}</h3>
                        <p class="form-hint">
                            Category <code class="nb-code">{{ $template->code }}</code>.
                            A field ticked « answered per variant » is asked of each variant below instead.
                        </p>

                        <div class="octopia-fields">
                            @foreach ($template->attributeFields() as $field)
                                @php($value = $isCurrent ? ($answers[$field['code']] ?? '') : '')
                                <div class="form-group octopia-field">
                                    <label for="octopia-{{ $template->id }}-{{ $field['code'] }}">
                                        {{ $field['label'] }}@if ($field['required'])<span class="octopia-required" title="Required by Octopia">*</span>@endif
                                    </label>

                                    @if (! empty($field['options']))
                                        <select
                                            name="values[{{ $field['code'] }}]"
                                            id="octopia-{{ $template->id }}-{{ $field['code'] }}"
                                            class="form-control"
                                            @unless($isCurrent) disabled @endunless
                                        >
                                            <option value="">—</option>
                                            @foreach ($field['options'] as $option)
                                                <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input
                                            type="text"
                                            name="values[{{ $field['code'] }}]"
                                            id="octopia-{{ $template->id }}-{{ $field['code'] }}"
                                            class="form-control"
                                            value="{{ $value }}"
                                            @unless($isCurrent) disabled @endunless
                                        >
                                    @endif

                                    @if ($field['constraint'])
                                        <p class="form-hint">{{ $field['constraint'] }}</p>
                                    @endif

                                    @if ($activeVariants->isNotEmpty())
                                        <label class="form-check octopia-per-variant">
                                            <input
                                                type="checkbox"
                                                name="per_variant[]"
                                                value="{{ $field['code'] }}"
                                                @checked($isCurrent && in_array($field['code'], $perVariant, true))
                                                @unless($isCurrent) disabled @endunless
                                            >
                                            Answered per variant
                                        </label>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @if ($isCurrent && $activeVariants->isNotEmpty())
                        @php($perVariantFields = array_values(array_filter(
                            $template->attributeFields(),
                            fn (array $field): bool => in_array($field['code'], $perVariant, true),
                        )))

                        <section class="order-panel">
                            <h3 class="order-panel-title">Variants</h3>

                            @if ($perVariantFields === [])
                                <p class="form-hint">
                                    Nothing is answered per variant yet. Tick a field above, save, and it will be asked
                                    of each variant here.
                                </p>
                            @else
                                <p class="form-hint">
                                    Each variant is an offer of its own on Cdiscount. Left empty, a variant takes the
                                    product's answer.
                                </p>

                                @foreach ($activeVariants as $variant)
                                    @php($own = $listing?->variants->firstWhere('product_variant_id', $variant->id)?->answers() ?? [])
                                    <div class="octopia-variant">
                                        <p class="octopia-variant-title">
                                            {{ $variant->label() !== '' ? $variant->label() : 'Variant' }}
                                            @if ($variant->sku)
                                                <code class="nb-code">{{ $variant->sku }}</code>
                                            @endif
                                            @unless (filled($variant->gtin))
                                                <span class="octopia-missing">no EAN</span>
                                            @endunless
                                        </p>

                                        <div class="octopia-fields">
                                            @foreach ($perVariantFields as $field)
                                                @php($value = $own[$field['code']] ?? '')
                                                <div class="form-group octopia-field">
                                                    <label for="octopia-variant-{{ $variant->id }}-{{ $field['code'] }}">
                                                        {{ $field['label'] }}@if ($field['required'])<span class="octopia-required">*</span>@endif
                                                    </label>
                                                    @if (! empty($field['options']))
                                                        <select
                                                            name="variants[{{ $variant->id }}][{{ $field['code'] }}]"
                                                            id="octopia-variant-{{ $variant->id }}-{{ $field['code'] }}"
                                                            class="form-control"
                                                        >
                                                            <option value="">Same as the product</option>
                                                            @foreach ($field['options'] as $option)
                                                                <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input
                                                            type="text"
                                                            name="variants[{{ $variant->id }}][{{ $field['code'] }}]"
                                                            id="octopia-variant-{{ $variant->id }}-{{ $field['code'] }}"
                                                            class="form-control"
                                                            value="{{ $value }}"
                                                        >
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </section>
                    @endif
                </div>
            @endforeach

            <div class="order-panel admin-order-create-actions">
                <button type="submit" class="btn btn-primary">Save listing</button>
                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/admin-octopia-fields.js') }}" defer></script>
@endpush
