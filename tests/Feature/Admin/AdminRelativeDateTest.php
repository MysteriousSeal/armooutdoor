<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Les dates relatives de l'administration.
 *
 * La boutique tourne en français, l'administration est rédigée en anglais.
 * `diffForHumans()` suit la locale de l'application : sans point de passage
 * unique, « il y a 10 minutes » réapparaît au milieu d'une page anglaise, ce
 * qui s'est déjà produit trois fois.
 */
class AdminRelativeDateTest extends TestCase
{
    public function test_it_reads_in_english_even_when_the_app_is_french(): void
    {
        app()->setLocale('fr');

        $this->assertSame('10 minutes ago', admin_relative_date(now()->subMinutes(10)));
        $this->assertStringNotContainsString('il y a', admin_relative_date(now()->subMinutes(10)));
    }

    public function test_it_accepts_a_string_as_well_as_a_date(): void
    {
        app()->setLocale('fr');

        $this->assertSame(
            admin_relative_date(Carbon::parse('2026-08-25 10:00:00')),
            admin_relative_date('2026-08-25 10:00:00'),
        );
    }

    /** Rien à dater ne doit pas produire « il y a 56 ans ». */
    public function test_it_returns_nothing_for_an_empty_value(): void
    {
        $this->assertSame('', admin_relative_date(null));
        $this->assertSame('', admin_relative_date(''));
    }
}
