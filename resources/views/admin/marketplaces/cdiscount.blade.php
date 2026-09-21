@extends('layouts.admin')

@section('title', 'Cdiscount — Admin')

@section('content')
    @php
        // Octopia words a message in French and English at once, "… / …":
        // the first is shown, the whole one on hover.
        $say = fn (?string $message): string => trim(\Illuminate\Support\Str::before((string) $message, ' / '));
    @endphp

    <div class="admin-list-page cdiscount-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.marketplaces.index') }}">Marketplaces</a></p>
                    <h2 class="admin-list-title">Cdiscount</h2>
                    <p class="admin-list-lede">
                        Read a category from Octopia, say on a product which one describes it, and answer what it asks.
                    </p>
                </div>
            </div>
        </header>

        {{-- The one thing the shop cannot fix by itself. --}}
        @unless ($imagesAreSecure)
            <p class="octopia-notice">
                This site is served over <code>{{ config('app.url') }}</code>, and Octopia only accepts image addresses in https.
                Products sent from here would carry the addresses as they are, so send them from production rather than from here.
            </p>
        @endunless

        {{-- Octopia's API: no file, the category's attributes read as they are.
             Without credentials the section says what is missing, rather than
             offering a search that can only fail. --}}
        @if ($apiConfigured)
            <section class="octopia-card octopia-search-card">
                <form method="GET" action="{{ route('admin.marketplaces.cdiscount') }}" class="octopia-search">
                    <label class="admin-field-label" for="octopia-find">Read a category from Octopia</label>
                    <div class="octopia-search-row">
                        <input id="octopia-find" type="search" name="find" class="form-control" placeholder="Name or 6-character code…" value="{{ $find }}">
                        <button type="submit" class="btn btn-primary">Find</button>
                    </div>
                </form>

                @if ($findError)
                    <p class="form-error">Octopia could not be read: {{ $findError }}</p>
                @endif

                @if ($find !== '' && ! $findError)
                    @if ($matches === [])
                        <p class="form-hint">No Octopia category matches « {{ $find }} ».</p>
                    @else
                        <ul class="octopia-matches">
                            @foreach ($matches as $match)
                                <li>
                                    <span class="octopia-match-label">{{ $match['label'] }}</span>
                                    <code class="nb-code">{{ $match['code'] }}</code>
                                    <form method="POST" action="{{ route('admin.marketplaces.cdiscount.categories.import') }}">
                                        @csrf
                                        <input type="hidden" name="code" value="{{ $match['code'] }}">
                                        <button type="submit" class="btn btn-sm btn-primary">Read it</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </section>
        @else
            <p class="form-hint">
                To read categories straight from Octopia, set <code>OCTOPIA_CLIENT_ID</code>, <code>OCTOPIA_CLIENT_SECRET</code>
                and <code>OCTOPIA_SELLER_ID</code> in the environment. They come from the API credentials page of your Octopia account.
            </p>
        @endif

        @if ($templates->isEmpty())
            <p class="empty-state">No category yet. Read one from Octopia above.</p>
        @else
            <nav class="admin-tabs" aria-label="Octopia categories">
                @foreach ($templates as $one)
                    <a
                        href="{{ route('admin.marketplaces.cdiscount', ['template' => $one->id]) }}"
                        class="{{ $template->is($one) ? 'active' : '' }}"
                    >
                        {{ $one->name }} <span class="admin-tab-count">{{ $one->listings()->count() }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="octopia-summary">
                <p class="octopia-summary-facts">
                    <code class="nb-code">{{ $template->code }}</code>
                    <span>{{ count($template->fields) }} attributes</span>
                    <span>{{ count($template->requiredAttributeFields()) }} required</span>
                    <span class="nb-none">Read from Octopia {{ admin_relative_date($template->synced_at ?? $template->created_at) }}</span>
                </p>
                <div class="admin-table-actions">
                    <form method="POST" action="{{ route('admin.marketplaces.cdiscount.categories.import') }}">
                        @csrf
                        <input type="hidden" name="code" value="{{ $template->code }}">
                        <button type="submit" class="btn btn-sm btn-secondary">Refresh from Octopia</button>
                    </form>
                    <form method="POST" action="{{ route('admin.marketplaces.cdiscount.categories.destroy', $template) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-secondary">Remove category</button>
                    </form>
                </div>
            </div>

            @if ($lines === [])
                <p class="empty-state">
                    No product points at this category yet. Open a product, follow its
                    « Cdiscount listing » link and pick « {{ $template->name }} » there.
                </p>
            @else
                <form method="POST" action="{{ route('admin.marketplaces.cdiscount.send', $template) }}" data-octopia-send>
                    @csrf

                    <h3 class="octopia-section-title">Lines</h3>

                    <div class="admin-table-wrap">
                        <table class="admin-table nb-table octopia-lines">
                            <thead>
                                <tr>
                                    <th class="octopia-pick"><span class="sr-only">Send</span></th>
                                    <th>Line</th>
                                    <th class="octopia-col-code">EAN</th>
                                    <th class="octopia-col-ref">Seller reference</th>
                                    <th class="octopia-col-state">Sheet</th>
                                    <th class="octopia-col-offer">Offer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $line)
                                    @php($ready = $line['missing'] === [])
                                    <tr class="{{ $ready ? '' : 'octopia-row--incomplete' }}">
                                        <td class="octopia-pick">
                                            {{-- A line with something missing would be refused: it
                                                 cannot be ticked, and the column says why. --}}
                                            <input
                                                type="checkbox"
                                                name="lines[]"
                                                value="{{ $line['key'] }}"
                                                @checked($ready)
                                                @disabled(! $ready)
                                                aria-label="Send this line"
                                            >
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.products.edit', $line['product']) }}">{{ $line['title'] }}</a>
                                            @if ($line['variant'])
                                                <span class="nb-ourname">
                                                    <span class="nb-ourname-arrow" aria-hidden="true">&#8627;</span>
                                                    {{ $line['variant']->label() !== '' ? $line['variant']->label() : 'Variant' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($line['gtin'])
                                                <code class="nb-code">{{ $line['gtin'] }}</code>
                                            @else
                                                <span class="nb-none">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($line['reference'])
                                                <code class="nb-code">{{ $line['reference'] }}</code>
                                            @else
                                                <span class="nb-none">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($ready)
                                                <span class="order-chip order-chip--shipped">Ready</span>
                                            @else
                                                <span class="octopia-missing">{{ implode(', ', $line['missing']) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($line['offer']['missing'] === [])
                                                <span class="order-chip order-chip--shipped">{{ format_euros($line['offer']['price_cents']) }}</span>
                                                <span class="nb-none">{{ $line['offer']['stock'] }} in stock</span>
                                            @else
                                                <a href="{{ route('admin.products.cdiscount.edit', $line['product']) }}" class="octopia-missing">{{ implode(', ', $line['offer']['missing']) }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Two steps, in this order: the offer of a product Octopia
                         does not know yet is rejected. Numbered because the
                         order is the point. --}}
                    <div class="octopia-steps">
                        <div class="octopia-step">
                            <span class="octopia-step-number" aria-hidden="true">1</span>
                            <div class="octopia-step-body">
                                <h4 class="octopia-step-title">Send the products</h4>
                                <p>Octopia creates the product sheets in its catalogue and checks each one.</p>
                            </div>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                onclick="return confirm('Send the ticked lines to Octopia? This creates their product sheets on Cdiscount.')"
                            >Send the products</button>
                        </div>

                        <div class="octopia-step">
                            <span class="octopia-step-number" aria-hidden="true">2</span>
                            <div class="octopia-step-body">
                                <h4 class="octopia-step-title">Put them on sale</h4>
                                <p>
                                    Once the products show as Integrated below, this sends the price, the stock and the delivery
                                    set on each product's Cdiscount page. Nothing is buyable before this step.
                                </p>
                            </div>
                            <button
                                type="submit"
                                formaction="{{ route('admin.marketplaces.cdiscount.offers', $template) }}"
                                class="btn btn-primary"
                                onclick="return confirm('Put the ticked lines on sale on Cdiscount, at the price and stock shown? This makes them buyable.')"
                            >Put them on sale</button>
                        </div>
                    </div>
                </form>
            @endif

            @if ($submissions->isNotEmpty())
                <h3 class="octopia-section-title">Sent to Octopia</h3>

                @foreach ($submissions as $submission)
                    @php($counts = collect($submission->outcomes())->countBy(fn (array $outcome): string => $outcome['status'] ?? 'pending'))
                    <section class="octopia-card octopia-batch">
                        <header class="octopia-batch-head">
                            <div class="octopia-batch-title">
                                <span class="octopia-batch-kind">{{ $submission->isOffers() ? 'Offers' : 'Products' }}</span>
                                <span class="nb-none">
                                    sent {{ admin_relative_date($submission->created_at) }}
                                    @if ($submission->checked_at)
                                        · checked {{ admin_relative_date($submission->checked_at) }}
                                    @else
                                        · not checked yet
                                    @endif
                                </span>
                                <code class="nb-code" title="Package id">{{ $submission->package_id }}</code>
                            </div>

                            <div class="octopia-batch-side">
                                {{-- Where the batch stands, in a glance: the lines below say why. --}}
                                @if ($counts->get('Integrated'))
                                    <span class="order-chip order-chip--shipped">{{ $counts->get('Integrated') }} integrated</span>
                                @endif
                                @if ($counts->get('Rejected', 0) + $counts->get('Refused', 0))
                                    <span class="order-chip order-chip--refunded">{{ $counts->get('Rejected', 0) + $counts->get('Refused', 0) }} rejected</span>
                                @endif
                                @if ($counts->get('Validated'))
                                    <span class="order-chip">{{ $counts->get('Validated') }} being processed</span>
                                @endif
                                @if ($counts->get('pending'))
                                    <span class="order-chip">{{ $counts->get('pending') }} waiting for an answer</span>
                                @endif

                                @unless ($submission->isSettled())
                                    <form method="POST" action="{{ route('admin.marketplaces.cdiscount.submissions.check', $submission) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary">Check the result</button>
                                    </form>
                                @endunless
                            </div>
                        </header>

                        <div class="octopia-scroll">
                            <table class="admin-table octopia-outcomes">
                                <tbody>
                                    @foreach ($submission->outcomes() as $outcome)
                                        <tr>
                                            <td class="octopia-outcome-line">{{ $outcome['title'] }}</td>
                                            <td class="octopia-col-code"><code class="nb-code">{{ $outcome['gtin'] }}</code></td>
                                            <td class="octopia-col-status">
                                                @if ($outcome['status'] === 'Integrated')
                                                    <span class="order-chip order-chip--shipped">Integrated</span>
                                                @elseif (in_array($outcome['status'], ['Refused', 'Rejected'], true))
                                                    <span class="order-chip order-chip--refunded">{{ $outcome['status'] }}</span>
                                                @elseif ($outcome['status'] === 'Duplicated')
                                                    <span class="order-chip">Duplicated</span>
                                                @elseif ($outcome['status'] === 'Validated')
                                                    <span class="order-chip">Being processed</span>
                                                @else
                                                    <span class="nb-none">No answer yet</span>
                                                @endif
                                            </td>
                                            <td class="octopia-outcome-notes">
                                                @if ($outcome['errors'] !== [] || $outcome['warnings'] !== [])
                                                    <ul class="octopia-notes">
                                                        @foreach ($outcome['errors'] as $error)
                                                            <li class="octopia-missing" title="{{ $error['message'] ?? '' }}">{{ filled($error['field'] ?? null) ? $error['field'].': ' : '' }}{{ $say($error['message'] ?? $error['code'] ?? '') }}</li>
                                                        @endforeach
                                                        @foreach ($outcome['warnings'] as $warning)
                                                            <li class="nb-none" title="{{ $warning['message'] ?? '' }}">{{ filled($warning['field'] ?? null) ? $warning['field'].': ' : '' }}{{ $say($warning['message'] ?? $warning['code'] ?? '') }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            @endif
        @endif
    </div>
@endsection
