<?php

namespace Tests\Feature\Blog;

use App\Support\HtmlSanitizer;
use Tests\TestCase;

/**
 * Les images dans le corps d'un article.
 *
 * Autorisées, mais servies par la boutique et par personne d'autre : une
 * balise `<img>` pointant ailleurs est une fuite de référent et un mouchard
 * potentiel. La permission est explicite, pour que les fiches produit — qui
 * passent par le même nettoyeur — n'en héritent pas au passage.
 */
class BlogBodyImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://armooutdoor.test']);
    }

    public function test_a_root_relative_image_is_kept(): void
    {
        $clean = HtmlSanitizer::clean('<p>x</p><img src="/images/blog/a.webp" alt="Une réplique">', allowImages: true);

        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('/images/blog/a.webp', $clean);
        $this->assertStringContainsString('alt="Une réplique"', $clean);
    }

    public function test_an_absolute_url_on_our_own_host_is_kept(): void
    {
        $clean = HtmlSanitizer::clean('<img src="https://armooutdoor.test/images/blog/a.webp">', allowImages: true);

        $this->assertStringContainsString('<img', $clean);
    }

    /**
     * Le piège : commence par une barre oblique, charge pourtant ailleurs.
     */
    public function test_a_protocol_relative_image_is_removed(): void
    {
        $clean = HtmlSanitizer::clean('<p>a</p><img src="//evil.example/x.jpg">', allowImages: true);

        $this->assertStringNotContainsString('<img', (string) $clean);
        $this->assertStringNotContainsString('evil.example', (string) $clean);
    }

    public function test_a_foreign_host_is_removed(): void
    {
        $clean = HtmlSanitizer::clean('<p>a</p><img src="https://evil.example/x.jpg">', allowImages: true);

        $this->assertStringNotContainsString('<img', (string) $clean);
        $this->assertStringNotContainsString('evil.example', (string) $clean);
    }

    public function test_a_data_uri_image_is_removed(): void
    {
        $clean = HtmlSanitizer::clean('<p>a</p><img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=">', allowImages: true);

        $this->assertStringNotContainsString('<img', (string) $clean);
        $this->assertStringNotContainsString('data:', (string) $clean);
    }

    /** Retirée entièrement, pas vidée de sa source : pas d'icône cassée. */
    public function test_a_refused_image_leaves_no_bare_tag(): void
    {
        $clean = (string) HtmlSanitizer::clean('<img src="https://evil.example/x.jpg"><p>suite</p>', allowImages: true);

        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringContainsString('suite', $clean);
    }

    public function test_a_kept_image_always_has_an_alt(): void
    {
        $clean = (string) HtmlSanitizer::clean('<img src="/images/blog/a.webp">', allowImages: true);

        $this->assertStringContainsString('alt=""', $clean);
    }

    public function test_figure_and_caption_survive_together(): void
    {
        $clean = (string) HtmlSanitizer::clean(
            '<figure><img src="/images/blog/a.webp"><figcaption>Légende</figcaption></figure>',
            allowImages: true,
        );

        $this->assertStringContainsString('<figure>', $clean);
        $this->assertStringContainsString('<figcaption>', $clean);
        $this->assertStringContainsString('Légende', $clean);
    }

    /**
     * Le même balisage sur le chemin des fiches produit : l'image tombe.
     * C'est tout l'intérêt d'un opt-in plutôt que d'une liste élargie.
     */
    public function test_the_product_path_still_strips_images(): void
    {
        $markup = '<p>texte</p><img src="/images/blog/a.webp" alt="x">';

        $clean = (string) HtmlSanitizer::clean($markup);

        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringContainsString('texte', $clean);
    }

    public function test_scripts_never_survive_even_with_images_allowed(): void
    {
        $clean = (string) HtmlSanitizer::clean('<p>a</p><script>alert(1)</script>', allowImages: true);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_image_attributes_do_not_leak_onto_other_tags(): void
    {
        $clean = (string) HtmlSanitizer::clean('<p src="/x.webp" loading="lazy">texte</p>', allowImages: true);

        $this->assertStringNotContainsString('src=', $clean);
        $this->assertStringNotContainsString('loading=', $clean);
    }
}
