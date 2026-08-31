<?php

namespace Tests\Unit;

use App\Support\SessionFlow;
use PHPUnit\Framework\TestCase;

class SessionFlowTest extends TestCase
{
    public function test_page_group_buckets_known_paths(): void
    {
        $this->assertSame('Home', SessionFlow::pageGroup('/'));
        $this->assertSame('Product', SessionFlow::pageGroup('/products/ridge-tent'));
        $this->assertSame('Browse', SessionFlow::pageGroup('/categories/cibles'));
        $this->assertSame('Browse', SessionFlow::pageGroup('/search'));
        $this->assertSame('Cart', SessionFlow::pageGroup('/cart'));
        $this->assertSame('Checkout', SessionFlow::pageGroup('/checkout'));
        $this->assertSame('Order', SessionFlow::pageGroup('/orders/A1B2C3'));
        $this->assertSame('Account', SessionFlow::pageGroup('/account/profile'));
        $this->assertSame('Info', SessionFlow::pageGroup('/faq'));
        $this->assertSame('Other', SessionFlow::pageGroup('/some-unknown-page'));
    }

    public function test_group_sequence_collapses_consecutive_duplicates(): void
    {
        $paths = ['/', '/', '/categories/cibles', '/products/a', '/products/a', '/cart', '/checkout', '/orders/1'];

        $this->assertSame(['Home', 'Browse', 'Product', 'Cart', 'Checkout', 'Order'], SessionFlow::groupSequence($paths));
    }

    public function test_group_sequence_caps_at_the_step_limit(): void
    {
        $paths = ['/', '/categories/a', '/products/a', '/cart', '/checkout', '/blog', '/faq', '/account', '/orders/1'];

        $sequence = SessionFlow::groupSequence($paths);

        $this->assertCount(SessionFlow::STEPS, $sequence);
        $this->assertSame(['Home', 'Browse', 'Product', 'Cart', 'Checkout', 'Blog', 'Info', 'Account'], $sequence);
    }

    public function test_build_totals_and_exit_node_match_session_count(): void
    {
        $sessions = [
            ['Home', 'Product', 'Cart', 'Checkout'],
            ['Home', 'Product'],
        ];

        $flow = SessionFlow::build($sessions, 880, 380);

        $this->assertSame(2, $flow['total']);

        // Session 2 (Home, Product) stops after its 2nd page — it must show
        // up as an exit in the 3rd column, not silently vanish.
        $exitNode = collect($flow['nodes'])->first(fn ($n) => $n['isExit']);
        $this->assertNotNull($exitNode);
        $this->assertSame(1, $exitNode['count']);
        $this->assertSame(2, $exitNode['step']);

        // Every link layer must sum back to the sessions still active at that point.
        $layer1 = collect($flow['table'])->where('layer', 1)->sum('count');
        $this->assertSame(2, $layer1);
    }

    public function test_build_carries_link_keys_and_a_legend_for_the_diagram(): void
    {
        $flow = SessionFlow::build([
            ['Home', 'Product', 'Cart'],
            ['Home', 'Product'],
        ]);

        // Links are addressable by step-qualified endpoints, so the hover
        // script can find everything touching a node.
        $first = $flow['links'][0];
        $this->assertSame('0:Home', $first['from']);
        $this->assertSame('1:Product', $first['to']);

        $legendLabels = array_column($flow['legend'], 'label');
        $this->assertSame(['Home', 'Product', 'Cart', SessionFlow::EXIT_LABEL], $legendLabels);
    }

    public function test_labels_hide_on_nodes_too_thin_to_carry_them(): void
    {
        // 199 sessions through Product and a lone one through Browse: the
        // Browse node is a sliver of its column and must not draw a label.
        $sessions = array_fill(0, 199, ['Home', 'Product']);
        $sessions[] = ['Home', 'Browse'];

        $nodes = collect(SessionFlow::build($sessions)['nodes']);

        $this->assertTrue($nodes->firstWhere('label', 'Home')['labelVisible']);
        $this->assertFalse($nodes->firstWhere('label', 'Browse')['labelVisible']);
    }

    public function test_bounces_leave_the_diagram_but_are_counted(): void
    {
        $flow = SessionFlow::build([
            ['Home'],
            ['Home'],
            ['Home', 'Product'],
        ]);

        $this->assertSame(1, $flow['total']);
        $this->assertSame(2, $flow['bounces']);
        // Shares are of the drawn sessions, not the bounces.
        $this->assertSame(100.0, collect($flow['nodes'])->firstWhere('label', 'Home')['percent']);
    }

    public function test_rare_paths_keep_a_visible_floor_height(): void
    {
        $sessions = array_fill(0, 199, ['Home', 'Product']);
        $sessions[] = ['Home', 'Browse'];

        $browse = collect(SessionFlow::build($sessions)['nodes'])->firstWhere('label', 'Browse');

        $this->assertGreaterThanOrEqual(8, $browse['height']);
    }

    public function test_build_returns_empty_shell_for_no_sessions(): void
    {
        $flow = SessionFlow::build([]);

        $this->assertSame(0, $flow['total']);
        $this->assertSame([], $flow['nodes']);
        $this->assertSame([], $flow['links']);
    }
}
