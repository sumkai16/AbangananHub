<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Services\RentReminderNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Carbon 3 returns the day gap as a float, so "due tomorrow" used to fall through the strict `=== 1` match
 * and read "1 days overdue". The wording follows the real gap in whole days.
 */
class ReminderWordingTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    /** @return array<string, array{string, string}> */
    public static function gaps(): array
    {
        return [
            'in three days' => ['2026-10-17', 'is due in 3 days'],
            'tomorrow'      => ['2026-10-15', 'is due tomorrow'],
            'today'         => ['2026-10-14', 'is due today'],
            'one day late'  => ['2026-10-13', 'is 1 day overdue'],
            'three days late' => ['2026-10-11', 'is 3 days overdue'],
        ];
    }

    #[DataProvider('gaps')]
    public function test_the_landlord_reminder_reads_the_real_gap(string $dueOn, string $expected): void
    {
        $t = $this->makeTenancy();
        // Mid-day on purpose: the gap to a start-of-day due date is then a fraction, not a whole number.
        $today = Carbon::parse('2026-10-14 10:00:00');

        app(RentReminderNotifier::class)->sendToLandlord(
            $t['reservation'],
            ['due_on' => Carbon::parse($dueOn)->startOfDay(), 'label' => 'Oct 2026', 'balance' => 5000.0],
            $today,
        );

        $message = Notification::where('user_id', $t['landlord']->user_id)->latest('notification_id')->value('message');

        $this->assertStringContainsString($expected, $message);
    }

    public function test_the_tenant_reminder_uses_the_same_wording(): void
    {
        $t = $this->makeTenancy();
        $t['tenant']->update(['account_status' => 'active']);

        $sent = app(RentReminderNotifier::class)->sendToTenant(
            $t['reservation']->fresh(),
            ['due_on' => Carbon::parse('2026-10-15')->startOfDay(), 'label' => 'Oct 2026', 'balance' => 5000.0],
            Carbon::parse('2026-10-14 10:00:00'),
        );

        $this->assertTrue($sent);
        $this->assertStringContainsString(
            'is due tomorrow',
            Notification::where('user_id', $t['tenant']->user_id)->latest('notification_id')->value('message')
        );
    }
}
