<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The section a page belongs to, named in its trail.
 *
 * Six pages went straight from Accueil to their own title, so nothing said
 * whether a page was help or law until you read it.
 */
class SectionBreadcrumbTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'cgv' => ['/cgv', 'Informations légales'],
            'mentions' => ['/mentions-legales', 'Informations légales'],
            'confidentialite' => ['/confidentialite', 'Informations légales'],
            'retractation' => ['/droit-de-retractation', 'Informations légales'],
            'livraison' => ['/livraison-et-retours', 'Aide'],
            'paiement' => ['/paiement-securise', 'Aide'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_page_names_its_section(string $url, string $section): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('<span class="breadcrumbs-section">'.$section.'</span>', false);
    }

    #[DataProvider('pages')]
    public function test_the_section_is_named_and_never_linked(string $url): void
    {
        // It has no page of its own; a crumb pointing at a sibling would be
        // worse than one pointing nowhere.
        preg_match(
            '#<nav class="breadcrumbs".*?</nav>#s',
            $this->get($url)->assertOk()->getContent(),
            $crumbs,
        );

        $this->assertNotEmpty($crumbs);
        // Only "Accueil" is a link; the section and the title are not.
        $this->assertSame(1, substr_count($crumbs[0], '<a '));
    }

    #[DataProvider('pages')]
    public function test_the_trail_declares_itself_without_the_sectionless_step(string $url): void
    {
        // Google wants an address for every element but the last, and the
        // section has none, so it stays out of the structured data.
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('"name":"Informations légales","item":null', $html);
    }
}
