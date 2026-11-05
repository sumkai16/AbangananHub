<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Payments > Calendar zooms like a map: all years, one year, one month. The controller sends one payload for every
 * year (public/js/payments-calendar.js draws it), so these tests read that payload and the page shell around it.
 */
class PaymentsCalendarZoomTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function page(array $t, array $query = []): string
    {
        return $this->actingAs($t['landlord'])
            ->get(route('landlord.payments.index', ['view' => 'calendar'] + $query))
            ->assertOk()
            ->getContent();
    }

    /** The JSON the script reads, decoded the way the browser decodes it. */
    private function payload(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="payments-calendar-data">(.*?)</script>#s', $html, $m), 'The calendar payload is on the page.');

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_default_is_the_current_month_with_every_year_in_the_payload(): void
    {
        $t = $this->makeTenancy();
        $data = $this->payload($this->page($t));

        $this->assertSame('month', $data['level']);
        $this->assertSame('2026-10', $data['month']);
        $this->assertSame(2026, $data['year']);
        $this->assertSame('2026-10-14', $data['today']);
        // From the year rent first fell due to two years ahead.
        $this->assertSame([2026, 2027, 2028], $data['years']);
        // The all-years window opens on the first year with data, and the server says how far the arrows may go.
        $this->assertSame(2026, $data['start']);
        $this->assertSame([1990, 2100], [$data['minYear'], $data['maxYear']]);
    }

    public function test_the_range_starts_in_the_first_year_any_rent_fell_due(): void
    {
        $t = $this->makeTenancy(['target_move_in_date' => '2025-11-01', 'reservation_date' => '2025-11-01']);

        $this->assertSame([2025, 2026, 2027, 2028], $this->payload($this->page($t))['years']);
    }

    public function test_the_address_picks_the_level(): void
    {
        $t = $this->makeTenancy();

        $years = $this->payload($this->page($t, ['zoom' => 'years']));
        $this->assertSame('years', $years['level']);

        $year = $this->payload($this->page($t, ['zoom' => 'year', 'year' => '2027']));
        $this->assertSame('year', $year['level']);
        $this->assertSame(2027, $year['year']);

        // Old links keep working: ?month= alone is the day grid for that month.
        $month = $this->payload($this->page($t, ['month' => '2027-03']));
        $this->assertSame('month', $month['level']);
        $this->assertSame('2027-03', $month['month']);
        $this->assertSame(2027, $month['year']);
    }

    public function test_any_year_opens_with_no_data_and_no_error(): void
    {
        $t = $this->makeTenancy();

        // A year long after anything is projected.
        $year = $this->payload($this->page($t, ['zoom' => 'year', 'year' => '2031']));
        $this->assertSame('year', $year['level']);
        $this->assertSame(2031, $year['year']);
        $this->assertSame([], array_filter(array_keys($year['months']), fn ($ym) => str_starts_with($ym, '2031')), 'Nothing is invented for 2031.');

        // The all-years window can start anywhere.
        $years = $this->payload($this->page($t, ['zoom' => 'years', 'year' => '2031']));
        $this->assertSame('years', $years['level']);
        $this->assertSame(2031, $years['start']);

        // A month with no data: the day grid is drawn by the browser, the server only has to say which month.
        $month = $this->payload($this->page($t, ['month' => '2033-04']));
        $this->assertSame('month', $month['level']);
        $this->assertSame('2033-04', $month['month']);
        $this->assertSame(2033, $month['year']);
        $this->assertArrayNotHasKey('2033-04', $month['months']);

        // The payload itself still holds only years that have data.
        $this->assertSame([2026, 2027, 2028], $month['years']);
    }

    public function test_a_year_outside_the_span_falls_back(): void
    {
        $t = $this->makeTenancy();

        foreach (['1989', '2101', '0001', '9999', 'abc', ''] as $bad) {
            $data = $this->payload($this->page($t, ['zoom' => 'year', 'year' => $bad]));
            $this->assertSame(2026, $data['year'], "year={$bad}");
        }
        foreach (['1989-12', '2101-01', '2150-01', '2026-13'] as $bad) {
            $this->assertSame('2026-10', $this->payload($this->page($t, ['month' => $bad]))['month'], "month={$bad}");
        }

        // The edges of the span are fine.
        $this->assertSame(1990, $this->payload($this->page($t, ['zoom' => 'year', 'year' => '1990']))['year']);
        $this->assertSame('2100-12', $this->payload($this->page($t, ['month' => '2100-12']))['month']);
    }

    public function test_a_lease_that_ends_later_extends_the_years_but_never_past_five_years_out(): void
    {
        $t = $this->makeTenancy(['target_move_out_date' => '2030-06-15']);
        $data = $this->payload($this->page($t));

        $this->assertSame([2026, 2027, 2028, 2029, 2030], $data['years']);
        $this->assertArrayHasKey('2030-06', $data['months']);
        $this->assertArrayNotHasKey('2030-07', $data['months']);

        // A far-off move-out date cannot bloat the page: 60 months from this one, then nothing.
        $far = $this->makeTenancy(['target_move_out_date' => '2040-01-15'], $t['landlord']);
        $months = $this->payload($this->page($far))['months'];
        $this->assertArrayHasKey('2031-10', $months);
        $this->assertArrayNotHasKey('2031-11', $months);
    }

    public function test_nonsense_in_the_address_falls_back_to_this_month(): void
    {
        $t = $this->makeTenancy();

        $data = $this->payload($this->page($t, ['zoom' => 'banana', 'year' => 'abc', 'month' => '9999-99']));
        $this->assertSame('month', $data['level']);
        $this->assertSame('2026-10', $data['month']);

        // A year or month far outside any lease is refused too, so the range can never balloon.
        $far = $this->payload($this->page($t, ['zoom' => 'year', 'year' => '0001', 'month' => '0001-01']));
        $this->assertSame(2026, $far['year']);
        $this->assertSame('2026-10', $far['month']);
        $this->assertSame([2026, 2027, 2028], $far['years']);
    }

    public function test_months_carry_the_totals_the_year_views_draw(): void
    {
        $t = $this->makeTenancy();
        $data = $this->payload($this->page($t));

        // Aug, Sep and Oct are all past their due day (5th) and unpaid.
        foreach (['2026-08', '2026-09', '2026-10'] as $ym) {
            $this->assertSame(1, $data['months'][$ym]['due']);
            $this->assertSame(1, $data['months'][$ym]['overdue']);
            $this->assertEquals(5000, $data['months'][$ym]['expected']);
            $this->assertEquals(0, $data['months'][$ym]['collected']);
        }

        $this->makePayment($t['reservation'], ['payment_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 5000, 'status' => 'Paid', 'paid_at' => '2026-08-06']);
        $august = $this->payload($this->page($t))['months']['2026-08'];

        $this->assertSame(1, $august['paid']);
        $this->assertSame(0, $august['overdue']);
        $this->assertEquals(5000, $august['collected']);
    }

    public function test_each_day_lists_compact_entries_and_one_table_names_the_tenancies(): void
    {
        $t = $this->makeTenancy();
        $t['tenant']->update(['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $data = $this->payload($this->page($t));

        $entry = $data['days']['2026-10-05'][0];
        $this->assertSame([$t['reservation']->reservation_id, 'overdue', 5000, 0], $entry);

        $person = $data['people'][$t['reservation']->reservation_id];
        $this->assertSame('Ana Reyes', $person['name']);
        $this->assertSame(route('landlord.payments.ledger', $t['reservation']), $person['url']);
    }

    public function test_a_day_is_ordered_most_urgent_first(): void
    {
        $a = $this->makeTenancy();
        $b = $this->makeTenancy([], $a['landlord']);
        // Paid in full, so Oct 5 holds a paid entry and an overdue one.
        $this->makePayment($a['reservation'], ['payment_type' => 'Monthly', 'billing_period' => '2026-10-01', 'amount' => 5000, 'status' => 'Paid', 'paid_at' => '2026-10-05']);

        $day = $this->payload($this->page($a))['days']['2026-10-05'];

        $this->assertSame(['overdue', 'paid'], array_column($day, 1));
    }

    public function test_later_months_are_projected_until_move_out_or_a_year_ahead(): void
    {
        $open = $this->makeTenancy();
        $ending = $this->makeTenancy(['target_move_out_date' => '2027-01-15'], $open['landlord']);

        $months = $this->payload($this->page($open))['months'];

        // Open-ended: a year of upcoming rent after this month, then nothing invented.
        $this->assertSame(2, $months['2026-11']['upcoming']);
        $this->assertSame(1, $months['2027-10']['upcoming'], 'Only the open-ended tenancy runs this far.');
        $this->assertArrayNotHasKey('2027-11', $months);
        // The one that moves out in January stops there.
        $this->assertSame(2, $months['2027-01']['upcoming']);
        $this->assertSame(1, $months['2027-02']['upcoming']);
    }

    public function test_another_landlords_tenancies_are_not_in_the_payload(): void
    {
        $mine = $this->makeTenancy();
        $other = $this->makeTenancy();
        $other['tenant']->update(['first_name' => 'Stranger']);

        $html = $this->page($mine);

        $this->assertStringNotContainsString('Stranger', $html);
        $this->assertCount(1, $this->payload($html)['people']);
    }

    public function test_the_property_filter_narrows_the_calendar(): void
    {
        $a = $this->makeTenancy();
        $b = $this->makeTenancy([], $a['landlord']);

        $data = $this->payload($this->page($a, ['property' => $b['reservation']->property_id]));

        $this->assertSame([$b['reservation']->reservation_id], array_map('intval', array_keys($data['people'])));
    }

    public function test_tenant_names_cannot_break_out_of_the_payload(): void
    {
        $t = $this->makeTenancy();
        $t['tenant']->update(['first_name' => '</script><img src=x onerror=alert(1)>', 'last_name' => 'Smith']);

        $html = $this->page($t);

        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertSame('</script><img src=x onerror=alert(1)> Smith', $this->payload($html)['people'][$t['reservation']->reservation_id]['name']);
    }

    public function test_the_page_has_the_zoom_navigation_and_no_dropdown_pickers(): void
    {
        $t = $this->makeTenancy();
        $html = $this->page($t);

        $this->assertStringContainsString('js/payments-calendar.js', $html);
        $this->assertStringContainsString('aria-label="Calendar zoom"', $html);
        $this->assertStringContainsString('All years', $html);
        $this->assertStringContainsString('>Today</button>', $html);
        $this->assertStringContainsString('See more · +', $html);
        $this->assertStringNotContainsString('data-cal-pick', $html);
        // Fragment swapping is gone: the calendar never asks the server for a partial any more.
        $this->actingAs($t['landlord'])
            ->get(route('landlord.payments.index', ['view' => 'calendar']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('<!DOCTYPE html>', false);
    }

    public function test_the_filter_form_carries_the_level_so_a_search_keeps_it(): void
    {
        $t = $this->makeTenancy();

        $year = $this->page($t, ['zoom' => 'year', 'year' => '2027']);
        $this->assertMatchesRegularExpression('/name="zoom" data-cal-field="zoom" value="year"\s*>/', $year);
        $this->assertMatchesRegularExpression('/name="year" data-cal-field="year" value="2027"\s*>/', $year);
        $this->assertMatchesRegularExpression('/name="month" data-cal-field="month" value="" disabled/', $year);

        $years = $this->page($t, ['zoom' => 'years', 'year' => '2031']);
        $this->assertMatchesRegularExpression('/name="zoom" data-cal-field="zoom" value="years"\s*>/', $years);
        $this->assertMatchesRegularExpression('/name="year" data-cal-field="year" value="2031"\s*>/', $years);

        $month = $this->page($t, ['month' => '2027-03']);
        $this->assertMatchesRegularExpression('/name="month" data-cal-field="month" value="2027-03"\s*>/', $month);
        $this->assertMatchesRegularExpression('/name="zoom" data-cal-field="zoom" value="" disabled/', $month);
    }

    public function test_the_list_and_calendar_toggle_leaves_the_zoom_level_behind(): void
    {
        $t = $this->makeTenancy();
        $html = $this->page($t, ['zoom' => 'year', 'year' => '2027']);

        $this->assertMatchesRegularExpression('#href="[^"]*view=list"#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*view=list[^"]*(zoom|year)=#', $html);
    }

    public function test_the_summary_strip_stays_out_of_the_calendar(): void
    {
        $t = $this->makeTenancy();
        $html = $this->page($t, ['zoom' => 'years']);

        $this->assertStringNotContainsString('Collected this month', $html);
        $this->assertStringNotContainsString('Still owed', $html);
    }

    public function test_the_calendar_costs_about_the_same_queries_as_the_list(): void
    {
        $landlord = $this->makeLandlord();
        foreach (range(1, 4) as $i) {
            $this->makeTenancy([], $landlord);
        }
        $t = ['landlord' => $landlord];

        DB::enableQueryLog();
        $this->actingAs($landlord)->get(route('landlord.payments.index'))->assertOk();
        $list = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->actingAs($landlord)->get(route('landlord.payments.index', ['view' => 'calendar', 'zoom' => 'years']))->assertOk();
        $calendar = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The ledger runs once per tenancy, not once per month: no extra query for the extra years.
        $this->assertLessThanOrEqual($list + 2, $calendar);
    }
}
