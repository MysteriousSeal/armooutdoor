<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The two accounting pages, still empty. */
class AccountingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_reaches_both_pages(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales')
            ->assertOk()
            ->assertSee('Sales')
            ->assertSee('Accounting')
            ->assertSee('January');

        $this->actingAs($owner)->get('/admin/accounting/purchases')
            ->assertOk()
            ->assertSee('Purchases')
            ->assertSee('January');
    }

    public function test_a_staff_admin_is_turned_away(): void
    {
        $staff = User::factory()->staffAdmin()->create();

        // These pages will carry revenue and costs: the same door as the
        // rest of what touches money.
        $this->actingAs($staff)->get('/admin/accounting/sales')->assertForbidden();
        $this->actingAs($staff)->get('/admin/accounting/purchases')->assertForbidden();
    }

    public function test_the_menu_shows_only_for_the_owner(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Accounting')
            ->assertSee('/admin/accounting/sales', false)
            ->assertSee('/admin/accounting/purchases', false);

        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Accounting')
            ->assertDontSee('/admin/accounting/sales', false);
    }

    public function test_the_open_page_marks_its_group_and_its_item(): void
    {
        $content = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#admin-nav-trigger active"[^>]*>\s*Accounting#s', $content);
        $this->assertMatchesRegularExpression('#/admin/accounting/purchases"[^>]*admin-nav-menu-item active#s', $content);
    }

    public function test_accounting_opens_the_right_hand_block_just_left_of_system(): void
    {
        $content = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        // Accounting pushes the right-hand block, and System follows it.
        $this->assertMatchesRegularExpression(
            '#admin-nav-group admin-nav-group--end".*?Accounting.*?admin-nav-group ".*?System#s',
            $content
        );
        $this->assertSame(1, substr_count($content, 'admin-nav-group--end'));
    }

    public function test_system_pushes_alone_when_accounting_is_hidden(): void
    {
        $content = $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        // Without Accounting, the bar must keep System against the right.
        $this->assertSame(1, substr_count($content, 'admin-nav-group--end'));
        $this->assertMatchesRegularExpression('#admin-nav-group--end".*?System#s', $content);
    }

    public function test_each_section_renders_its_own_page(): void
    {
        $owner = User::factory()->admin()->create();

        // The two sections diverge from the first line, so each owns its page
        // whole rather than branching inside a shared one.
        $sales = $this->actingAs($owner)->get('/admin/accounting/sales/2026-07')->assertOk();
        $sales->assertSee('Channel')->assertDontSee('Excl. VAT');

        $purchases = $this->actingAs($owner)->get('/admin/accounting/purchases/2026-07')->assertOk();
        $purchases->assertSee('Supplier')->assertDontSee('Channel');
    }

    public function test_both_sections_share_one_heading(): void
    {
        $owner = User::factory()->admin()->create();

        foreach (['sales', 'purchases'] as $section) {
            $this->actingAs($owner)
                ->get('/admin/accounting/'.$section.'/2026-07')
                ->assertOk()
                ->assertSee('July 2026')
                ->assertSee('1 July to 31 July 2026');
        }
    }

    public function test_both_sections_say_where_the_month_stands(): void
    {
        $owner = User::factory()->admin()->create();
        $this->travelTo('2026-08-26 10:00:00');

        foreach (['sales', 'purchases'] as $section) {
            // A closed month can be ruled off and printed; one still running
            // cannot, and that holds on both sides of the accounts.
            $this->actingAs($owner)
                ->get('/admin/accounting/'.$section.'/2026-07')
                ->assertOk()
                ->assertSee('Closed')
                ->assertDontSee('In progress');

            $this->actingAs($owner)
                ->get('/admin/accounting/'.$section.'/2026-08')
                ->assertOk()
                ->assertSee('In progress');
        }
    }

    public function test_the_chip_sits_on_the_title_line(): void
    {
        $this->travelTo('2026-08-26 10:00:00');

        $content = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-07')
            ->assertOk()
            ->getContent();

        // Beside the month's name, not under the dates: the chip qualifies the
        // month, so it belongs on its line.
        $this->assertMatchesRegularExpression(
            '#accounting-month-heading.*?July 2026.*?admin-list-chip.*?</div>.*?admin-list-lede#s',
            $content
        );
    }

    public function test_both_journals_are_printed_in_the_same_frame(): void
    {
        $this->travelTo('2026-08-26 10:00:00');

        // One layout carries the letterhead, the meta boxes and the signature
        // block, so an edit to any of them is made once.
        foreach (['sales-pdf', 'purchases-pdf'] as $journal) {
            $source = file_get_contents(resource_path('views/admin/accounting/'.$journal.'.blade.php'));

            $this->assertStringContainsString("@extends('admin.accounting.journal-pdf')", $source);
            $this->assertStringNotContainsString('<!DOCTYPE html>', $source);
            $this->assertStringNotContainsString('class="signature"', $source);
        }
    }

    public function test_the_row_buttons_are_written_once(): void
    {
        // The pencil and the bin are the same on both sides; only what fills
        // the form differs.
        foreach (['sales', 'purchases'] as $section) {
            $source = file_get_contents(resource_path('views/admin/accounting/partials/'.$section.'.blade.php'));

            $this->assertStringContainsString("@include('admin.accounting.partials.row-actions'", $source);
            $this->assertStringNotContainsString('data-entry-delete', $source);
        }
    }
}
