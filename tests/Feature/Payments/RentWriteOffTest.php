<?php

namespace Tests\Feature\Payments;

use App\Models\Notification;
use App\Services\RentLedger;
use App\Services\RentWriteOff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * An ended tenant's unpaid rent keeps counting as owed, then is written off by a daily job after
 * rentals.write_off_after_days (default 90) counted from the move-out date. The landlord can write it off sooner, and
 * "Collect again" undoes it for good (the job never touches that rental again).
 */
class RentWriteOffTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const MOVE_OUT = '2026-08-31';

    private function ended(array $overrides = []): array
    {
        return $this->makeTenancy(array_merge([
            'rental_status' => 'Completed',
            'target_move_out_date' => self::MOVE_OUT,
        ], $overrides));
    }

    private function runJob(string $date): void
    {
        $this->artisan('reservations:write-off-overdue-rent', ['--date' => $date])->assertSuccessful();
    }

    private function dayAfterMoveOut(int $days): string
    {
        return Carbon::parse(self::MOVE_OUT)->addDays($days)->toDateString();
    }

    public function test_it_is_not_written_off_before_the_day_and_is_the_day_after_ninety(): void
    {
        $t = $this->ended();
        $this->assertGreaterThan(0, RentLedger::for($t['reservation'])->summary()['outstanding']);

        $this->runJob($this->dayAfterMoveOut(89));
        $this->assertNull($t['reservation']->fresh()->rent_written_off_at);

        $this->runJob($this->dayAfterMoveOut(90));
        $fresh = $t['reservation']->fresh();
        $this->assertNotNull($fresh->rent_written_off_at);
        $this->assertSame('auto', $fresh->rent_write_off_source);
        $this->assertGreaterThan(0, (float) $fresh->rent_written_off_amount);
        $this->assertSame(0.0, RentLedger::for($fresh)->summary()['outstanding']);
    }

    public function test_the_landlord_is_told_and_it_is_logged(): void
    {
        $t = $this->ended();

        $this->runJob($this->dayAfterMoveOut(90));

        $this->assertTrue(Notification::where('user_id', $t['landlord']->user_id)->where('type', 'rent_written_off')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'rent.write_off_auto']);
    }

    public function test_the_number_of_days_is_a_setting(): void
    {
        config(['rentals.write_off_after_days' => 30]);
        $t = $this->ended();

        $this->runJob($this->dayAfterMoveOut(29));
        $this->assertNull($t['reservation']->fresh()->rent_written_off_at);

        $this->runJob($this->dayAfterMoveOut(30));
        $this->assertNotNull($t['reservation']->fresh()->rent_written_off_at);
    }

    public function test_nothing_is_written_off_when_nothing_is_owed_or_the_rental_is_running(): void
    {
        $running = $this->makeTenancy(['target_move_out_date' => self::MOVE_OUT]);
        $paid = $this->ended();
        $owed = RentLedger::for($paid['reservation'])->summary()['outstanding'];
        $this->actingAs($paid['landlord'])->post(route('landlord.payments.store', $paid['reservation']), [
            'payment_type' => 'Monthly', 'amount' => $owed, 'method' => 'Cash', 'paid_at' => now()->toDateString(),
            'billing_period' => '2026-08',
        ]);
        $paidOwes = RentLedger::for($paid['reservation']->fresh())->summary()['outstanding'];

        $this->runJob($this->dayAfterMoveOut(120));

        $this->assertNull($running['reservation']->fresh()->rent_written_off_at, 'a running rental is never written off');
        if ($paidOwes <= 0) {
            $this->assertNull($paid['reservation']->fresh()->rent_written_off_at, 'nothing owed, nothing to write off');
        }
    }

    public function test_collect_again_stops_the_job_from_writing_it_off_a_second_time(): void
    {
        $t = $this->ended();
        $this->runJob($this->dayAfterMoveOut(90));
        $this->assertNotNull($t['reservation']->fresh()->rent_written_off_at);

        $this->actingAs($t['landlord'])
            ->post(route('landlord.tenancies.resumeCollecting', $t['reservation']))
            ->assertSessionHas('success');

        $fresh = $t['reservation']->fresh();
        $this->assertNull($fresh->rent_written_off_at);
        $this->assertNotNull($fresh->rent_write_off_resumed_at);
        $this->assertGreaterThan(0, RentLedger::for($fresh)->summary()['outstanding']);

        $this->runJob($this->dayAfterMoveOut(200));
        $this->assertNull($t['reservation']->fresh()->rent_written_off_at, 'the landlord chose to keep collecting');
    }

    public function test_write_off_now_marks_it_manual_and_works_before_the_day(): void
    {
        $t = $this->ended();

        $this->actingAs($t['landlord'])
            ->post(route('landlord.tenancies.stopCollecting', $t['reservation']))
            ->assertSessionHas('success');

        $this->assertSame('manual', $t['reservation']->fresh()->rent_write_off_source);
    }

    public function test_the_ledger_page_says_when_it_will_be_written_off_and_then_that_it_was(): void
    {
        $t = $this->ended();
        $due = Carbon::parse(self::MOVE_OUT)->addDays(90);

        // Well before the warning window: plain "still collecting" with the date.
        Carbon::setTestNow($due->copy()->subDays(40));
        $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))
            ->assertOk()->assertSee('Still collecting')->assertSee($due->format('M j, Y'))->assertDontSee('Will be written off on');

        // Inside the warning window.
        Carbon::setTestNow($due->copy()->subDays(3));
        $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))
            ->assertOk()->assertSee('Will be written off on '.$due->format('M j, Y'));

        Carbon::setTestNow();
        $this->runJob($due->toDateString());
        $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))
            ->assertOk()->assertSee('Written off')->assertSee('Collect again');
    }

    public function test_loss_is_what_the_deposit_does_not_cover(): void
    {
        $this->assertSame(0.0, RentWriteOff::loss(2100, 4200));
        $this->assertSame(900.0, RentWriteOff::loss(2100, 1200));
        $this->assertSame(2100.0, RentWriteOff::loss(2100, 0));
    }
}
