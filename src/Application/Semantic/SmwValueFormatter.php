<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\Semantic;
use InvalidArgumentException;

final class SmwValueFormatter {
    private const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    public function format(array $field, array $value): string {
        $type = $field['type']; $raw = $value['metadata']['raw'] ?? $value['value'];
        if ($type === 'historicalDate') {
            $time = $value['metadata']['sourceTime'] ?? [];
            if (!preg_match('/^([+-]\d+)-(\d\d)-(\d\d)T/', $time['time'] ?? '', $m)) { throw new InvalidArgumentException('Missing time metadata'); }
            $year = (int)$m[1]; $precision = $time['precision'] ?? null;
            if (!$year || !in_array($precision,[9,10,11],true)) { throw new InvalidArgumentException('Unsupported precision'); }
            $calendar = match ($time['calendarmodel'] ?? '') {
                'http://www.wikidata.org/entity/Q1985786','https://www.wikidata.org/entity/Q1985786'=>'Jl',
                'http://www.wikidata.org/entity/Q1985727','https://www.wikidata.org/entity/Q1985727'=>'Gr',
                default=>throw new InvalidArgumentException('Unknown calendar') };
            $text = $precision === 11 ? (int)$m[3].' ' : '';
            if ($precision >= 10) { $text .= (self::MONTHS[(int)$m[2]-1] ?? throw new InvalidArgumentException('Invalid month')).' '; }
            return $text.abs($year).($year < 0 ? ' BC' : '').' '.$calendar;
        }
        if ($type === 'number') { if ((!is_int($raw) && !is_float($raw)) || !is_finite((float)$raw)) { throw new InvalidArgumentException('Invalid number'); } return (string)$raw; }
        if ($type === 'boolean') { if (!is_bool($raw)) { throw new InvalidArgumentException('Invalid boolean'); } return $raw ? '1' : '0'; }
        if ($type === 'date') { return $this->date($raw); }
        if ($type === 'dateTime') { return $this->dateTime($raw); }
        if ($type === 'url' && (!is_string($raw) || !preg_match('/^[a-z][a-z0-9+.-]*:/i',$raw))) { throw new InvalidArgumentException('Absolute URL required'); }
        return $value['value'];
    }
    private function date(string $raw): string {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$raw,$m) || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])) { throw new InvalidArgumentException('Invalid Gregorian date'); }
        return (int)$m[3].' '.self::MONTHS[(int)$m[2]-1].' '.(int)$m[1].' Gr';
    }
    private function dateTime(string $raw): string {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,9}))?(Z|[+-]\d{2}:\d{2})$/D',$raw,$m)) { throw new InvalidArgumentException('Explicit timezone required'); }
        $this->date($m[1]);
        if ((int)$m[2]>23 || (int)$m[3]>59 || (int)$m[4]>59) { throw new InvalidArgumentException('Invalid clock'); }
        $zone = $m[6];
        if ($zone !== 'Z' && ((int)substr($zone,1,2)>23 || (int)substr($zone,4,2)>59)) { throw new InvalidArgumentException('Invalid offset'); }
        $dt = new \DateTimeImmutable($m[1].'T'.$m[2].':'.$m[3].':'.$m[4].$zone);
        $dt = $dt->setTimezone(new \DateTimeZone('UTC'));
        if ((int)$dt->format('Y')<1 || (int)$dt->format('Y')>9999) { throw new InvalidArgumentException('UTC outside range'); }
        return (int)$dt->format('j').' '.self::MONTHS[(int)$dt->format('n')-1].' '.(int)$dt->format('Y').' '.$dt->format('H:i:s').'+00:00'.(($m[5] ?? '') !== '' ? ' .'.$m[5] : '').' Gr';
    }
}
