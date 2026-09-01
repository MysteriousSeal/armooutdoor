<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The four section headings on the home page.
 *
 * A small accent label names the aisle, the title says what is in it. They
 * layer rather than compete: the uppercase note lives in the label, so the
 * title does not have to shout it too.
 */
class HomeSectionHeadingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enough of a catalogue for all four sections to render: « featured »
        // takes ten and « more » draws from what is left, so a handful of
        // products leaves the last section with nothing to show.
        $category = Category::factory()->create();

        foreach (range(1, 20) as $i) {
            $product = Product::factory()->create([
                'is_active' => true,
                'category_id' => $category->id,
                'name' => ['fr' => 'Cible '.$i],
                'price_cents' => 2000,
            ]);

            if ($i === 1) {
                Discount::query()->create([
                    'product_id' => $product->id,
                    'type' => 'percentage',
                    'value' => 20,
                ]);
            }
        }
    }

    public static function headings(): array
    {
        return [
            'offres' => ['home_kicker_deals', 'home_deals'],
            'categories' => ['home_kicker_categories', 'shop_by_category'],
            'selection' => ['home_kicker_featured', 'featured'],
            'reste' => ['home_kicker_more', 'home_more'],
        ];
    }

    #[DataProvider('headings')]
    public function test_each_section_is_labelled_then_titled(string $kicker, string $title): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<p class="home-cats-kicker">'.__('store.'.$kicker).'</p>', false)
            ->assertSee(__('store.'.$title));
    }

    #[DataProvider('headings')]
    public function test_the_label_comes_before_the_title(string $kicker, string $title): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, __('store.'.$title)),
            strpos($html, __('store.'.$kicker)),
            'The kicker should sit above the title it names.',
        );
    }

    public function test_the_titles_no_longer_shout(): void
    {
        // Uppercase at weight 800 sat oddly under a tagline promising
        // discretion; the uppercase note is the kicker's job now.
        $css = file_get_contents(public_path('css/home.css'));

        preg_match('/\.home \.home-cats-title \{(.*?)\}/s', $css, $rule);

        $this->assertNotEmpty($rule);
        $this->assertStringNotContainsString('text-transform: uppercase', $rule[1]);
        $this->assertStringNotContainsString('font-weight: 800', $rule[1]);
        $this->assertStringContainsString('font-weight: 600', $rule[1]);
    }

    public function test_the_hairline_is_drawn_once_and_dropped_when_narrow(): void
    {
        $css = file_get_contents(public_path('css/home.css'));

        $this->assertStringContainsString('.home .home-cats-header::after', $css);
        // Under 40rem the link drops below the title, and a line between them
        // would only separate them from each other.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 40rem\).*?home-cats-header::after \{\s*display: none;/s',
            $css,
        );
    }

    public function test_the_title_carries_the_accent_band_the_hero_uses(): void
    {
        // A highlighter stroke, as on the category hero, not a rule.
        $css = file_get_contents(public_path('css/home.css'));

        preg_match('/\.home \.home-cats-title \{(.*?)\}/s', $css, $rule);

        $this->assertNotEmpty($rule);
        $this->assertStringContainsString('color: var(--accent-heading);', $rule[1]);
        $this->assertStringContainsString('color-mix(in srgb, var(--accent-heading)', $rule[1]);
        $this->assertStringContainsString('background-size: 100% 0.18em;', $rule[1]);
        // Aligned to its own text, so the band ends where the words do.
        $this->assertStringContainsString('align-self: flex-start;', $rule[1]);
    }

    public function test_the_heading_accent_has_a_value_for_both_themes(): void
    {
        // The literal the rest of the shop copies has none, so on a dark
        // ground it stays dark olive on dark.
        $base = file_get_contents(public_path('css/base.css'));

        $this->assertMatchesRegularExpression('/:root \{[^}]*--accent-heading: #5b5c3b;/s', $base);
        $this->assertMatchesRegularExpression("/\[data-theme='dark'\] \{[^}]*--accent-heading:/s", $base);
    }
}
