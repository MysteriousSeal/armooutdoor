<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The help pages sit on the same edges as the rest of the shop.
 *
 * They stopped at 52rem inside a container twice that wide. The width goes to
 * the help index rather than to the prose, which keeps a measure it can be
 * read at.
 */
class HelpLayoutTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'livraison' => ['/livraison-et-retours', 'help.shipping-returns'],
            'paiement' => ['/paiement-securise', 'help.secure-payment'],
        ];
    }

    /**
     * The same pages, addresses only.
     *
     * PHPUnit warns when a data set passes more arguments than the test
     * accepts, so a method that only needs the URL is fed only the URL.
     */
    public static function urls(): array
    {
        return array_map(fn (array $row): array => [$row[0]], self::pages());
    }

    #[DataProvider('urls')]
    public function test_each_page_sets_its_content_beside_the_index(string $url): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('class="help-layout"', false)
            ->assertSee('class="help-aside"', false);
    }

    #[DataProvider('pages')]
    public function test_each_page_links_to_the_others_and_marks_itself(string $url, string $route): void
    {
        $response = $this->get($url)->assertOk();

        foreach (['faq', 'help.shipping-returns', 'help.secure-payment', 'contact.show'] as $sibling) {
            $response->assertSee('href="'.route($sibling).'"', false);
        }

        $response->assertSee('aria-current="page"', false);
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'aria-current="page"'),
            'Exactly one help link should be marked current.',
        );
    }

    #[DataProvider('urls')]
    public function test_the_markup_still_closes_everything_it_opens(string $url): void
    {
        // Two wrappers were added around an existing body; a stray </div>
        // would break the footer rather than these pages.
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
    }

    public function test_the_pages_no_longer_carry_their_own_width(): void
    {
        $css = file_get_contents(public_path('css/help.css'));

        $this->assertStringNotContainsString('max-width: 52rem', $css);
        $this->assertStringContainsString('.help-layout {', $css);
    }
}
