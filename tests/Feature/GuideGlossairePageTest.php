<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Glossary;
use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The glossary: not a dictionary but a legend for the catalogue, so every
 * entry has to land somewhere real.
 */
class GuideGlossairePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_entry_is_defined_and_filed_under_a_letter(): void
    {
        foreach (Glossary::resolved() as $entry) {
            $this->assertNotSame('', $entry['term']);
            $this->assertNotSame('', $entry['definition']);
            $this->assertNotSame('', $entry['source']);
            $this->assertMatchesRegularExpression('/^\p{Lu}$/u', $entry['initial']);
        }
    }

    public function test_the_entries_are_alphabetical_with_accents_folded(): void
    {
        $terms = Glossary::resolved()->pluck('term')->all();
        $sorted = $terms;

        usort($sorted, fn (string $a, string $b): int => strcmp(
            mb_strtolower(transliterator_transliterate('Any-Latin; Latin-ASCII', $a)),
            mb_strtolower(transliterator_transliterate('Any-Latin; Latin-ASCII', $b)),
        ));

        $this->assertSame($sorted, $terms);
    }

    public function test_a_filter_entry_counts_only_what_is_actually_for_sale(): void
    {
        $category = Category::factory()->create(['slug' => 'repliques-airsoft']);

        $attributes = [['label' => 'Propulsion', 'value' => 'Électrique (AEG)']];
        Product::factory()->count(2)->create(['category_id' => $category->id, 'is_active' => true, 'filter_attributes' => $attributes]);
        Product::factory()->create(['category_id' => $category->id, 'is_active' => false, 'filter_attributes' => $attributes]);

        $aeg = Glossary::resolved()->firstWhere('term', 'AEG');

        // The inactive one is not for sale, so it is not offered.
        $this->assertSame(2, $aeg['count']);
        $this->assertStringContainsString('filter', $aeg['url']);
        // The link says what it does. Echoing the filter's own value read
        // as two loose numbers beside the count: « 5 · 3 ».
        $this->assertSame('Voir les répliques électriques', $aeg['label']);
    }

    public function test_a_word_the_shop_sells_nothing_for_still_earns_its_place(): void
    {
        $notions = Glossary::resolved()->where('kind', 'notion');

        // At least a fifth of the glossary is vocabulary the catalogue has
        // no shelf for: the sport's own words, not the shop's stock list.
        $this->assertGreaterThanOrEqual(15, $notions->count());

        foreach ($notions as $entry) {
            $this->assertNull($entry['url']);
            $this->assertNull($entry['count']);
            // It says which part of the sport it belongs to, and stops.
            $this->assertNotSame('', $entry['source']);
            $this->assertStringNotContainsString('·', $entry['source']);
        }
    }

    public function test_every_link_says_what_it_does(): void
    {
        foreach (Glossary::resolved() as $entry) {
            if ($entry['url'] === null) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^(Voir|Lire) /u',
                $entry['label'],
                $entry['term'].' does not say what its link does',
            );
        }
    }

    public function test_a_filter_matching_nothing_keeps_its_definition_and_drops_its_link(): void
    {
        // No products at all: a link to an empty filtered rayon is a dead end.
        $aeg = Glossary::resolved()->firstWhere('term', 'AEG');

        $this->assertNull($aeg['url']);
        $this->assertNull($aeg['count']);
        $this->assertNotSame('', $aeg['definition']);
    }

    public function test_a_rayon_this_instance_does_not_carry_loses_its_link(): void
    {
        // Some rayons exist in production and not here. The entry keeps its
        // definition rather than pointing at a 404, the same rule an empty
        // filter obeys.
        $holster = Glossary::resolved()->firstWhere('term', 'Holster');

        $this->assertNull($holster['url']);
        $this->assertNotSame('', $holster['definition']);

        Category::factory()->create(['slug' => 'holsters']);

        $this->assertNotNull(Glossary::resolved()->firstWhere('term', 'Holster')['url']);
    }

    public function test_every_letter_of_the_alphabet_opens_something(): void
    {
        // An index with holes in it invites the reader to wonder what is
        // missing rather than what is there.
        $this->assertSame(range('A', 'Z'), Glossary::initials()->sort()->values()->all());
    }

    public function test_an_accented_word_files_under_its_plain_letter(): void
    {
        $letters = Glossary::resolved()
            ->filter(fn (array $entry): bool => str_starts_with($entry['term'], 'É'))
            ->pluck('initial')
            ->unique();

        // « Élévation » belongs in E, not in a letter of its own after Z.
        $this->assertNotEmpty($letters);
        $this->assertSame(['E'], $letters->values()->all());
    }

    public function test_the_page_indexes_itself_by_letter(): void
    {
        $html = $this->get('/guides/glossaire')->assertOk()->getContent();

        $initials = Glossary::initials();

        foreach ($initials as $letter) {
            $this->assertStringContainsString('href="#lettre-'.$letter.'"', $html);
            $this->assertStringContainsString('id="lettre-'.$letter.'"', $html);
        }

        // Every letter of the alphabet appears, the spent ones included, so
        // the index does not look as though it skipped one.
        foreach (range('A', 'Z') as $letter) {
            $this->assertStringContainsString('>'.$letter.'</', $html);
        }
    }

    public function test_each_term_is_a_heading_of_its_own(): void
    {
        $html = $this->get('/guides/glossaire')->assertOk()->getContent();

        // Forty headings are forty stops a screen reader can jump between,
        // which a definition list does not give.
        $this->assertSame(count(Glossary::entries()), substr_count($html, '<h3 class="gloss-term"'));

        foreach (Glossary::resolved()->take(3) as $entry) {
            $this->assertStringContainsString('id="'.Str::slug($entry['term']).'"', $html);
        }
    }

    public function test_each_entry_is_coloured_by_the_kind_of_place_it_leads_to(): void
    {
        $kinds = Glossary::resolved()->groupBy('kind');

        // Four kinds, four colours: stock you can filter, a shelf, the
        // shop's own writing, and a word it sells nothing for.
        $this->assertEqualsCanonicalizing(['filtre', 'rayon', 'lecture', 'notion'], $kinds->keys()->all());

        $html = $this->get('/guides/glossaire')->assertOk()->getContent();

        foreach ($kinds as $kind => $entries) {
            $this->assertSame($entries->count(), substr_count($html, 'gloss-where is-'.$kind));
        }
    }

    public function test_the_filter_field_ships_closed_and_the_list_does_not(): void
    {
        $html = $this->get('/guides/glossaire')->assertOk()->getContent();

        // Typing needs JavaScript; reading and the letter index do not.
        $this->assertMatchesRegularExpression('/data-gloss-search\s+hidden/', $html);
        $this->assertStringContainsString('js/guides/guides.js', $html);
        $this->assertSame(Glossary::entries() === [] ? 0 : count(Glossary::entries()), substr_count($html, 'data-gloss-entry'));
    }

    public function test_the_page_declares_its_terms_to_search_engines(): void
    {
        $html = $this->get('/guides/glossaire')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"DefinedTermSet"', $html);
        $this->assertSame(count(Glossary::entries()), substr_count($html, '"@type":"DefinedTerm"'));
        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.glossaire').'">', $html);
    }

    public function test_the_glossary_joins_the_shelf_the_index_and_the_sitemap_read(): void
    {
        $this->assertContains(route('guides.glossaire'), array_column(Guides::all(), 'url'));

        $this->get('/guides')->assertOk()->assertSee(route('guides.glossaire'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.glossaire'), false);
    }
}
