<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_says_who_the_shop_is(): void
    {
        $this->get('/a-propos')->assertOk()
            ->assertSee('À propos')
            ->assertSee('La boutique')
            ->assertSee('Pourquoi avoir créé Armo Outdoor')
            ->assertSee('Nos engagements')
            ->assertSee('mentions légales')
            ->assertSee('css/help.css');
    }

    public function test_its_about_schema_parses_and_points_at_the_organization(): void
    {
        $html = $this->get('/a-propos')->assertOk()->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $blocks);
        $types = [];

        foreach ($blocks[1] as $json) {
            $data = json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR);
            $types[] = $data['@type'];
        }

        $this->assertContains('AboutPage', $types);
        $this->assertContains('BreadcrumbList', $types);
    }

    public function test_the_home_about_button_and_the_footer_lead_there(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The button under « Une boutique française à taille humaine ».
        $aboutSection = Str::before(Str::after($html, 'home-about-title'), '</section>');
        $this->assertStringContainsString(route('about'), $aboutSection);

        // And the footer's Aide & Infos column.
        $this->assertStringContainsString('>À propos</a>', $html);
    }

    public function test_the_pages_sitemap_names_it(): void
    {
        $this->get('/sitemap-pages.xml')->assertOk()->assertSee(route('about'));
    }
}
