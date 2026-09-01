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

    public function test_the_help_pages_stop_promising_paypal_and_name_stripe(): void
    {
        // PayPal est désactivé au checkout : partout ailleurs il est
        // annoncé « bientôt », jamais offert.
        $this->get('/faq')->assertOk()
            ->assertSee('PayPal arrive bientôt')
            ->assertDontSee('ou par PayPal');

        $this->get('/paiement-securise')->assertOk()
            ->assertSee('PayPal arrive bientôt')
            ->assertSee('Stripe')
            ->assertSee('3-D Secure')
            ->assertSee('help-logo--soon');

        $this->get('/')->assertOk()
            ->assertDontSee('Carte bancaire, PayPal')
            ->assertDontSee('Carte bancaire ou PayPal');
    }

    public function test_the_privacy_policy_accounts_for_identity_documents(): void
    {
        // The shop collects passports now. A page listing what it collects
        // that does not mention them is the gap a complaint is made of.
        $page = $this->get('/confidentialite')->assertOk();

        // Collected, and why.
        $page->assertSee('Pièce d\'identité', false);
        $page->assertSee('Mes documents', false);
        $page->assertSee('majorité', false);
        // How long, which is the part CNIL sanctions when it is wrong.
        $page->assertSee('supprimée dès qu\'elle a été vérifiée', false);
        // Who sees it: nobody outside the shop.
        $page->assertSee('ne sont transmises à aucun prestataire', false);
        // How it is held.
        $page->assertSee('chiffré avant d\'être écrit', false);
        $page->assertSee('journalisée', false);
    }

    public function test_the_policy_says_handing_one_over_is_optional(): void
    {
        $this->get('/confidentialite')
            ->assertOk()
            ->assertSee('Le dépôt est facultatif', false);
    }

    public function test_the_legal_notice_names_the_same_tools_as_the_privacy_policy(): void
    {
        // The two pages had drifted: the policy named PostHog and Google
        // Analytics while the notice still said only that nothing is dropped
        // without consent, which is true and stops one sentence short.
        $notice = $this->get('/mentions-legales')->assertOk();

        $notice->assertSee('PostHog', false);
        $notice->assertSee('Google Analytics', false);
        $notice->assertSee('Union européenne', false);
        $notice->assertSee('États-Unis', false);
        $notice->assertSee(route('legal.privacy'), false);
    }

    public function test_both_pages_still_say_nothing_is_dropped_without_consent(): void
    {
        $this->get('/mentions-legales')
            ->assertOk()
            ->assertSee('sans consentement préalable', false)
            ->assertSee('aucun des deux n\'est chargé', false);
    }
}
