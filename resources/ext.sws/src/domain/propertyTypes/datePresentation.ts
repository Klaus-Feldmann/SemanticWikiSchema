import type { PropertyDefinition } from '../PropertyDefinition';
import type { HistoricalDate } from './historicalDate';
import type { DatePart } from '../../composables/useExternalStatements';

export function dateParts( date: HistoricalDate, property: PropertyDefinition, language: string, wikiLanguage: string ): DatePart[] {
	const en = language.startsWith( 'en' );
	const suffix = en ? property.wikiLink?.dateBceEn || 'BC' : property.wikiLink?.dateBceDe || 'v. Chr.';
	const months = en ? [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ] :
		[ 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' ];
	let prefix = date.day === undefined ? '' : date.day + ( en ? ' ' : '. ' );
	if ( date.month !== undefined ) {
		prefix += months[ date.month - 1 ] + ' ';
	}
	return [ ...( prefix ? [ { text: prefix } ] : [] ), {
		text: String( Math.abs( date.year ) ) + ( date.year < 0 ? ' ' + suffix : '' ),
		yearTitle: date.year < 0 ? Math.abs( date.year ) + ( wikiLanguage.startsWith( 'en' ) ? ' BC' : ' v. Chr.' ) : String( date.year ),
	} ];
}
