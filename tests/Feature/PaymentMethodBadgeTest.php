<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A payment method the shop does not take yet.
 *
 * The badge saying so had markup and no rules behind it, so it inherited the
 * tile label's own style and came out as one run of capitals — PAYPAL BIENTOT
 * DISPONIBLE — reading as a method you could use.
 */
class PaymentMethodBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_badge_is_its_own_element_beside_the_name(): void
    {
        // Not nested inside the label: that is what made it inherit the
        // label's uppercase weight and run together with it.
        $this->get('/paiement-securise')
            ->assertOk()
            ->assertSee('<span>PayPal</span>', false)
            ->assertSee('<span class="badge help-logo-soon">Bientôt disponible</span>', false)
            ->assertDontSee('help-logo-soon-chip', false);
    }

    public function test_the_available_method_carries_no_badge(): void
    {
        $html = $this->get('/paiement-securise')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'help-logo-soon'));
        $this->assertStringContainsString('<span>Carte bancaire</span>', $html);
    }

    public function test_every_class_the_tile_uses_is_actually_styled(): void
    {
        // The whole fault was markup naming rules that did not exist.
        $css = file_get_contents(public_path('css/help.css'));

        foreach (['.help-logo--soon', '.help-logo-soon'] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector.' has no rules.');
        }

        $this->assertStringContainsString('grayscale', $css);
    }

    public function test_the_badge_reuses_the_shared_component(): void
    {
        $base = file_get_contents(public_path('css/base.css'));

        $this->assertStringContainsString('.badge {', $base);
        $this->get('/paiement-securise')->assertOk()->assertSee('class="badge help-logo-soon"', false);
    }
}
