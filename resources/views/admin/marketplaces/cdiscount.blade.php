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
                        Octopia takes no feed: it takes the Excel template of a category, filled in.
                        Keep the template here, say on a product which one describes it, and take the file back with the lines written in.
                    </p>
                </div>
            </div>
        </header>

        {{-- The one thing the export cannot fix by itself. --}}
        @unless ($imagesAreSecure)
            <p class="empty-state">
                This site is served over <code>{{ config('app.url') }}</code>, and Octopia only accepts image addresses in https.
                The file will carry the addresses as they are, so export from production rather than from here.
            </p>
        @endunless

        <form method="POST" action="{{ route('admin.marketplaces.cdiscount.templates.store') }}" enctype="multipart/form-data" class="admin-filter-bar">
            @csrf
            <div class="admin-filter-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-field-label" for="octopia-template">Add a category template (.xlsm from Octopia)</label>
                    <input id="octopia-template" type="file" name="template" accept=".xlsm,.xlsx" class="form-control" required>
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Read it</button>
                </div>
            </div>
        </form>

        @if ($templates->isEmpty())
            <p class="empty-state">No template yet. Download one from Octopia for a category and add it above.</p>
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
                    Category <code class="nb-code">{{ $template->code }}</code>, {{ count($template->fields) }} columns,
                    {{ count($template->requiredAttributeFields()) }} of them required attributes.
                    From <code class="nb-code">{{ $template->original_filename }}</code>, added {{ admin_relative_date($template->created_at) }}.
                </p>
                <form method="POST" action="{{ route('admin.marketplaces.cdiscount.templates.destroy', $template) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-secondary">Remove template</button>
                </form>
            </div>

            @if ($lines === [])
                <p class="empty-state">
                    No product points at this template yet. Open a product, follow its
                    « Cdiscount listing » link and pick « {{ $template->name }} » there.
                </p>
            @else
                <form method="POST" action="{{ route('admin.marketplaces.cdiscount.export', $template) }}">
                    @csrf
                    <div class="admin-table-wrap">
                        <table class="admin-table nb-table">
                            <thead>
                                <tr>
                                    <th class="octopia-pick"><span class="sr-only">Export</span></th>
                                    <th>Line</th>
                                    <th>EAN</th>
                                    <th>Seller reference</th>
                                    <th>Missing</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $line)
                                    @php($ready = $line['missing'] === [])
                                    <tr class="{{ $ready ? '' : 'octopia-row--incomplete' }}">
                                        <td class="octopia-pick">
                                            {{-- An incomplete line would be refused on import: it can
                                                 still be sent, but it is not ticked for you. --}}
                                            <input
                                                type="checkbox"
                                                name="lines[]"
                                                value="{{ $line['key'] }}"
                                                @checked($ready)
                                                aria-label="Export this line"
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="vinted-actions">
                        <button type="submit" class="btn btn-primary">Download the filled template</button>
                        <p class="form-hint">
                            The file comes back as Octopia's own, with the ticked lines written from row {{ $template->first_data_row }}.
                            Prices and stock are not in this template: they are set on Octopia's side.
                        </p>
                    </div>
                </form>
            @endif
        @endif
    </div>
@endsection
