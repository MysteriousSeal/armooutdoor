@extends('layouts.admin')

@section('title', $title)

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Accounting</p>
            <h2 class="admin-list-title">{{ $title }}</h2>
            <p class="admin-list-lede">{{ $lede }}</p>
        </header>

        {{-- Le mois en cours en tête : c'est celui qu'on ouvre presque
             toujours, et il doit rester à la même place d'un mois sur
             l'autre. --}}
        @foreach ($years as $year => $months)
            <section class="accounting-year" aria-labelledby="accounting-year-{{ $year }}">
                <h3 class="accounting-year-title" id="accounting-year-{{ $year }}">{{ $year }}</h3>
                <ul class="accounting-months">
                    @foreach ($months as $month)
                        <li>
                            <a
                                href="{{ route('admin.accounting.'.$section.'.month', ['month' => \App\Support\AccountingPeriods::key($month)]) }}"
                                class="accounting-month {{ $loop->parent->first && $loop->first ? 'is-current' : '' }}"
                            >
                                <span class="accounting-month-name">{{ $month->locale('en')->isoFormat('MMMM') }}</span>
                                <span class="accounting-month-meta">
                                    @if ($loop->parent->first && $loop->first)
                                        In progress
                                    @else
                                        {{ \App\Support\AccountingPeriods::key($month) }}
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
