<?php

namespace Tests\Feature;

use App\Support\BusinessTime;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** app.timezone stays UTC; "today" and the 08:00 reminders mean Manila time. */
class BusinessTimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_is_manilas_date_while_utc_is_still_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 20:00:00', 'UTC')); // 04:00 on the 19th in Manila

        $this->assertSame('2026-10-18', now()->toDateString());
        $this->assertSame('2026-10-19', BusinessTime::today());
        $this->assertSame('Asia/Manila', BusinessTime::now()->timezone->getName());
    }

    public function test_every_scheduled_command_carries_the_manila_timezone(): void
    {
        app(ConsoleKernel::class)->bootstrap(); // loads routes/console.php, which registers the schedule
        $events = app(Schedule::class)->events();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertSame('Asia/Manila', (string) $event->timezone, $event->command);
        }
    }
}
