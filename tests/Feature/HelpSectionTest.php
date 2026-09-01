<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The FAQ is the landing page of the help section.
 *
 * Rather than build an index page holding four links and nothing else, the
 * page that was already doing the job takes the title: the siblings point
 * their section crumb at it, and it wears the same header and index they do.
 */
class HelpSectionTest extends TestCase
{
    use RefreshDatabase;

    public static function siblings(): array
    {
        return [
            'livraison' => ['/livraison-et-retours'],
            'paiement' => ['/paiement-securise'],
        ];
    }

    private function trail(string $url): array
    {
        preg_match_all(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $this->get($url)->assertOk()->getContent(),
            $blocks,
        );

        foreach ($blocks[1] as $block) {
            $decoded = json_decode(trim($block), true);

            if (($decoded['@type'] ?? '') === 'BreadcrumbList') {
                return array_column($decoded['itemListElement'], 'name');
            }
        }

        return [];
    }

    public function test_the_faq_wears_the_section_header_and_index(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertSee('class="panel-header"', false)
            ->assertSee('class="help-layout"', false)
            ->assertSee('class="help-aside"', false);
    }

    public function test_the_faq_loads_the_stylesheet_its_layout_lives_in(): void
    {
        // It wears the help layout but kept only its own stylesheet, so the
        // sidebar and the grid rendered unstyled: correct markup, no rules.
        $this->get('/faq')
            ->assertOk()
            ->assertSee('css/help.css', false)
            ->assertSee('css/faq.css', false);
    }

    public function test_the_faq_is_the_section_rather_than_a_page_inside_it(): void
    {
        // Its own crumb stops at Aide: you are already there.
        $this->get('/faq')
            ->assertOk()
            ->assertSee('<span class="breadcrumbs-section">Aide</span>', false);

        $this->assertSame(['Accueil', 'Aide'], $this->trail('/faq'));
    }

    #[DataProvider('siblings')]
    public function test_a_sibling_links_its_section_crumb_to_the_faq(string $url): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('<a href="'.route('faq').'">Aide</a>', false);
    }

    #[DataProvider('siblings')]
    public function test_the_section_now_earns_its_place_in_the_trail(string $url): void
    {
        // It was left out while it had no address to give.
        $this->assertSame('Aide', $this->trail($url)[1] ?? null);
        $this->assertCount(3, $this->trail($url));
    }

    #[DataProvider('siblings')]
    public function test_the_legal_pages_keep_an_unlinked_section(string $url): void
    {
        // Only the help section gained a landing page; the legal one did not.
        $this->get('/cgv')
            ->assertOk()
            ->assertSee('<span class="breadcrumbs-section">Informations légales</span>', false);
    }
}
