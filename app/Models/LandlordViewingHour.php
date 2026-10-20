<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One weekday a landlord takes viewings, and between which hours.
 * See plans/unit-viewing-scheduling.md.
 */
class LandlordViewingHour extends Model
{
    protected $primaryKey = 'viewing_hour_id';

    protected $fillable = ['landlord_id', 'day_of_week', 'start_hour', 'end_hour'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_hour'  => 'integer',
            'end_hour'    => 'integer',
        ];
    }

    /**
     * This landlord's week as [dayOfWeek => [start, end]], days off absent.
     * No rows means hours were never set: every day on the config default,
     * so landlords who never open the setting keep the old behaviour.
     */
    public static function weekFor(int $landlordId): array
    {
        $rows = static::where('landlord_id', $landlordId)->get();

        if ($rows->isEmpty()) {
            return static::defaultWeek();
        }

        return $rows->mapWithKeys(fn (self $r) => [$r->day_of_week => [$r->start_hour, $r->end_hour]])
            ->sortKeys()
            ->all();
    }

    public static function defaultWeek(): array
    {
        $span = [config('rentals.viewing_first_hour'), config('rentals.viewing_last_hour') + 1];

        return array_fill_keys(range(0, 6), $span);
    }

    /**
     * Start hours that can be booked in [start, end): the last viewing starts
     * an hour before the landlord stops.
     */
    public static function startHours(array $span): array
    {
        [$start, $end] = $span;

        return $end > $start ? range($start, $end - 1) : [];
    }

    /**
     * The week as picker slots: [dayOfWeek => [['value' => '09:00', 'label' => '9 AM'], …]].
     */
    public static function slotsFor(int $landlordId): array
    {
        return collect(static::weekFor($landlordId))
            ->map(fn (array $span) => array_map(fn (int $h) => [
                'value' => sprintf('%02d:00', $h),
                'label' => Carbon::createFromTime($h)->format('g A'),
            ], static::startHours($span)))
            ->all();
    }

    /**
     * "Mon–Fri 9 AM–4 PM · Sat 8 AM–12 PM" — consecutive days with the same
     * hours are grouped so the summary fits on one line.
     */
    public static function summaryFor(int $landlordId): string
    {
        $week = static::weekFor($landlordId);
        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $fmt = fn (int $h) => Carbon::createFromTime($h % 24)->format('g A');

        $groups = [];
        foreach ($week as $day => $span) {
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['span'] === $span && $groups[$last]['to'] === $day - 1) {
                $groups[$last]['to'] = $day;
            } else {
                $groups[] = ['from' => $day, 'to' => $day, 'span' => $span];
            }
        }

        if (! $groups) {
            return 'No viewing days set';
        }

        return collect($groups)->map(function ($g) use ($names, $fmt) {
            $days = $g['from'] === $g['to'] ? $names[$g['from']] : $names[$g['from']] . '–' . $names[$g['to']];

            return $days . ' ' . $fmt($g['span'][0]) . '–' . $fmt($g['span'][1]);
        })->implode(' · ');
    }
}
