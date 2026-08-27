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
                                <span class="accounting-month-name">
                                    {{ $month->locale('en')->isoFormat('MMMM') }}
                                    {{-- What the month still owes the file, beside its
                                         name: the list then shows where the work is
                                         without opening every month. A month with
                                         nothing in it has nothing to owe. --}}
                                    @if ($section === 'purchases' && $entries > 0)
                                        @php($owed = (int) ($missingInvoices[$monthKey] ?? 0))
                                        @if ($owed > 0)
                                            <span class="accounting-month-owed" title="{{ trans_choice('{1}:count invoice still to attach|[2,*]:count invoices still to attach', $owed, ['count' => $owed]) }}">
                                                {{ $owed }}
                                                <span class="sr-only">{{ trans_choice('{1}invoice missing|[2,*]invoices missing', $owed) }}</span>
                                            </span>
                                        @else
                                            <span class="accounting-month-owed is-complete" title="Every invoice attached">
                                                <svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                                                    <path d="m5 13 4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span class="sr-only">Every invoice attached</span>
                                            </span>
                                        @endif
                                    @endif
                                </span>
                                <span class="accounting-month-meta">
                                    {{-- The count rather than the month key: it is what
                                         you look for before opening. The current month
                                         says it too, and adds that it is still running. --}}
                                    <span class="accounting-month-count {{ $entries === 0 ? 'is-none' : '' }}">
                                        {{ trans_choice('{1}:count entry|[0,*]:count entries', $entries, ['count' => $entries]) }}
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
                                    {{-- Closed, holding lines, and never taken out:
                                         a sheet is waiting to be filed. A month that
                                         sold nothing is not offered, since there is
                                         nothing to print. --}}
                                    @if (! $download && ! $isCurrent && $entries > 0)
                                        <span class="accounting-month-available">Download available</span>
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
