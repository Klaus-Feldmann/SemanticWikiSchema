export interface HistoricalDate {
	year: number;
	month?: number;
	day?: number;
}

// Wikipedia calendar convention, historical era numbering: -0044 is 44 BCE; -0001 is 1 BCE. No year zero.
export function parseHistoricalDate( value: string ): HistoricalDate | null {
	// Every repetition is bounded; the optional month/day groups have distinct separators.
	// eslint-disable-next-line security/detect-unsafe-regex
	const match = /^(-?)((?!0000)[0-9]{4})(?:-(0[1-9]|1[0-2])(?:-(0[1-9]|[12][0-9]|3[01]))?)?$/.exec( value );
	if ( !match || Number( match[ 2 ] ) === 0 ) {
		return null;
	}
	const year = Number( match[ 2 ] ) * ( match[ 1 ] === '-' ? -1 : 1 );
	const month = match[ 3 ] === undefined ? undefined : Number( match[ 3 ] );
	const day = match[ 4 ] === undefined ? undefined : Number( match[ 4 ] );
	const astronomicalYear = year < 0 ? year + 1 : year;
	const julian = year < 1582 || year === 1582 && ( month ?? 1 ) < 10 || year === 1582 && month === 10 && ( day ?? 1 ) <= 4;
	const leap = astronomicalYear % 4 === 0 && ( julian || astronomicalYear % 100 !== 0 || astronomicalYear % 400 === 0 );
	const days = [ 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
	if ( year === 1582 && month === 10 && day !== undefined && day >= 5 && day <= 14 ) {
		return null;
	}
	if ( day !== undefined && month !== undefined && day > days[ month - 1 ] ) {
		return null;
	}
	return { year, ...( month === undefined ? {} : { month } ), ...( day === undefined ? {} : { day } ) };
}

export function historicalYearTitle( year: number ): string {
	return year < 0 ? Math.abs( year ) + ' v. Chr.' : String( year );
}

/** Inclusive Julian-day bounds; missing components remain absent in storage. */
export function historicalDateBounds( date: HistoricalDate ): { start: number; end: number } {
	const endMonth = date.month ?? 12;
	const y = date.year < 0 ? date.year + 1 : date.year;
	const leap = y % 4 === 0 && ( date.year < 1582 || y % 100 !== 0 || y % 400 === 0 );
	const endDay = date.day ?? [ 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ][ endMonth - 1 ];
	return { start: dayNumber( date.year, date.month ?? 1, date.day ?? 1 ), end: dayNumber( date.year, endMonth, endDay ) };
}

function dayNumber( year: number, month: number, day: number ): number {
	const julian = year < 1582 || year === 1582 && ( month < 10 || month === 10 && day <= 4 );
	const a = Math.floor( ( 14 - month ) / 12 );
	const y = ( year < 0 ? year + 1 : year ) + 4800 - a;
	const m = month + 12 * a - 3;
	const j = day + Math.floor( ( 153 * m + 2 ) / 5 ) + 365 * y + Math.floor( y / 4 );
	return julian ? j - 32083 : j - Math.floor( y / 100 ) + Math.floor( y / 400 ) - 32045;
}
