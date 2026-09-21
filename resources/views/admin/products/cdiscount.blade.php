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
                        answer what it asks. The ready lines are listed on the
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
                        No Octopia category yet. Read one from Octopia on the
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
                            The category decides the fields below. Leaving it empty removes this listing;
                            the product itself is untouched.
                        </p>
                    </div>
                @endif
            </section>

            {{-- Claude reads the product sheet and fills what it establishes. It
                 proposes; the form still has to be saved. Shown once a category
                 is chosen, and only where a key is configured. --}}
            @if ($canGenerate)
                <div class="vinted-assist" data-octopia-assist @unless($currentTemplateId) hidden @endunless>
                    <span class="vinted-assist-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" focusable="false">
                            <path d="M12 3.2 13.7 9l5.8 1.7-5.8 1.7L12 18.2 10.3 12.4 4.5 10.7 10.3 9zM18.4 3.4l.7 2.3 2.3.7-2.3.7-.7 2.3-.7-2.3-2.3-.7 2.3-.7z" fill="currentColor"/>
                        </svg>
                    </span>

                    <div class="vinted-assist-main">
                        <p class="vinted-assist-title">Let Claude fill it in</p>
                        <p class="vinted-assist-note">
                            Reads the product sheet (name, description, characteristics, weight, brand, variants) and fills the empty attributes it can establish from it.
                            It never guesses: an attribute the sheet does not state is left for you, and what you already answered is not touched.
                            Nothing is saved until you press Save.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary vinted-assist-run"
                        data-octopia-generate
                        data-generate-url="{{ route('admin.products.cdiscount.generate', $product) }}"
                    >Fill with Claude</button>
                </div>
                <p class="vinted-assist-status" data-octopia-generate-status role="status" hidden></p>
            @endif

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

            {{-- The description Cdiscount is given, written for the category chosen.
                 Sent instead of the shop's meta and long descriptions. --}}
            <div data-octopia-description @unless($currentTemplateId) hidden @endunless>
                <section class="order-panel">
                    <h3 class="order-panel-title">Description for Cdiscount</h3>
                    <p class="form-hint">
                        Octopia reads the description to file the product, and the shop's, which lists every use of the article, can move it to another category.
                        This one is sent instead of the meta description and the long description: written for the category chosen, it says what the article is, plainly.
                        Left empty, the meta description is sent, or the long one.
                    </p>

                    <div class="form-group">
                        <textarea
                            name="description"
                            id="octopia-description"
                            class="form-control"
                            rows="6"
                            maxlength="{{ \App\Services\Octopia\OctopiaDescriptionWriter::LIMIT }}"
                            data-octopia-description-field
                        >{{ old('description', $listing?->description) }}</textarea>
                        <p class="form-hint">
                            <span data-octopia-count>{{ mb_strlen((string) old('description', $listing?->description)) }}</span>
                            / {{ \App\Services\Octopia\OctopiaDescriptionWriter::LIMIT }} characters, plain text.
                        </p>
                        @error('description')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    @if ($canGenerate)
                        <div class="octopia-description-actions">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                data-octopia-describe
                                data-describe-url="{{ route('admin.products.cdiscount.describe', $product) }}"
                            >Write with Claude</button>
                            <p class="vinted-assist-status" data-octopia-describe-status role="status" hidden></p>
                        </div>
                    @endif
                </section>
            </div>

            {{-- The offer: what it is sold at and delivered how. Independent of
                 the category's attributes, but there is no offer without a
                 category, so it follows the choice above. --}}
            <div data-octopia-offer @unless($currentTemplateId) hidden @endunless>
                <section class="order-panel">
                    <h3 class="order-panel-title">Offer</h3>
                    <p class="form-hint">
                        What Cdiscount is told about selling this product: at what price, delivered how, and how soon.
                        The stock is the shop's own{{ $activeVariants->isNotEmpty() ? ', variant by variant' : '' }}.
                        The VAT, the eco-tax and the D3E tax are all sent as 0.
                    </p>

                    <div class="octopia-fields">
                        <div class="form-group octopia-field">
                            <label for="offer-condition">Condition</label>
                            <select name="offer[condition]" id="offer-condition" class="form-control">
                                @foreach (\App\Models\CdiscountListing::CONDITIONS as $code => $label)
                                    <option value="{{ $code }}" @selected(old('offer.condition', $offer['condition']) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group octopia-field">
                            <label for="offer-markup">Markup on the shop price (%)</label>
                            <input type="number" step="0.1" min="-50" max="200" name="offer[markup]" id="offer-markup" class="form-control" value="{{ old('offer.markup', $offer['markup'] + 0) }}" data-offer-markup>
                            <p class="form-hint">Cdiscount takes a commission: this raises the shop's price to cover it.</p>
                        </div>

                        <div class="form-group octopia-field">
                            <label for="offer-preparation">Preparation time (days)<span class="octopia-required" title="Required by Octopia">*</span></label>
                            <input type="number" step="1" min="0" max="90" name="offer[preparation_days]" id="offer-preparation" class="form-control" value="{{ old('offer.preparation_days', $offer['preparation_days']) }}">
                            <p class="form-hint">Days before the parcel leaves.</p>
                        </div>
                    </div>

                    {{-- What the customer will pay on Cdiscount, following the markup
                         as it is typed. Every line the product is sold as. --}}
                    <div class="octopia-sale-price" aria-live="polite">
                        <p class="octopia-sale-price-title">Sold on Cdiscount at</p>
                        @php($markup = (float) old('offer.markup', $offer['markup']))
                        @foreach ($priceLines as $line)
                            <p class="octopia-sale-price-line">
                                @if (count($priceLines) > 1)<span class="octopia-sale-price-label">{{ $line['label'] }}</span>@endif
                                <strong data-shop-cents="{{ $line['cents'] }}">{{ format_euros((int) round($line['cents'] * (1 + $markup / 100))) }}</strong>
                                <span class="nb-none">shop price {{ format_euros($line['cents']) }}</span>
                            </p>
                        @endforeach
                    </div>

                    <h4 class="octopia-variant-title">Delivery</h4>
                    <p class="form-hint">
                        Each way you deliver, and what the customer pays for it. The tracked delivery is the one Octopia requires;
                        a free delivery is a cost of 0. The extra cost is for each further item in the same order.
                    </p>

                    @foreach (\App\Models\CdiscountListing::DELIVERY_MODES as $code => $label)
                        @php($mode = $offer['delivery'][$code] ?? null)
                        <div class="octopia-delivery">
                            <label class="form-check">
                                <input type="checkbox" name="offer[delivery][{{ $code }}][enabled]" value="1" @checked(old('offer.delivery.'.$code.'.enabled', $mode !== null))>
                                {{ $label }}@if ($code === 'THD')<span class="octopia-required" title="Required by Octopia">*</span>@endif
                            </label>
                            <div class="octopia-delivery-costs">
                                <label class="octopia-delivery-cost">
                                    <span>Cost (€)</span>
                                    <input type="number" step="0.01" min="0" name="offer[delivery][{{ $code }}][cost]" class="form-control" value="{{ old('offer.delivery.'.$code.'.cost', $mode['cost'] ?? '') }}">
                                </label>
                                <label class="octopia-delivery-cost">
                                    <span>Each extra item (€)</span>
                                    <input type="number" step="0.01" min="0" name="offer[delivery][{{ $code }}][additional]" class="form-control" value="{{ old('offer.delivery.'.$code.'.additional', $mode['additional'] ?? '') }}">
                                </label>
                            </div>
                        </div>
                    @endforeach

                    @foreach (['offer.condition', 'offer.markup', 'offer.preparation_days'] as $key)
                        @error($key)<p class="form-error">{{ $message }}</p>@enderror
                    @endforeach
                </section>
            </div>

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
