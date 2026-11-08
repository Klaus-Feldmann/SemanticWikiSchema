<?php

declare(strict_types=1);

namespace SWSBaseBridge;

/** Presentation only. No SMW syntax or research selection. */
final class TimePresentation
{
    public static function parts(array $date, string $language, string $wikiLanguage, array $options = []): array
    {
        $en = str_starts_with($language, 'en');
        $y = $date['year'];
        $suffix = $en ? ($options['dateBceEn'] ?? 'BC') : ($options['dateBceDe'] ?? 'v. Chr.');
        $yearText = (string)abs($y).($y < 0 ? ' '.$suffix : '');
        $yearTitle = $y < 0 ? abs($y).(str_starts_with($wikiLanguage, 'en') ? ' BC' : ' v. Chr.') : (string)$y;
        $months = $en ? ['January','February','March','April','May','June','July','August','September','October','November','December'] : ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        $prefix = '';
        if (isset($date['day'])) {
            $prefix = $date['day'].($en ? ' ' : '. ');
        }
        if (isset($date['month'])) {
            $prefix .= $months[$date['month'] - 1].' ';
        }
        return [...($prefix !== '' ? [['text' => $prefix]] : []),['text' => $yearText,'yearTitle' => $yearTitle]];
    }
}
