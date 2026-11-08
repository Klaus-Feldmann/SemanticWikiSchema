<?php

declare(strict_types=1);

namespace SWSBaseBridge;

/** Decode Wikibase JSON time values with the core's calendar arithmetic. */
final class HistoricalTime extends \ProfessionalWiki\SWS\Domain\HistoricalCalendar
{
    public static function read(array $raw): ?array
    {
        $calendar = match($raw['calendarmodel'] ?? '') {
            'http://www.wikidata.org/entity/Q1985727','https://www.wikidata.org/entity/Q1985727' => 'gregorian',
            'http://www.wikidata.org/entity/Q1985786','https://www.wikidata.org/entity/Q1985786' => 'julian',default => null
        };
        if ($calendar === null || !preg_match('/\A([+-])(\d{4,16})-(\d{2})-(\d{2})T00:00:00Z\z/', $raw['time'] ?? '', $m)) {
            return null;
        }
        $y = (int)$m[2] * ($m[1] === '-' ? -1 : 1);
        $p = $raw['precision'] ?? null;
        if (!in_array($p, [9,10,11], true) || $y === 0 || abs($y) > 9999) {
            return null;
        }
        $month = $p >= 10 ? (int)$m[3] : 1;
        $day = $p === 11 ? (int)$m[4] : 1;
        if (!self::valid($y, $month, $day, $calendar)) {
            return null;
        }
        if (($raw['timezone'] ?? 0) !== 0 || ($raw['before'] ?? 0) !== 0 || ($raw['after'] ?? 0) !== 0) {
            return null;
        }
        $first = self::dayNumber($y, $month, $day, $calendar);
        $lastMonth = $p === 9 ? 12 : $month;
        $lastDay = $p < 11 ? [31,self::leap($y, $calendar) ? 29 : 28,31,30,31,30,31,31,30,31,30,31][$lastMonth - 1] : $day;
        $last = self::dayNumber($y, $lastMonth, $lastDay, $calendar);
        // Only fully specified days can be unambiguously converted to a different calendar.
        $displayCalendar = $p === 11 ? ($first >= 2299161 ? 'gregorian' : 'julian') : $calendar;
        $display = $p === 11 ? self::fromDayNumber($first, $displayCalendar) : ['year' => $y];
        if ($p === 10) {
            $display['month'] = $month;
        }
        if (abs($display['year']) > 9999) {
            return null;
        }
        return ['sourceTime' => $raw,'calendar' => $calendar,'precision' => $p,'year' => $y,
         ...($p >= 10 ? ['month' => $month] : []),...($p === 11 ? ['day' => $day] : []),
         'displayDate' => $display,'displayCalendar' => $displayCalendar,
         'comparison' => ['start' => $first,'end' => $last]];
    }
}
