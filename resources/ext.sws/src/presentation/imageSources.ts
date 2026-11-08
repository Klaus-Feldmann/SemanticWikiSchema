import type { ImageSource } from '@/domain/propertyTypes/Image';
export function projectName(): string {
	return String( mw.config.get( 'wgSWSProjectName' ) || mw.config.get( 'wgSiteName' ) || mw.config.get( 'wgMetaNamespace' ) || mw.message( 'sws-image-local' ).text() );
}
export function imageSourceOptions(): { value: ImageSource; label: string }[] {
	return [ { value: 'wikidata', label: 'Wikidata' }, { value: 'wikibase-local', label: 'Wikibase' },
		{ value: 'local', label: projectName() }, { value: 'commons', label: 'Commons' } ];
}
