<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop answers on one hostname.
 *
 * armooutdoor.fr and www.armooutdoor.fr both served the whole catalogue, and
 * each page under www named itself as its own canonical, so search engines saw
 * two complete copies of the site with nothing joining them.
 */
class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_www_is_redirected_to_the_apex_domain(): void
    {
        $this->get('http://www.armooutdoor.fr/faq')
            ->assertStatus(301)
            ->assertRedirect('http://armooutdoor.fr/faq');
    }

    public function test_the_redirect_keeps_the_path_and_the_query_string(): void
    {
        $this->get('http://www.armooutdoor.fr/categories/cibles?sort=price_asc&page=2')
            ->assertStatus(301)
            ->assertRedirect('http://armooutdoor.fr/categories/cibles?sort=price_asc&page=2');
    }

    public function test_the_apex_domain_is_served_untouched(): void
    {
        $this->get('http://armooutdoor.fr/faq')->assertOk();
    }

    public function test_a_host_that_merely_contains_www_is_not_rewritten(): void
    {
        $this->get('http://wwwarmooutdoor.fr/faq')->assertOk();
    }
}
