{{--
    The list of accounting months, sales or purchases.

    Both sections share this template; `$section` names which one, and drives
    where a month card links. `$years` holds the months grouped by year, newest
    first, and `$counts` how many lines each month carries — empty for
    purchases, which has nothing to count yet.
--}}
@extends('layouts.admin')

@section('title', $title)

@section('content')
    <div class="admin-list-page">
        {{-- Heading, and the sentence saying what the section holds. --}}
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Accounting</p>
            <h2 class="admin-list-title">{{ $title }}</h2>
            <p class="admin-list-lede">{{ $lede }}</p>
        </header>

        {{-- The current month first: it is the one opened almost every time,
             and it should stay in the same place from one month to the
             next. --}}
        @foreach ($years as $year => $months)
            {{-- One block per year, its months as cards. --}}
            <section class="accounting-year" aria-labelledby="accounting-year-{{ $year }}">
                <h3 class="accounting-year-title" id="accounting-year-{{ $year }}">{{ $year }}</h3>
                <ul class="accounting-months">
                    @foreach ($months as $month)
                        @php
                            $isCurrent = $loop->parent->first && $loop->first;
                            $monthKey = \App\Support\AccountingPeriods::key($month);
                            $entries = (int) ($counts[$monthKey] ?? 0);
                            $download = $downloads[$monthKey] ?? null;
                            $hasMoved = (bool) ($stale[$monthKey] ?? false);
                        @endphp
                        <li>
                            <a
                                href="{{ route('admin.accounting.'.$section.'.month', ['month' => $monthKey]) }}"
                                class="accounting-month {{ $isCurrent ? 'is-current' : '' }}"
                            >
                                <span class="accounting-month-name">{{ $month->locale('en')->isoFormat('MMMM') }}</span>
                                <span class="accounting-month-meta">
                                    {{-- The count rather than the month key: it is what
                                         you look for before opening. The current month
                                         says it too, and adds that it is still running. --}}
                                    <span class="accounting-month-count {{ $entries === 0 ? 'is-none' : '' }}">
                                        {{ trans_choice('{0}none|{1}:count entry|[2,*]:count entries', $entries, ['count' => $entries]) }}
                                    </span>
                                    {{-- The current month cannot be ruled off, so a
                                         download status there would be noise. --}}
                                    @if ($download && ! $isCurrent)
                                        <span
                                            class="accounting-month-filed {{ $hasMoved ? 'is-stale' : '' }}"
                                            title="{{ $hasMoved ? 'Changed since the copy of' : 'Downloaded' }} {{ $download->created_at->format('j M Y') }} at {{ $download->created_at->format('H:i') }}{{ $download->user ? ' by '.$download->user->name : '' }}"
                                        >
                                            {{ $hasMoved ? 'Changed' : 'Downloaded' }}
                                        </span>
                                    @endif
                                    @if ($isCurrent)
                                        <span class="accounting-month-running">In progress</span>
                                    @endif
                                </span>
                                <svg class="accounting-month-arrow" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
                                    <path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
