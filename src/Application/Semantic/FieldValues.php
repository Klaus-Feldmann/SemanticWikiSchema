<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\Semantic;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\HistoricalDate;
use ProfessionalWiki\SWS\Domain\HistoricalCalendar;

/** Pure, schema-driven read model. Caller supplies an authorized snapshot and lazy remote reader.
 * No global cache, no network, no store writes. One instance belongs to one snapshot.
 */
final class FieldValues {
    private ?array $external = null;
    public function __construct(private array $schema, private array $subject, private \Closure $readExternal) {}

    public function collect(?string $name = null, string $origin = 'all', string $language = 'de', bool $includeImages = false): array {
        return $this->collectInternal($name,$origin,$language,$includeImages,false);
    }

    /** Scalar SMW projection only. Relation identity stays in the snapshot, never coerced to text. */
    public function collectForAnnotations(string $language = 'de'): array {
        return $this->collectInternal(null,'all',$language,false,true);
    }

    private function collectInternal(?string $name, string $origin, string $language, bool $includeImages, bool $forAnnotations): array {
        if (!in_array($origin, ['all','own','external'], true)) { throw new InvalidArgumentException('Unknown origin'); }
        $fields = []; $found = false;
        foreach ($this->schema['properties'] as $definition) {
            if ($name !== null && $definition['name'] !== $name) { continue; }
            $type = $definition['type'];
            if ($type === 'wikitext' || $type === 'image' && !$includeImages) { continue; }
            $found = true;
            $stored = $this->subject['statements'][$definition['name']]['values'] ?? [];
            if ($forAnnotations && $type === 'relation') {
                // Do not resolve targets or publish relation annotations in this processing stage.
                $fields[] = ['name'=>$definition['name'],'type'=>$type,'values'=>[],
                    'hasStoredValues'=>$stored !== [],'status'=>'deferred-relation',
                    'externalProperty'=>$definition['externalProperty'] ?? null,
                    'targetSchemas'=>$definition['targetSchemas'] ?? [],
                    'rawStatement'=>$this->subject['statements'][$definition['name']] ?? null,'reason'=>null];
                continue;
            }
            $own = $origin === 'external' ? [] : array_values(array_filter($stored,
                static fn($v) => $v !== null && (!is_string($v) || trim($v) !== '')));
            $values = []; $seen = [];
            $add = static function($value, string $source, array $metadata) use (&$values, &$seen): void {
                if (!is_scalar($value) || is_float($value) && !is_finite($value)) { throw new InvalidArgumentException('Unsupported scalar'); }
                $text = is_bool($value) ? ($value ? 'ja' : 'nein') : (string)$value;
                $key = json_encode([$text, $metadata['urlEntry'] ?? null], JSON_THROW_ON_ERROR);
                if ($text !== '' && !isset($seen[$key])) { $seen[$key] = true; $values[] = ['value'=>$text,'origin'=>$source,'metadata'=>$metadata]; }
            };
            foreach ($own as $value) {
                if ($type === 'historicalDate') {
                    $date = is_string($value) ? HistoricalDate::parse($value) : null;
                    if (!$date) { throw new InvalidArgumentException('Invalid historical date'); }
                    $calendar = HistoricalCalendar::calendar($date->year, $date->month ?? 1, $date->day ?? 1);
                    $metadata = ['kind'=>'time','own'=>true,'raw'=>$value,'year'=>$date->year,
                        'sourceTime'=>['time'=>sprintf('%s%04d-%02d-%02dT00:00:00Z', $date->year < 0 ? '-' : '+', abs($date->year), $date->month ?? 0, $date->day ?? 0),
                        'precision'=>$date->day !== null ? 11 : ($date->month !== null ? 10 : 9),
                        'calendarmodel'=>'http://www.wikidata.org/entity/'.($calendar === 'julian' ? 'Q1985786' : 'Q1985727')]];
                    $months = str_starts_with($language,'en') ? ['January','February','March','April','May','June','July','August','September','October','November','December'] : ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                    $en = str_starts_with($language,'en');
                    $label = ($date->day !== null ? $date->day.($en ? ' ' : '. ') : '').($date->month !== null ? $months[$date->month-1].' ' : '').abs($date->year);
                    if ($date->year < 0) { $label .= ' '.($definition['wikiLink'][$en ? 'dateBceEn' : 'dateBceDe'] ?? ($en ? 'BC' : 'v. Chr.')); }
                    if ($date->month !== null) { $metadata['month'] = $date->month; }
                    if ($date->day !== null) { $metadata['day'] = $date->day; }
                    $add($label,'own',$metadata);
                } elseif ($type === 'url' && is_array($value)) {
                    if (!is_string($value['url'] ?? null)) { throw new InvalidArgumentException('Invalid URL entry'); }
                    foreach (['label','caption','captionPage'] as $key) { if (isset($value[$key]) && !is_string($value[$key])) { throw new InvalidArgumentException('Invalid URL metadata'); } }
                    $add($value['url'],'own',['raw'=>$value['url'],'urlEntry'=>$value]);
                } elseif ($type === 'select') {
                    $label = null;
                    foreach ($definition['options'] ?? [] as $option) { if ($value === $option['label']) { $label = $value; break; } }
                    if ($label === null) { foreach ($definition['options'] ?? [] as $option) { if ($value === $option['id']) { $label = $option['label']; break; } } }
                    if (!is_string($label) || $label === '') { throw new InvalidArgumentException('Unresolved selection'); }
                    $add($label,'own',['raw'=>$value]);
                } else { $add($value,'own',['raw'=>$value]); }
            }
            $remote = null;
            $status = $own !== [] ? 'own' : 'missing';
            $binding = $definition['externalProperty'] ?? null;
            if ($own === [] && $binding !== null && $origin !== 'own') {
                if ($this->external === null) {
                    $response = ($this->readExternal)();
                    if (($response['version'] ?? null) !== 1 || ($response['scope'] ?? null) !== 'current' || ($response['mainSubjectId'] ?? null) !== $this->subject['id'] || !is_array($response['fields'] ?? null)) { throw new InvalidArgumentException('Invalid external snapshot'); }
                    $remoteFields = [];
                    foreach ($response['fields'] as $field) { if (isset($remoteFields[$field['name']])) { throw new InvalidArgumentException('Duplicate external field'); } $remoteFields[$field['name']] = $field; }
                    $this->external = $remoteFields;
                }
                $remote = $this->external[$definition['name']] ?? null;
                if (!$remote || !in_array($remote['status'], ['resolved','missing', ...($type === 'historicalDate' ? ['manual'] : [])], true) || $remote['source'] !== $binding['source'] || $remote['propertyId'] !== $binding['id'] || !is_array($remote['values'] ?? null)) { throw new InvalidArgumentException('External field unavailable: '.$definition['name']); }
                $status = $remote['status'];
                foreach ($remote['values'] as $value) {
                    if ($value['kind'] === 'time' && $type === 'historicalDate') {
                        if (!is_array($value['sourceTime'] ?? null) || !is_string($value['label'] ?? null)) { throw new InvalidArgumentException('Invalid time'); }
                        $add($value['label'],$remote['source'],$value);
                    } elseif ($value['kind'] === 'text') {
                        if (!is_string($value['value'] ?? null) || trim($value['value']) === '') { throw new InvalidArgumentException('Invalid external text: '.$definition['name']); }
                        $add($value['value'],$remote['source'],$value);
                    } elseif ($value['kind'] === 'entity') {
                        if (!is_string($value['label'] ?? null) || $value['label'] === '' || $value['label'] === $value['id']) { throw new InvalidArgumentException('Missing entity label'); }
                        $add($value['label'],$remote['source'],$value);
                    } elseif (!in_array($value['kind'], ['somevalue','novalue'], true)) { throw new InvalidArgumentException('Unsupported external value'); }
                }
            }
            $fields[] = ['name'=>$definition['name'],'type'=>$type,'values'=>$values,'hasStoredValues'=>$stored !== [],'status'=>$status,'externalProperty'=>$binding,'rawStatement'=>$this->subject['statements'][$definition['name']] ?? null,'reason'=>$remote['reason'] ?? null];
        }
        if ($name !== null && !$found) { throw new InvalidArgumentException('Field not found: '.$name); }
        return ['subject'=>$this->subject,'schema'=>$this->schema,'fields'=>$fields];
    }
}
