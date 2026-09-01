<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which of the two order e-mails is handed to the sender first.
 *
 * Both are queued in the same terminating phase, so a sender that limits
 * messages per second refuses the second one. Losing the notice that a sale
 * needs handling costs more than delaying a receipt the customer can also read
 * in their account, so the shop's notice goes first.
 */
class OrderEmailOrderingTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function callOrderIn(string $path, string $variable): array
    {
        preg_match_all(
            '/\\'.$variable.'->send(AdminNewOrder|Confirmation)Email\(\);/',
            file_get_contents(base_path($path)),
            $matches,
        );

        return $matches[1];
    }

    public function test_the_checkout_tells_the_shop_before_the_customer(): void
    {
        $this->assertSame(
            ['AdminNewOrder', 'Confirmation'],
            $this->callOrderIn('app/Http/Controllers/CheckoutController.php', '$order'),
        );
    }

    public function test_a_manual_order_does_the_same(): void
    {
        $file = base_path('app/Http/Controllers/Admin/OrderController.php');

        preg_match_all('/->send(AdminNewOrder|Confirmation)Email\(\);/', file_get_contents($file), $matches);

        // Every pair in the file leads with the shop's notice.
        $pairs = array_chunk($matches[1], 2);

        foreach ($pairs as $pair) {
            if (count($pair) === 2) {
                $this->assertSame(['AdminNewOrder', 'Confirmation'], $pair);
            }
        }

        $this->assertNotEmpty($pairs);
    }

    public function test_both_are_still_sent(): void
    {
        // Reordering must not have dropped one.
        $this->assertTrue(method_exists(Order::class, 'sendAdminNewOrderEmail'));
        $this->assertTrue(method_exists(Order::class, 'sendConfirmationEmail'));

        foreach (['app/Http/Controllers/CheckoutController.php', 'app/Http/Controllers/Admin/OrderController.php'] as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertStringContainsString('sendAdminNewOrderEmail();', $source);
            $this->assertStringContainsString('sendConfirmationEmail();', $source);
        }
    }
}
