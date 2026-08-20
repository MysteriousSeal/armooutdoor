<?php

namespace Tests\Feature;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The customer's own discount codes. Only the ones reserved for them, and
 * only while they can actually be used — the page must never offer a code
 * that checkout would then refuse.
 */
class AccountDiscountsTest extends TestCase
{
    use RefreshDatabase;

    private function code(array $attributes = []): DiscountCode
    {
        return DiscountCode::query()->create([
            'code' => 'CODE'.random_int(1000, 9999),
            'type' => 'percentage',
            'value' => 10,
            ...$attributes,
        ]);
    }

    private function orderUsing(DiscountCode $code, User $user): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
            'status' => 'placed',
            'discount_code_id' => $code->id,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 100,
            'total_cents' => 1400,
            'payment_method' => 'card',
        ]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/account/reductions')->assertRedirect('/login');
    }

    public function test_a_customer_sees_a_code_reserved_for_them(): void
    {
        $user = User::factory()->create();
        $code = $this->code(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee($code->code);
    }

    public function test_another_customers_code_is_never_shown(): void
    {
        $user = User::factory()->create();
        $theirs = $this->code(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertDontSee($theirs->code);
    }

    public function test_public_codes_are_not_listed(): void
    {
        $user = User::factory()->create();
        $public = $this->code(['user_id' => null]);

        // Public codes stay as they are distributed rather than becoming a
        // coupon directory for anyone who logs in.
        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertDontSee($public->code);
    }

    public function test_an_expired_code_is_hidden(): void
    {
        $user = User::factory()->create();
        $expired = $this->code(['user_id' => $user->id, 'ends_at' => now()->subDay()]);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertDontSee($expired->code);
    }

    public function test_a_sold_out_code_is_hidden(): void
    {
        $user = User::factory()->create();
        $soldOut = $this->code(['user_id' => $user->id, 'quantity' => 0]);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertDontSee($soldOut->code);
    }

    public function test_a_code_the_customer_has_used_up_is_hidden(): void
    {
        $user = User::factory()->create();
        $code = $this->code(['user_id' => $user->id, 'max_uses_per_customer' => 1]);
        $this->orderUsing($code, $user);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertDontSee($code->code);
    }

    public function test_the_page_and_the_hub_count_agree(): void
    {
        $user = User::factory()->create();
        $this->code(['user_id' => $user->id]);
        $this->code(['user_id' => $user->id]);
        $this->code(['user_id' => $user->id, 'ends_at' => now()->subDay()]);

        // Two usable, one expired. Both surfaces must say two, or the hub
        // sends the customer looking for a code that is not there.
        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee(trans_choice('store.discount_count', 2, ['count' => 2]));

        $this->assertCount(
            2,
            $this->actingAs($user)->get('/account/reductions')->viewData('codes'),
        );
    }

    public function test_the_empty_state_shows_when_there_is_nothing(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(__('store.discounts_empty'));
    }

    public function test_the_amount_is_shown_in_french_not_the_admin_english(): void
    {
        $user = User::factory()->create();
        $this->code([
            'user_id' => $user->id,
            'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            'value' => null,
        ]);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(__('store.discount_code_free_relay_label'))
            ->assertDontSee('Free relay delivery');
    }

    public function test_a_deadline_is_shown_in_french(): void
    {
        $user = User::factory()->create();
        $endsAt = Carbon::parse('2026-12-24 23:59');
        $this->code(['user_id' => $user->id, 'ends_at' => $endsAt]);

        // Ends with the day, so the date alone says everything.
        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(__('store.discount_code_valid_until', ['date' => $endsAt->translatedFormat('j F Y')]))
            ->assertDontSee(__('store.discount_code_valid_until_time', [
                'date' => $endsAt->translatedFormat('j F Y'),
                'time' => '23:59',
            ]));
    }

    public function test_a_deadline_in_the_middle_of_the_day_shows_the_time(): void
    {
        $user = User::factory()->create();
        $endsAt = Carbon::parse('2026-12-24 09:00');
        $this->code(['user_id' => $user->id, 'ends_at' => $endsAt]);

        // Without the time the customer reads this as valid all day, then
        // meets a refusal at 09:01 with nothing to explain it.
        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(__('store.discount_code_valid_until_time', [
                'date' => $endsAt->translatedFormat('j F Y'),
                'time' => '09:00',
            ]));
    }

    public function test_codes_are_ordered_by_how_soon_they_lapse(): void
    {
        $user = User::factory()->create();

        // Created newest-first in the opposite order to their deadlines, so
        // creation order cannot accidentally produce the right answer.
        $undated = $this->code(['user_id' => $user->id, 'ends_at' => null]);
        $soon = $this->code(['user_id' => $user->id, 'ends_at' => now()->addDay()]);
        $later = $this->code(['user_id' => $user->id, 'ends_at' => now()->addYears(3)]);

        $this->assertSame(
            [$soon->code, $later->code, $undated->code],
            $this->actingAs($user)->get('/account/reductions')->viewData('codes')->pluck('code')->all(),
        );
    }

    public function test_listing_the_codes_does_not_cost_a_query_per_code(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            $this->code(['user_id' => $user->id, 'max_uses_per_customer' => 3, 'quantity' => 5]);
        }

        DB::enableQueryLog();
        $this->actingAs($user)->get('/account/reductions')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Eligibility and the remaining-uses line each want this customer's
        // usage; counting it per code once cost 3 queries apiece.
        $this->assertLessThan(12, $queries);
    }

    public function test_a_code_without_a_deadline_says_so(): void
    {
        $user = User::factory()->create();
        $this->code(['user_id' => $user->id, 'ends_at' => null]);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(__('store.discount_code_no_deadline'));
    }

    public function test_a_code_with_no_usage_limit_says_unlimited(): void
    {
        $user = User::factory()->create();
        $code = $this->code(['user_id' => $user->id, 'max_uses_per_customer' => null, 'quantity' => null]);

        $this->assertNull($code->remainingUsesFor($user));

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(__('store.discount_code_uses_unlimited'));
    }

    public function test_a_limited_code_shows_a_count_rather_than_unlimited(): void
    {
        $user = User::factory()->create();
        $this->code(['user_id' => $user->id, 'max_uses_per_customer' => 2]);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(trans_choice('store.discount_code_uses_left', 2, ['count' => 2]))
            ->assertDontSee(__('store.discount_code_uses_unlimited'));
    }

    public function test_remaining_uses_reflect_what_the_customer_has_left(): void
    {
        $user = User::factory()->create();
        $code = $this->code(['user_id' => $user->id, 'max_uses_per_customer' => 3]);
        $this->orderUsing($code, $user);

        $this->assertSame(2, $code->remainingUsesFor($user));
    }

    public function test_remaining_uses_take_the_tighter_of_the_two_limits(): void
    {
        $user = User::factory()->create();

        // Five uses each, but only one left in the shared pool.
        $code = $this->code(['user_id' => $user->id, 'max_uses_per_customer' => 5, 'quantity' => 1]);

        $this->assertSame(1, $code->remainingUsesFor($user));
    }

    public function test_each_voucher_offers_a_copy_button_carrying_the_code(): void
    {
        $user = User::factory()->create();
        $code = $this->code(['user_id' => $user->id]);

        // The button is the only copy affordance, and it has to carry the code
        // itself — the script reads it from the attribute, not from the page.
        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(__('store.discount_code_copy'))
            ->assertSee('data-copy-code="'.$code->code.'"', false)
            ->assertDontSee('Utiliser ce code');
    }

    public function test_a_code_with_a_deadline_shows_a_countdown(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $this->code(['user_id' => $user->id, 'ends_at' => now()->addDays(3)->addHours(4)->addMinutes(5)->addSeconds(9)]);

        // Rendered server-side so the chip is right before any script runs.
        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee(__('store.discount_code_expires_in', ['time' => '3j 04h 05m 09s']))
            ->assertSee('data-countdown-to=', false);
    }

    public function test_a_code_without_a_deadline_has_no_countdown(): void
    {
        $user = User::factory()->create();
        $this->code(['user_id' => $user->id, 'ends_at' => null]);

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertDontSee('data-countdown-to=', false);
    }

    public function test_the_countdown_is_marked_urgent_close_to_the_deadline(): void
    {
        $user = User::factory()->create();
        $soon = $this->code(['user_id' => $user->id, 'ends_at' => now()->addHours(5)]);
        $later = $this->code(['user_id' => $user->id, 'ends_at' => now()->addDays(30)]);

        $this->assertTrue($soon->isEndingSoon());
        $this->assertFalse($later->isEndingSoon());

        $this->actingAs($user)
            ->get('/account/reductions')
            ->assertOk()
            ->assertSee('is-urgent', false);
    }

    public function test_the_countdown_drops_to_smaller_units_as_it_runs_down(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        $this->assertSame(
            __('store.discount_code_expires_in', ['time' => '02h 30m 00s']),
            $this->code(['user_id' => $user->id, 'ends_at' => now()->addHours(2)->addMinutes(30)])->countdownLabel(),
        );

        // Single digits are padded, and the day and hour parts drop away
        // once there are none left.
        $this->assertSame(
            __('store.discount_code_expires_in', ['time' => '04m 20s']),
            $this->code(['user_id' => $user->id, 'ends_at' => now()->addMinutes(4)->addSeconds(20)])->countdownLabel(),
        );

        $this->assertSame(
            __('store.discount_code_expires_in', ['time' => '00m 08s']),
            $this->code(['user_id' => $user->id, 'ends_at' => now()->addSeconds(8)])->countdownLabel(),
        );
    }

    public function test_a_distant_deadline_stops_counting_seconds(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        // Seconds ticking five years out is motion without information, so
        // they drop away past a week.
        $this->assertSame(
            __('store.discount_code_expires_in', ['time' => '6j 00h 00m 00s']),
            $this->code(['user_id' => $user->id, 'ends_at' => now()->addDays(6)])->countdownLabel(),
        );

        $this->assertSame(
            __('store.discount_code_expires_in', ['time' => '8j 00h 00m']),
            $this->code(['user_id' => $user->id, 'ends_at' => now()->addDays(8)])->countdownLabel(),
        );

        $this->assertNull($this->code(['user_id' => $user->id, 'ends_at' => null])->countdownLabel());
    }

    public function test_the_nav_and_hub_link_to_the_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/account')
            ->assertOk()
            ->assertSee(__('store.account_discounts'))
            ->assertSee(route('account.discounts.index'), false);
    }
}
