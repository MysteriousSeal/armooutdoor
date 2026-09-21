@extends('layouts.admin')

@section('title', 'Cdiscount — Admin')

@section('content')
    <div class="admin-list-page">
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
            <p class="empty-state">
                This site is served over <code>{{ config('app.url') }}</code>, and Octopia only accepts image addresses in https.
                Products sent from here would carry the addresses as they are, so send them from production rather than from here.
            </p>
        @endunless

        {{-- Octopia's API: no file, the category's attributes read as they are.
             Without credentials the section says what is missing, rather than
             offering a search that can only fail. --}}
        @if ($apiConfigured)
            <form method="GET" action="{{ route('admin.marketplaces.cdiscount') }}" class="admin-filter-bar">
                <div class="admin-filter-row">
                    <div class="admin-filter-field admin-filter-field--search">
                        <label class="admin-field-label" for="octopia-find">Read a category from Octopia</label>
                        <input id="octopia-find" type="search" name="find" class="form-control" placeholder="Name or 6-character code…" value="{{ $find }}">
                    </div>
                    <div class="admin-filter-actions">
                        <button type="submit" class="btn btn-primary">Find</button>
                    </div>
                </div>
            </form>

            @error('code')<p class="form-error">{{ $message }}</p>@enderror

            @if ($findError)
                <p class="form-error">Octopia could not be read: {{ $findError }}</p>
            @endif

            @if ($find !== '' && ! $findError)
                @if ($matches === [])
                    <p class="empty-state">No Octopia category matches « {{ $find }} ».</p>
                @else
                    <div class="admin-table-wrap">
                        <table class="admin-table nb-table">
                            <tbody>
                                @foreach ($matches as $match)
                                    <tr>
                                        <td>{{ $match['label'] }}</td>
                                        <td><code class="nb-code">{{ $match['code'] }}</code></td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.marketplaces.cdiscount.categories.import') }}" class="admin-table-actions">
                                                @csrf
                                                <input type="hidden" name="code" value="{{ $match['code'] }}">
                                                <button type="submit" class="btn btn-sm btn-primary">Read it</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
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

            <div class="octopia-template-head">
                <p class="form-hint">
                    Category <code class="nb-code">{{ $template->code }}</code>, {{ count($template->fields) }} attributes,
                    {{ count($template->requiredAttributeFields()) }} of them required.
                    Read from Octopia {{ admin_relative_date($template->synced_at ?? $template->created_at) }}.
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
                    @error('lines')<p class="form-error">{{ $message }}</p>@enderror

                    <div class="admin-table-wrap">
                        <table class="admin-table nb-table">
                            <thead>
                                <tr>
                                    <th class="octopia-pick"><span class="sr-only">Send</span></th>
                                    <th>Line</th>
                                    <th>EAN</th>
                                    <th>Seller reference</th>
                                    <th>Sheet</th>
                                    <th>Offer</th>
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

                    <div class="vinted-actions">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            onclick="return confirm('Send the ticked lines to Octopia? This creates their product sheets on Cdiscount.')"
                        >1. Send the products</button>
                        <button
                            type="submit"
                            formaction="{{ route('admin.marketplaces.cdiscount.offers', $template) }}"
                            class="btn btn-primary"
                            onclick="return confirm('Put the ticked lines on sale on Cdiscount, at the price and stock shown? This makes them buyable.')"
                        >2. Put them on sale</button>
                        <p class="form-hint">
                            First the products: Octopia creates their sheets in its catalogue and checks each one.
                            Once they show as Integrated below, put them on sale: that sends the price, the stock and the delivery,
                            set on each product's Cdiscount page. Nothing is buyable before the second step.
                        </p>
                    </div>
                </form>
            @endif

            @if ($submissions->isNotEmpty())
                <h3 class="order-panel-title">Sent to Octopia</h3>
                @error('submission')<p class="form-error">{{ $message }}</p>@enderror

                @foreach ($submissions as $submission)
                    <div class="octopia-template-head">
                        <p class="form-hint">
                            <strong>{{ $submission->isOffers() ? 'Offers' : 'Products' }}</strong>,
                            package <code class="nb-code">{{ $submission->package_id }}</code>,
                            sent {{ admin_relative_date($submission->created_at) }}.
                            @if ($submission->checked_at)
                                Checked {{ admin_relative_date($submission->checked_at) }}.
                            @else
                                Not checked yet.
                            @endif
                        </p>
                        @unless ($submission->isSettled())
                            <form method="POST" action="{{ route('admin.marketplaces.cdiscount.submissions.check', $submission) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-secondary">Check the result</button>
                            </form>
                        @endunless
                    </div>

                    <div class="admin-table-wrap">
                        <table class="admin-table nb-table">
                            <tbody>
                                @foreach ($submission->outcomes() as $outcome)
                                    <tr>
                                        <td>{{ $outcome['title'] }}</td>
                                        <td><code class="nb-code">{{ $outcome['gtin'] }}</code></td>
                                        <td>
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
                                        <td>
                                            @foreach ($outcome['errors'] as $error)
                                                <span class="octopia-missing">{{ filled($error['field'] ?? null) ? $error['field'].': ' : '' }}{{ $error['message'] ?? $error['code'] ?? '' }}</span><br>
                                            @endforeach
                                            @foreach ($outcome['warnings'] as $warning)
                                                <span class="nb-none">{{ filled($warning['field'] ?? null) ? $warning['field'].': ' : '' }}{{ $warning['message'] ?? $warning['code'] ?? '' }}</span><br>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif
        @endif
    </div>
@endsection
