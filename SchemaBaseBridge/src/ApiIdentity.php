<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ApiBase;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\SWSExtension;

final class ApiIdentity extends ApiBase {
	public function execute(): void {
		$this->getMain()->setCacheMode( 'private' );
		$params = $this->extractRequestParams();
		$title = Title::newFromID( $params['pageid'] );
		if ( $title === null || !$this->getAuthority()->authorizeRead( 'read', $title ) ||
			( $params['descriptions'] && preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $params['language'] ) !== 1 ) ) {
			$this->dieWithError( 'swsbasebridge-unavailable', 'unavailable' );
		}
		$pageSubjects = SWSExtension::getInstance()->newPageSubjectsLookup()
			->getPageSubjects( new PageId( $title->getArticleID() ) );
		$mainId = $pageSubjects->getMainSubject()?->id->text;
		$descriptionIdentities = $params['descriptions'] && $mainId !== null
			? ( new DataViewLookup( $this->getContext() ) )->readIdentities( $title, $params['language'] ) : [];
		$pageDescription = $params['descriptions'] ? ( new PageDescriptionLookup() )->read( $title, $this->getAuthority() ) : [];
		$lookup = new PageIdentityLookup();
		$identities = ( new AutomaticIdentityService() )->resolve( $mainId, [
			'wikidata' => fn () => $descriptionIdentities['wikidata'] ?? $lookup->wikidata( $title ),
			'wikibase-local' => fn () => $descriptionIdentities['wikibase-local'] ?? $lookup->wikibase( $title, $this->getAuthority() ),
		] );
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'version' => 2, 'pageId' => $title->getArticleID(), 'scope' => 'current',
			'mainSubjectId' => $mainId, 'identities' => $identities,
			...( $params['descriptions'] ? [ 'descriptions' => DescriptionPresentation::groups( $descriptionIdentities ),
				'pageDescription' => $pageDescription,
				'displayDescription' => $mainId === null ? null : DescriptionPresentation::select( $descriptionIdentities, $pageDescription ) ] : [] ),
		] );
	}

	public function getAllowedParams(): array {
		return [ 'pageid' => [ self::PARAM_TYPE => 'integer', self::PARAM_MIN => 1, self::PARAM_REQUIRED => true ],
			'descriptions' => [ self::PARAM_TYPE => 'boolean', self::PARAM_DFLT => false ],
			'language' => [ self::PARAM_TYPE => 'string', self::PARAM_DFLT => $this->getLanguage()->getCode() ] ];
	}
	public function isWriteMode(): bool { return false; }
	protected function getExamplesMessages(): array { return []; }
}
