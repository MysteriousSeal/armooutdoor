@extends('layouts.app')

@section('title', __('store.documents_title').' — '.config('app.name'))
@section('canonical', localized_route('account.documents.index'))
@section('robots', 'noindex, nofollow')

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/documents.css') }}">
@endpush

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.documents_title') }}</span>
        </nav>

        @include('partials.page-panel-header', [
            'kicker' => __('store.account_title'),
            'title' => __('store.documents_title'),
            'lede' => __('store.documents_lede'),
            'meta' => null,
        ])

        @if (session('status'))
            <p class="doc-flash">{{ session('status') }}</p>
        @endif

        <div class="doc-layout">
            <form
                method="POST"
                action="{{ localized_route('account.documents.store') }}"
                enctype="multipart/form-data"
                class="doc-form"
            >
                @csrf
                <div class="form-group">
                    <label for="kind">{{ __('store.documents_kind') }}</label>
                    <select id="kind" name="kind" class="form-control" required>
                        @foreach (\App\Models\IdentityDocument::KINDS as $kind)
                            <option value="{{ $kind }}">{{ __('store.documents_kind_'.$kind) }}</option>
                        @endforeach
                    </select>
                    @error('kind') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label for="document">{{ __('store.documents_file') }}</label>
                    <input type="file" id="document" name="document" class="form-control doc-file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                    <p class="form-hint">{{ __('store.documents_file_hint') }}</p>
                    @error('document') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn btn-primary">{{ __('store.documents_submit') }}</button>
            </form>

            <section class="doc-list" aria-label="{{ __('store.documents_title') }}">
                @forelse ($documents as $document)
                    @php ($state = $document->effectiveStatus())
                    <article class="doc-item doc-item--{{ $state }}">
                        <div class="doc-item-copy">
                            <p class="doc-item-kind">{{ __('store.documents_kind_'.$document->kind) }}</p>
                            <p class="doc-item-meta">
                                {{ $document->created_at->translatedFormat('d F Y') }}
                                <span class="doc-item-sep" aria-hidden="true">·</span>
                                {{ $document->fileExists() ? format_bytes($document->size_bytes) : __('store.documents_file_gone') }}
                            </p>
                            @if ($document->expires_at)
                                {{-- The date on the document itself, as read by the person
                                     who checked it. Once past, the proof stops counting and
                                     the page says what to do about it. --}}
                                <p class="doc-item-validity{{ $document->hasExpired() ? ' is-lapsed' : '' }}">
                                    {{ $document->hasExpired()
                                        ? __('store.documents_expired_on', ['date' => $document->expires_at->translatedFormat('d F Y')])
                                        : __('store.documents_valid_until', ['date' => $document->expires_at->translatedFormat('d F Y')]) }}
                                    @if ($document->hasExpired())
                                        <span class="doc-item-validity-hint">{{ __('store.documents_expired_hint') }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>

                        <span class="doc-status doc-status--{{ $state }}">
                            {{ __('store.documents_status_'.$state) }}
                        </span>

                        <form
                            method="POST"
                            action="{{ localized_route('account.documents.destroy', ['document' => $document->id]) }}"
                            onsubmit="return confirm('{{ __('store.documents_delete_confirm') }}')"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="doc-item-delete">{{ __('store.documents_delete') }}</button>
                        </form>
                    </article>
                @empty
                    <p class="doc-empty">{{ __('store.documents_empty') }}</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection
