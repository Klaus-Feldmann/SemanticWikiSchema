<?php

declare(strict_types=1);

namespace ProfessionalWiki\SWS\Domain;

/** Bounded historical calendar arithmetic; no Unix timestamp and no year zero. */
class HistoricalCalendar
{
    public static function leap(int $year, string $calendar): bool
    {
        $y = $year < 0 ? $year + 1 : $year;
        return $y % 4 === 0 && ($calendar === 'julian' || $y % 100 !== 0 || $y % 400 === 0);
    }
    public static function valid(int $y, int $m, int $d, string $calendar): bool
    {
        if (!in_array($calendar, ['julian','gregorian'], true) || $y === 0 || abs($y) > 9999 || $m < 1 || $m > 12 || $d < 1) {
            return false;
        }
        return $d <= [31,self::leap($y, $calendar) ? 29 : 28,31,30,31,30,31,31,30,31,30,31][$m - 1];
    }
    public static function calendar(int $y, int $m = 1, int $d = 1): string
    {
        return [$y,$m,$d] >= [1582,10,15] ? 'gregorian' : 'julian';
    }
    public static function dayNumber(int $year, int $month, int $day, string $calendar): int
    {
        if (!self::valid($year, $month, $day, $calendar)) {
            throw new \InvalidArgumentException('Invalid historical date');
        }
        $y = $year < 0 ? $year + 1 : $year;
        $a = (int)floor((14 - $month) / 12);
        $y += 4800 - $a;
        $m = $month + 12 * $a - 3;
        $j = $day + (int)floor((153 * $m + 2) / 5) + 365 * $y + (int)floor($y / 4);
        return $calendar === 'julian' ? $j - 32083 : $j - (int)floor($y / 100) + (int)floor($y / 400) - 32045;
    }
    public static function fromDayNumber(int $j, string $calendar): array
    {
        if ($calendar === 'gregorian') {
            $a = $j + 32044;
            $b = (int)floor((4 * $a + 3) / 146097);
            $c = $a - (int)floor(146097 * $b / 4);
        } else {
            $b = 0;
            $c = $j + 32082;
        }
        $d = (int)floor((4 * $c + 3) / 1461);
        $e = $c - (int)floor(1461 * $d / 4);
        $m = (int)floor((5 * $e + 2) / 153);
        $day = $e - (int)floor((153 * $m + 2) / 5) + 1;
        $month = $m + 3 - 12 * (int)floor($m / 10);
        $y = ($calendar === 'gregorian' ? 100 * $b : 0) + $d - 4800 + (int)floor($m / 10);
        return ['year' => $y <= 0 ? $y - 1 : $y,'month' => $month,'day' => $day];
    }
}
