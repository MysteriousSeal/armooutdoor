<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce que les pages légales doivent dire — et ne plus dire — depuis la
 * relecture de conformité : plus de bannière « modèle », la médiation
 * nommée dès qu'elle est configurée, la majorité exigée sur les produits
 * réglementés, et la mesure d'audience assumée dans la confidentialité.
 */
class LegalPagesComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_terms_no_longer_announce_themselves_as_a_template(): void
    {
        $this->get('/cgv')->assertOk()
            ->assertDontSee('un modèle à faire relire');
    }

    public function test_the_terms_carry_the_age_clause_and_the_odr_link(): void
    {
        $this->get('/cgv')->assertOk()
            ->assertSee('réservés aux personnes majeures')
            ->assertSee('ec.europa.eu/consumers/odr')
            ->assertSee('France métropolitaine uniquement')
            // Le pied de page dit « carte ou PayPal » sur toutes les pages ;
            // c'est la formule exacte retirée des CGV qu'on vise.
            ->assertDontSee('carte bancaire ou PayPal');
    }

    public function test_the_mediator_is_named_once_configured(): void
    {
        $this->get('/cgv')->assertOk()->assertDontSee('Le médiateur compétent');

        CompanySetting::current()->update([
            'mediator_name' => 'CM2C',
            'mediator_url' => 'https://www.cm2c.net',
        ]);

        $this->get('/cgv')->assertOk()
            ->assertSee('Le médiateur compétent est CM2C')
            ->assertSee('https://www.cm2c.net');
    }

    public function test_the_privacy_policy_owns_up_to_audience_measurement(): void
    {
        $this->get('/confidentialite')->assertOk()
            ->assertSee('audience du site')
            ->assertSee('adresse IP')
            ->assertSee('Stripe');
    }

    public function test_the_withdrawal_delay_runs_from_receipt_by_the_client_or_their_designee(): void
    {
        $this->get('/droit-de-retractation')->assertOk()
            ->assertSee('ou par un tiers qu');
    }
}
