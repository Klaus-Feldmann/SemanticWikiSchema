<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain;

/** Wikipedia-convention dates in historical era numbering, without a year zero.
 * The lexical precision is significant: 1458 is not shorthand for 1458-01-01.
 */
readonly class HistoricalDate {
	public const string RDF_DATATYPE = 'urn:sws:datatype:historical-date:wikipedia:historical-era';
	public const string PATTERN = '^(-?)((?!0000)[0-9]{4})(?:-(0[1-9]|1[0-2])(?:-(0[1-9]|[12][0-9]|3[01]))?)?$';

	private function __construct(
		public int $year,
		public ?int $month,
		public ?int $day,
	) {
	}

	public static function parse( string $value ): ?self {
		if ( preg_match( '/' . self::PATTERN . '/D', $value, $m ) !== 1 || (int)$m[2] === 0 ) {
			return null;
		}
		$year = (int)$m[2] * ( $m[1] === '-' ? -1 : 1 );
		$month = isset( $m[3] ) ? (int)$m[3] : null;
		$day = isset( $m[4] ) ? (int)$m[4] : null;
		if ( $year === 1582 && $month === 10 && $day !== null && $day >= 5 && $day <= 14 ) {
			return null;
		}
		if ( $day !== null && $month !== null && $day > self::daysInMonth( $year, $month ) ) {
			return null;
		}
		return new self( $year, $month, $day );
	}

	private static function daysInMonth( int $year, int $month ): int {
		$y = $year < 0 ? $year + 1 : $year;
		$leap = $y % 4 === 0 && ( $year < 1582 || $y % 100 !== 0 || $y % 400 === 0 );
		return [ 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ][$month - 1];
	}

	/** Comparison bounds, NOT invented dates stored as if observed. */
	public function bounds(): array {
		$startMonth = $this->month ?? 1;
		$startDay = $this->day ?? 1;
		$endMonth = $this->month ?? 12;
		$endDay = $this->day ?? self::daysInMonth( $this->year, $endMonth );
		return [
			'start' => HistoricalCalendar::dayNumber( $this->year, $startMonth, $startDay, HistoricalCalendar::calendar( $this->year, $startMonth, $startDay ) ),
			'end' => HistoricalCalendar::dayNumber( $this->year, $endMonth, $endDay, HistoricalCalendar::calendar( $this->year, $endMonth, $endDay ) ),
		];
	}

	public function precision(): string {
		return $this->day !== null ? 'day' : ( $this->month !== null ? 'month' : 'year' );
	}
}
