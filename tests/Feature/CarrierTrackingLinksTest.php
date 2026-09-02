<?php

namespace Tests\Feature;

use App\Models\Carrier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarrierTrackingLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tracking_home_is_the_template_without_its_query(): void
    {
        $this->assertSame('https://www.laposte.fr/outils/suivre-vos-envois', Carrier::trackingHomeUrl('lettre-suivie'));
        $this->assertSame('https://www.mondialrelay.fr/suivi-de-colis', Carrier::trackingHomeUrl('mondial-relay'));
        $this->assertNull(Carrier::trackingHomeUrl('unknown-carrier'));
    }

    public function test_every_carrier_card_links_to_its_tracking_page(): void
    {
        $html = $this->get('/livraison-et-retours')->assertOk()->getContent();

        foreach (['colissimo-home', 'chronopost-home', 'mondial-relay', 'relais-pickup', 'lettre-suivie'] as $slug) {
            $this->assertStringContainsString('href="'.Carrier::trackingHomeUrl($slug).'"', $html);
        }

        $this->assertStringContainsString('help-logo--link', $html);
        $this->assertStringContainsString('Suivre un envoi Lettre suivie', $html);
        $this->assertStringContainsString('Cliquez sur un transporteur pour ouvrir sa page de suivi', $html);
    }
}
