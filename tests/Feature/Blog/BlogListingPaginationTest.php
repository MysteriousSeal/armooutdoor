<?php

namespace Tests\Feature\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\BlogCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The listing's page sizes: page one shows the newest post as a large
 * card above twelve others, and every later page shows twelve with no
 * featured card, so no post repeats or goes missing at the seam.
 */
class BlogListingPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlogCategorySeeder::class);
    }

    /**
     * Posts a day apart, returned newest first, each with a picture so the
     * cards carry an img whose loading attribute can be read.
     *
     * @return Collection<int, BlogPost>
     */
    private function posts(int $count, array $attributes = []): Collection
    {
        return collect(range(1, $count))->map(fn (int $age): BlogPost => BlogPost::factory()->create($attributes + [
            'published_at' => now()->subDays($age),
            'image' => 'blog/x.webp',
        ]));
    }

    /**
     * The cards of a page, in order.
     *
     * @return list<array{slug: string, featured: bool, lazy: bool}>
     */
    private function cards(TestResponse $response): array
    {
        preg_match_all('~<article class="(blog-card[^"]*)">(.*?)</article>~s', $response->getContent(), $matches, PREG_SET_ORDER);

        return array_map(fn (array $card): array => [
            'slug' => preg_match('~href="[^"]*/blog/([^"/?#]+)" class="blog-card-link"~', $card[2], $href) ? $href[1] : '',
            'featured' => str_contains($card[1], 'blog-card--featured'),
            'lazy' => str_contains($card[2], 'loading="lazy"'),
        ], $matches);
    }

    private function statusLine(TestResponse $response): ?string
    {
        return preg_match('~<p class="store-pager-status">\s*(.*?)\s*</p>~s', $response->getContent(), $match)
            ? html_entity_decode($match[1])
            : null;
    }

    private function blogStatus(int $first, int $last, int $total): string
    {
        return __('store.blog_pagination_status', ['first' => $first, 'last' => $last, 'total' => $total]);
    }

    private function heroCount(int $count): string
    {
        return trans_choice('store.blog_posts_count', $count, ['count' => $count]);
    }

    private function title(TestResponse $response): string
    {
        return preg_match('~<title>(.*?)</title>~s', $response->getContent(), $match) ? html_entity_decode($match[1]) : '';
    }

    /** @return array<string, array{int, list<int>}> */
    public static function pageSizes(): array
    {
        return [
            'one post' => [1, [1]],
            'twelve posts' => [12, [12]],
            'thirteen posts, still one page' => [13, [13]],
            'fourteen posts, one left for page two' => [14, [13, 1]],
            'twenty-five posts, two full pages' => [25, [13, 12]],
            'twenty-six posts, one on page three' => [26, [13, 12, 1]],
            'thirty posts' => [30, [13, 12, 5]],
        ];
    }

    /**
     * The whole rule at once, on the index and on a category page: the
     * card count of each page, the featured card only on page one and
     * always the newest post, every post exactly once in date order, the
     * hero's true total, and a status line counting the featured card in.
     *
     * @param  list<int>  $sizes
     */
    #[DataProvider('pageSizes')]
    public function test_page_one_holds_thirteen_and_every_later_page_twelve(int $count, array $sizes): void
    {
        $slugs = $this->posts($count)->pluck('slug')->all();

        foreach (['/blog', '/blog/conseils'] as $path) {
            $seen = [];

            foreach ($sizes as $offset => $size) {
                $page = $offset + 1;
                $response = $this->get($path.'?page='.$page)->assertOk();
                $cards = $this->cards($response);
                $where = $path.' page '.$page;

                $this->assertCount($size, $cards, $where);
                $this->assertSame($page === 1 ? 1 : 0, substr_count($response->getContent(), 'blog-card--featured'), $where);
                $this->assertSame($page === 1, $cards[0]['featured'], $where);
                if ($page === 1) {
                    $this->assertSame($slugs[0], $cards[0]['slug'], $where.': the featured card is the newest post');
                }

                $seen = [...$seen, ...array_column($cards, 'slug')];

                $response->assertSee($this->heroCount($count));
                if (count($sizes) > 1) {
                    $this->assertSame($this->blogStatus(count($seen) - $size + 1, count($seen), $count), $this->statusLine($response), $where);
                } else {
                    $response->assertDontSee('store-pager', false);
                }
            }

            $this->assertSame($slugs, $seen, $path.': every post once, newest first');
            $this->assertSame([], $this->cards($this->get($path.'?page='.(count($sizes) + 1))->assertOk()));
        }
    }

    public function test_no_post_shows_the_empty_state_and_no_card(): void
    {
        $response = $this->get('/blog')->assertOk()
            ->assertSee(__('store.blog_empty'))
            ->assertSee($this->heroCount(0))
            ->assertDontSee('store-pager', false);
        $this->assertSame([], $this->cards($response));

        $this->get('/blog/conseils')->assertOk()->assertSee(__('store.blog_empty_category'));
    }

    public function test_a_lone_post_is_the_featured_card_and_nothing_else(): void
    {
        $post = $this->posts(1)->first();

        $response = $this->get('/blog')->assertOk()
            ->assertDontSee(__('store.blog_empty'))
            ->assertSee($this->heroCount(1));

        $this->assertSame([['slug' => $post->slug, 'featured' => true, 'lazy' => false]], $this->cards($response));
    }

    /** The count the lead once got wrong: page one starts at 1, not 2. */
    public function test_the_status_line_counts_the_featured_card_in(): void
    {
        $this->posts(18);

        $first = $this->get('/blog')->assertOk();
        $this->assertSame($this->blogStatus(1, 13, 18), $this->statusLine($first));
        $this->assertStringNotContainsString($this->blogStatus(2, 13, 18), $first->getContent());
        $this->assertStringNotContainsString('Produits ', (string) $this->statusLine($first));

        $second = $this->get('/blog?page=2')->assertOk();
        $this->assertSame($this->blogStatus(14, 18, 18), $this->statusLine($second));

        // The hero and the category tab still count all eighteen.
        foreach ([$first, $second] as $response) {
            $response->assertSee($this->heroCount(18))
                ->assertDontSee($this->heroCount(17))
                ->assertSee('<span class="blog-tab-count">18</span>', false);
        }
    }

    public function test_only_a_visible_post_can_be_featured(): void
    {
        $older = $this->posts(2);
        $draft = BlogPost::factory()->draft()->create();
        $scheduled = BlogPost::factory()->scheduled()->create();

        $response = $this->get('/blog')->assertOk();

        $this->assertSame($older->pluck('slug')->all(), array_column($this->cards($response), 'slug'));
        $this->assertTrue($this->cards($response)[0]['featured']);
        $response->assertDontSee($draft->localizedTitle(), false)
            ->assertDontSee($scheduled->localizedTitle(), false);
    }

    public function test_a_tie_on_the_date_features_the_later_post(): void
    {
        $at = now()->subDay()->startOfMinute();
        $earlier = BlogPost::factory()->create(['published_at' => $at]);
        $later = BlogPost::factory()->create(['published_at' => $at]);

        $cards = $this->cards($this->get('/blog')->assertOk());

        $this->assertSame([$later->slug, $earlier->slug], array_column($cards, 'slug'));
        $this->assertSame([true, false], array_column($cards, 'featured'));
    }

    /** A newer post elsewhere on the blog is not the category's featured card. */
    public function test_a_category_features_its_own_newest_post(): void
    {
        $guides = $this->posts(20)->pluck('slug')->all();
        $news = BlogPost::factory()->create([
            'blog_category_id' => BlogCategory::query()->where('slug', 'actualites')->value('id'),
            'published_at' => now()->subHour(),
        ]);

        $first = $this->get('/blog/conseils')->assertOk()->assertSee($this->heroCount(20));
        $second = $this->get('/blog/conseils?page=2')->assertOk();

        $this->assertSame($guides[0], $this->cards($first)[0]['slug']);
        $this->assertTrue($this->cards($first)[0]['featured']);
        $this->assertSame($guides, array_column([...$this->cards($first), ...$this->cards($second)], 'slug'));
        $this->assertSame($this->blogStatus(1, 13, 20), $this->statusLine($first));
        $this->assertSame($this->blogStatus(14, 20, 20), $this->statusLine($second));
        $this->assertStringNotContainsString('blog-card--featured', $second->getContent());

        // The unfiltered index features the newer post and counts it in.
        $index = $this->get('/blog')->assertOk()->assertSee($this->heroCount(21));
        $this->assertSame($news->slug, $this->cards($index)[0]['slug']);
        $this->assertSame($this->blogStatus(1, 13, 21), $this->statusLine($index));
    }

    /** Two eager pictures a page: on page one the featured card is one of them. */
    public function test_only_the_first_two_pictures_of_a_page_load_eagerly(): void
    {
        $this->posts(30);

        foreach ([1 => 13, 2 => 12, 3 => 5] as $page => $size) {
            $lazy = array_column($this->cards($this->get('/blog?page='.$page)), 'lazy');

            $this->assertSame([false, false, ...array_fill(0, $size - 2, true)], $lazy, 'page '.$page);
        }
    }

    public function test_page_two_names_itself_and_page_one_stays_plain(): void
    {
        $this->posts(14);
        $suffix = __('store.pagination_page', ['page' => 2]);

        $first = $this->get('/blog')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('blog.index').'">', false)
            ->assertDontSee('rel="prev"', false)
            ->assertSee('rel="next"', false);
        $this->assertStringNotContainsString($suffix, $this->title($first));

        $second = $this->get('/blog?page=2')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('blog.index').'?page=2">', false)
            ->assertSee('rel="prev"', false)
            ->assertDontSee('rel="next"', false);
        $this->assertStringContainsString($suffix, $this->title($second));

        $this->get('/blog/conseils?page=2')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('blog.category', 'conseils').'?page=2">', false);
    }

    public function test_the_pager_links_keep_the_query_string(): void
    {
        $this->posts(14);

        $content = $this->get('/blog?page=2&utm_source=x')->assertOk()->getContent();

        $this->assertSame(1, preg_match('~<a class="store-pager-arrow" href="([^"]+)" rel="prev">~', $content, $prev));
        $this->assertStringContainsString('utm_source=x', $prev[1]);
    }

    /** Past the end: nothing listed, nothing featured, the full count kept. */
    public function test_a_page_past_the_end_lists_nothing(): void
    {
        $this->posts(18);

        $response = $this->get('/blog?page=9')->assertOk()
            ->assertSee(__('store.blog_empty'))
            ->assertSee($this->heroCount(18))
            ->assertSee('<link rel="canonical" href="'.route('blog.index').'">', false)
            ->assertDontSee('store-pager', false)
            ->assertDontSee('blog-card--featured', false);

        $this->assertSame([], $this->cards($response));
        $this->assertStringNotContainsString(__('store.pagination_page', ['page' => 9]), $this->title($response));
    }

    public function test_a_nonsense_page_number_is_page_one(): void
    {
        $newest = $this->posts(14)->first();

        foreach (['0', '-1', 'abc'] as $page) {
            $cards = $this->cards($this->get('/blog?page='.$page)->assertOk());

            $this->assertCount(13, $cards, 'page='.$page);
            $this->assertSame($newest->slug, $cards[0]['slug'], 'page='.$page);
            $this->assertTrue($cards[0]['featured'], 'page='.$page);
        }
    }

    /** The card's other hosts never get the large variant. */
    public function test_related_posts_and_product_pages_keep_the_plain_card(): void
    {
        [$post, $other] = $this->posts(2)->all();
        $product = Product::factory()->create(['is_active' => true]);
        $other->products()->attach($product->id, ['sort_order' => 0]);

        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertSee('<article class="blog-card">', false)
            ->assertDontSee('blog-card--featured', false);

        $this->get('/products/'.$product->slug)->assertOk()
            ->assertSee('<article class="blog-card">', false)
            ->assertDontSee('blog-card--featured', false);
    }

    /** The shared pager keeps its product wording and plain numbers. */
    public function test_product_listings_keep_their_own_status_line(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(30)->create(['category_id' => $category->id, 'is_active' => true]);
        $productStatus = fn (int $first, int $last): string => __('store.pagination_status', ['first' => $first, 'last' => $last, 'total' => 30]);

        $this->assertSame($productStatus(1, 24), $this->statusLine($this->get('/produits')->assertOk()));
        $this->assertSame($productStatus(25, 30), $this->statusLine($this->get('/produits?page=2')->assertOk()));
        $this->assertSame($productStatus(1, 24), $this->statusLine($this->get('/categories/'.$category->slug)->assertOk()));
        $this->assertSame($productStatus(25, 30), $this->statusLine($this->get('/categories/'.$category->slug.'?page=2')->assertOk()));
        $this->assertStringNotContainsString('Articles ', (string) $this->statusLine($this->get('/produits')));
    }
}
