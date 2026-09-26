<?php

namespace Tests\Feature\Admin;

use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The customer list's Last visit column: the last page seen while signed in. */
class CustomerLastVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26 15:00:00');
    }

    private function visit(User $customer, string $at): void
    {
        $visit = SiteVisit::query()->create(['path' => '/', 'user_id' => $customer->id]);
        $visit->forceFill(['created_at' => $at])->saveQuietly();
    }

    private function list(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/customers')
            ->assertOk()
            ->getContent();
    }

    public function test_the_latest_signed_in_visit_reads_relative_with_the_exact_time_on_hover(): void
    {
        $customer = User::factory()->create();
        $this->visit($customer, '2026-09-10 09:00:00');
        $this->visit($customer, '2026-09-23 14:32:00');

        $html = $this->list();

        $this->assertStringContainsString('<th>Last visit</th>', $html);
        $this->assertStringContainsString('title="23 Sep 2026 · 14:32"', $html);
        $this->assertStringContainsString('3 days ago', $html);
    }

    public function test_a_visit_today_reads_today(): void
    {
        $this->visit(User::factory()->create(), '2026-09-26 08:15:00');

        $this->assertMatchesRegularExpression('/title="26 Sep 2026 · 08:15">\s*Today\s*<\/time>/', $this->list());
    }

    /** Anonymous views belong to no one; another customer's are theirs. */
    public function test_only_this_customers_own_visits_count(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $this->visit($other, '2026-09-25 10:00:00');
        SiteVisit::query()->create(['path' => '/', 'user_id' => null]);

        $html = $this->list();

        $this->assertSame(1, substr_count($html, 'title="25 Sep 2026 · 10:00"'));
        $this->assertStringContainsString('title="No signed-in visit since 29 Aug 2026, when visits started being recorded">—</span>', $html);
        $this->assertStringContainsString($customer->email, $html);
    }

    public function test_the_column_costs_no_query_per_row(): void
    {
        $admin = User::factory()->admin()->create();
        $queriesFor = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get('/admin/customers')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        foreach (range(1, 5) as $i) {
            $this->visit(User::factory()->create(), '2026-09-2'.$i.' 10:00:00');
        }
        $withFive = $queriesFor();

        foreach (range(1, 10) as $i) {
            $this->visit(User::factory()->create(), '2026-09-20 10:00:00');
        }
        $withFifteen = $queriesFor();

        $this->assertSame($withFive, $withFifteen, 'Three times the customers should not mean more queries');
    }
}
