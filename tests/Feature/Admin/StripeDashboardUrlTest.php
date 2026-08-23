<?php

namespace Tests\Feature\Admin;

use App\Support\StripeDashboard;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Les liens vers le tableau de bord Stripe.
 *
 * Stripe range les données de test derrière un segment /test/. Ce segment
 * était écrit dans tous les liens : le jour du premier vrai paiement, le
 * lien aurait mené à une page vide, que l'on aurait lue comme un paiement
 * manquant plutôt que comme une mauvaise adresse.
 */
class StripeDashboardUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // L'identifiant de compte est mis en cache : on le pose à la main
        // plutôt que d'appeler Stripe depuis un test.
        Cache::forever('stripe_account_id', 'acct_123');
    }

    public function test_a_test_key_keeps_the_test_segment(): void
    {
        config(['services.stripe.secret' => 'sk_test_abc']);

        $this->assertSame(
            'https://dashboard.stripe.com/acct_123/test/payments/pi_1',
            StripeDashboard::paymentIntentUrl('pi_1')
        );
    }

    public function test_a_live_key_drops_it(): void
    {
        config(['services.stripe.secret' => 'sk_live_abc']);

        $this->assertSame(
            'https://dashboard.stripe.com/acct_123/payments/pi_1',
            StripeDashboard::paymentIntentUrl('pi_1')
        );
    }

    public function test_the_customer_link_follows_the_same_mode(): void
    {
        config(['services.stripe.secret' => 'sk_live_abc']);
        $this->assertSame('https://dashboard.stripe.com/acct_123/customers/cus_1', StripeDashboard::customerUrl('cus_1'));

        config(['services.stripe.secret' => 'sk_test_abc']);
        $this->assertSame('https://dashboard.stripe.com/acct_123/test/customers/cus_1', StripeDashboard::customerUrl('cus_1'));
    }

    public function test_an_unknown_key_counts_as_test(): void
    {
        // Se tromper vers le test est bénin : la page 404 se voit. Se tromper
        // vers le direct affiche une page vide, que l'on lit comme une perte.
        foreach (['rk_live_abc', 'pk_live_abc', 'whatever', ''] as $secret) {
            config(['services.stripe.secret' => $secret]);

            $this->assertFalse(StripeDashboard::isLiveMode(), 'clé : '.$secret);
            $this->assertStringContainsString('/test/', (string) StripeDashboard::paymentIntentUrl('pi_1'));
        }
    }

    public function test_a_missing_key_counts_as_test(): void
    {
        config(['services.stripe.secret' => null]);

        $this->assertFalse(StripeDashboard::isLiveMode());
    }

    public function test_no_account_means_no_link(): void
    {
        Cache::forget('stripe_account_id');
        Cache::forever('stripe_account_id', null);
        config(['services.stripe.secret' => 'sk_live_abc']);

        // Sans identifiant de compte le lien tombe sur le compte actif du
        // navigateur : mieux vaut pas de lien du tout.
        $this->assertNull(StripeDashboard::paymentIntentUrl('pi_1'));
        $this->assertNull(StripeDashboard::customerUrl('cus_1'));
    }

    public function test_the_test_segment_is_no_longer_hardcoded(): void
    {
        $source = (string) file_get_contents(app_path('Support/StripeDashboard.php'));

        // La régression serait de le réécrire en dur dans une des deux URL.
        $this->assertStringNotContainsString('/test/payments/', $source);
        $this->assertStringNotContainsString('/test/customers/', $source);
    }
}
