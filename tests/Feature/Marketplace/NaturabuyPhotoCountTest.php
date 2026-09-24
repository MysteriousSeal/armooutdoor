<?php

namespace Tests\Feature\Marketplace;

use App\Models\NaturabuyListing;
use App\Models\Product;
use App\Models\User;
use App\Services\Naturabuy\NaturabuyPhotoCounter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The photo count of each NaturaBuy listing, read from its public page one
 * listing a minute. Nothing here reaches their site: every page is faked.
 */
class NaturabuyPhotoCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 12:00:00');
    }

    private function listing(int $id, array $overrides = []): NaturabuyListing
    {
        $listing = NaturabuyListing::query()->create([
            'naturabuy_id' => $id,
            'title' => 'Cibles '.$id,
            'url' => 'Cibles-item-'.$id.'.html',
            'internalcode' => 'CODE-'.$id,
            'price_cents' => 999,
            'quantity' => 3,
            'out_of_stock' => false,
            'closed' => $overrides['closed'] ?? false,
            'synced_at' => now(),
        ]);

        unset($overrides['closed']);

        if ($overrides !== []) {
            $listing->forceFill($overrides)->save();
        }

        return $listing;
    }

    /** A page shaped like theirs: its own photos, full size and as thumbnails, next to other listings' and brand logos. */
    private function page(int $id, array $photos): string
    {
        $html = '<html><body>';

        foreach ($photos as $number) {
            $html .= '<img src="https://static.naturabuy.fr/uploaded/20260428/'.$id.'/'.$number.'_Cibles.jpg">';
            $html .= '<img src="https://static.naturabuy.fr/uploaded/20260428/'.$id.'/thumbs/600h600f_'.$number.'_Cibles.jpg">';
            $html .= '<img src="https://static.naturabuy.fr/uploaded/20260428/'.$id.'/thumbs/60_'.$number.'_Cibles.jpg">';
        }

        // Related listings and brands: other ids, never counted.
        $html .= '<img src="https://static.naturabuy.fr/uploaded/20260505/14974344/thumbs/250or2_00003_Autre.jpg">';
        $html .= '<img src="https://static.naturabuy.fr/output/brands/thumbs/71h71_brand-89.jpg">';

        return $html.'</body></html>';
    }

    public function test_each_photo_of_the_listing_counts_once(): void
    {
        $html = $this->page(14945316, ['00003', '00004']);

        $this->assertSame(2, NaturabuyPhotoCounter::countIn($html, 14945316));
    }

    public function test_a_photo_seen_only_as_a_thumbnail_still_counts(): void
    {
        $html = '<img src="/uploaded/20260428/555/thumbs/400f_00007_x.jpg">';

        $this->assertSame(1, NaturabuyPhotoCounter::countIn($html, 555));
    }

    public function test_an_id_that_only_starts_the_same_is_not_counted(): void
    {
        $html = '<img src="/uploaded/20260428/5551/00001_x.jpg">';

        $this->assertSame(0, NaturabuyPhotoCounter::countIn($html, 555));
    }

    public function test_the_command_stores_the_count_and_when(): void
    {
        $listing = $this->listing(14945316);
        Http::fake(['www.naturabuy.fr/*' => Http::response($this->page(14945316, ['00003', '00004']))]);

        $this->artisan('naturabuy:count-photos')->assertSuccessful();

        $listing->refresh();
        $this->assertSame(2, $listing->photo_count);
        $this->assertTrue($listing->photos_checked_at->equalTo(now()));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://www.naturabuy.fr/Cibles-item-14945316.html');
    }

    /** One page a minute: each run reads a single listing. */
    public function test_one_listing_per_run(): void
    {
        $this->listing(1);
        $this->listing(2);
        Http::fake(['www.naturabuy.fr/*' => Http::response('<html></html>')]);

        $this->artisan('naturabuy:count-photos')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(1, NaturabuyListing::query()->whereNotNull('photos_checked_at')->count());
    }

    /** Never read first, then the one read longest ago; a week passes before a reread. */
    public function test_the_next_listing_due(): void
    {
        $counter = app(NaturabuyPhotoCounter::class);
        $recent = $this->listing(1, ['photos_checked_at' => now()->subDays(3), 'photo_count' => 2]);
        $old = $this->listing(2, ['photos_checked_at' => now()->subDays(9), 'photo_count' => 2]);
        $older = $this->listing(3, ['photos_checked_at' => now()->subDays(12), 'photo_count' => 2]);
        $never = $this->listing(4);
        $this->listing(5, ['closed' => true]);

        $this->assertTrue($counter->nextDue()->is($never));

        $never->forceFill(['photos_checked_at' => now()])->save();
        $this->assertTrue($counter->nextDue()->is($older));

        $older->forceFill(['photos_checked_at' => now()])->save();
        $this->assertTrue($counter->nextDue()->is($old));

        $old->forceFill(['photos_checked_at' => now()])->save();
        $this->assertNull($counter->nextDue(), 'Listings read in the last week and closed ones wait');

        $this->travel(4)->days();
        $this->assertTrue($counter->nextDue()->is($recent));
    }

    /** A page that fails is tried again the next day, not every minute, and keeps its last count. */
    public function test_a_failed_read_is_retried_the_next_day(): void
    {
        $listing = $this->listing(7, ['photo_count' => 3, 'photos_checked_at' => now()->subDays(8)]);
        Http::fake(['www.naturabuy.fr/*' => Http::response('down', 503)]);

        $this->artisan('naturabuy:count-photos')->assertSuccessful();

        $this->assertSame(3, $listing->refresh()->photo_count);
        $this->assertNull(app(NaturabuyPhotoCounter::class)->nextDue());

        $this->travel(1)->days();
        $this->assertTrue(app(NaturabuyPhotoCounter::class)->nextDue()->is($listing));
    }

    public function test_a_listing_can_be_read_on_demand(): void
    {
        $listing = $this->listing(9, ['photo_count' => 1, 'photos_checked_at' => now()]);
        Http::fake(['www.naturabuy.fr/*' => Http::response($this->page(9, ['00001', '00002', '00003']))]);

        $this->artisan('naturabuy:count-photos', ['--listing' => 9])->assertSuccessful();

        $this->assertSame(3, $listing->refresh()->photo_count);
    }

    public function test_it_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'naturabuy:count-photos'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    /** A resync from their API leaves the count alone: the API knows nothing of it. */
    public function test_a_resync_keeps_the_count(): void
    {
        $listing = $this->listing(7, ['photo_count' => 4, 'photos_checked_at' => now()]);
        Http::fake(['*' => Http::response(['itemCount' => 1, 'items' => [[
            'id' => 7, 'title' => 'Renamed', 'price' => 9.99, 'quantity' => 2, 'url' => 'Cibles-item-7.html',
        ]]])]);

        $this->actingAs(User::factory()->admin()->create())->post('/admin/marketplaces/naturabuy/sync');

        $listing->refresh();
        $this->assertSame('Renamed', $listing->title);
        $this->assertSame(4, $listing->photo_count);
    }

    public function test_the_page_shows_their_count_next_to_ours(): void
    {
        $product = Product::factory()->create(['sku' => 'CODE-1', 'image' => 'products/a.webp']);
        $product->images()->create(['image' => 'products/b.webp', 'sort_order' => 1]);
        $product->images()->create(['image' => 'products/c.webp', 'sort_order' => 2]);
        Product::factory()->create(['sku' => 'CODE-2', 'image' => 'products/d.webp']);
        $this->listing(1, ['photo_count' => 1, 'photos_checked_at' => now()]);
        $this->listing(2, ['photo_count' => 4, 'photos_checked_at' => now()]);
        $this->listing(3);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/marketplaces/naturabuy')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>NB photos</th>', $html);
        // Ours 3, theirs 1: photos to add there.
        $this->assertStringContainsString('title="Fewer photos than on our shop (3). Read 24 Sep 2026">1</span>', $html);
        // Ours 1, theirs 4: nothing to flag.
        $this->assertStringContainsString('<span title="Read 24 Sep 2026">4</span>', $html);
        $this->assertStringContainsString('title="Not read yet">—</span>', $html);
    }
}
