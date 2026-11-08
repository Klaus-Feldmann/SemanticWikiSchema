<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Presentation;

use MediaWiki\Skin\SkinComponentUtils;
use MediaWiki\Title\Title;

class PageToolsBuilder {

	public const CREATE_SUBJECT_ID = 't-sws-create-subject';

	/**
	 * @return list<array<string, mixed>>
	 */
	public function build(
		Title $title,
		int $pageId,
		bool $canCreateMainSubject,
		bool $canEditSubject,
		bool $isLatestRevision,
		bool $devUiEnabled
	): array {
		$items = [];

		if ( $canCreateMainSubject && $isLatestRevision ) {
			$items[] = [
				'text' => wfMessage( 'sws-page-tools-create-subject' )->text(),
				'href' => '#',
				'id' => self::CREATE_SUBJECT_ID,
				'data' => [
					'mw-sws-action' => 'open-subject-creator',
				],
			];
		}

		// Every page that exists has an RDF export, so every page links to one. A title with no page
		// behind it has nothing to export. The link 404s for the one page state the export cannot
		// describe: a subject slot holding content that is not Subject data.
		if ( $pageId !== 0 ) {
			$items[] = [
				'text' => wfMessage( 'sws-page-tools-rdf' )->text(),
				'href' => wfScript( 'rest' ) . '/sws/v0/page/'
					. $pageId . '/rdf?format=turtle',
				'id' => 't-sws-rdf',
			];
		}

		if ( $devUiEnabled ) {
			$items[] = [
				'text' => wfMessage(
					$canEditSubject ? 'sws-page-tools-edit-json' : 'sws-page-tools-view-json'
				)->text(),
				'href' => SkinComponentUtils::makeSpecialUrlSubpage( 'NeoJson', $title->getFullText() ),
				'id' => 't-sws-edit-json',
			];
		}

		return $items;
	}

}
