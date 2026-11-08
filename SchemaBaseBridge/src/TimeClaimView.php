<?php

declare(strict_types=1);

namespace SWSBaseBridge;

/** A unique best-rank precise statement, or an explicit request for a manual date. */
final class TimeClaimView
{
    public static function result(array $claims, string $source, string $language, string $wikiLanguage, array $options = []): array
    {
        $claims = array_values(array_filter($claims, static fn (array $c): bool => ($c['rank'] ?? 'normal') !== 'deprecated'));
        $preferred = array_values(array_filter($claims, static fn (array $c): bool => ($c['rank'] ?? 'normal') === 'preferred'));
        $candidates = $preferred !== [] ? $preferred : $claims;
        $raw = array_map(static fn (array $c): array => array_intersect_key($c, array_flip(['id','rank','mainsnak','qualifiers'])), $claims);
        if ($candidates === []) {
            return ['status' => 'missing','values' => [],'candidates' => $raw];
        }
        if (count($candidates) !== 1) {
            return ['status' => 'manual','reason' => 'multiple','values' => [],'candidates' => $raw];
        }
        $c = $candidates[0];
        $snak = $c['mainsnak'] ?? [];
        if (($snak['snaktype'] ?? '') !== 'value' || ($snak['datatype'] ?? '') !== 'time') {
            return ['status' => 'manual','reason' => 'uncertain','values' => [],'candidates' => $raw];
        }
        $q = $c['qualifiers'] ?? [];
        // Local property IDs must never inherit meanings from similarly numbered Wikidata properties.
        if ($source === 'wikidata' && array_intersect(['P1319','P1326','P1480'], array_keys($q)) !== []) {
            return ['status' => 'manual','reason' => 'uncertain','values' => [],'candidates' => $raw];
        }
        if ($source === 'wikibase-local') {
            foreach ($q as $qualifiers) {
                foreach ($qualifiers as $qualifier) {
                    if (($qualifier['datatype'] ?? '') === 'time') {
                        return ['status' => 'manual', 'reason' => 'uncertain', 'values' => [], 'candidates' => $raw];
                    }
                }
            }
        }
        $time = HistoricalTime::read($snak['datavalue']['value'] ?? []);
        if ($time === null) {
            return ['status' => 'manual','reason' => 'imprecise','values' => [],'candidates' => $raw];
        }
        $parts = TimePresentation::parts($time['displayDate'], $language, $wikiLanguage, $options);
        return ['status' => 'resolved','values' => [['kind' => 'time',...$time,'parts' => $parts,
         'label' => implode('', array_column($parts, 'text')),'rank' => $c['rank'] ?? 'normal',
         'statementId' => $c['id'] ?? null]],'candidates' => $raw];
    }
}
