<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Presentation;

use ProfessionalWiki\SWS\Application\HostSubject;
use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use stdClass;

/**
 * The one Subject shape the web API serves, so a Subject returned by a write endpoint is
 * interchangeable with the same Subject read back.
 */
class SubjectPresentationSerializer {

	/**
	 * @return array<string, mixed>
	 */
	public function serialize( GetSubjectResponseItem $item ): array {
		$entry = [
			'id' => $item->id,
			'label' => $item->label,
			'displayName' => $item->displayName,
			'displayNameIsGenerated' => $item->displayNameIsGenerated,
			'schema' => $item->schema,
		];

		if ( $item->identity !== null ) { $entry += $item->identity; }
		if ( $item->hostSubject !== null ) {
			$entry['hostSubject'] = self::serializeHostSubject( $item->hostSubject );
		}

		if ( $item->pageId !== null ) {
			$entry['pageId'] = $item->pageId;
			$entry['pageTitle'] = $item->pageTitle;
			$entry['pageNamespaceId'] = $item->pageNamespaceId;
		}

		// An empty PHP array encodes as `[]`, which is not the object keyed by property name that
		// the Subject format specifies, and that a Subject holding Statements already serves.
		if ( $item->fieldKeyVersion !== 0 ) { $entry['fieldKeyVersion'] = $item->fieldKeyVersion; }
		if ( $item->fieldNotes !== [] ) { $entry['fieldNotes'] = (object)$item->fieldNotes; }
        $entry['statements'] = $item->statements === [] ? new stdClass() : $item->statements;

		return $entry;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function serializeHostSubject( HostSubject $hostSubject ): array {
		$entry = [
			'id' => $hostSubject->id,
			'displayName' => $hostSubject->displayName,
			'displayNameIsGenerated' => $hostSubject->displayNameIsGenerated,
			'schema' => $hostSubject->schema,
		];

		if ( $hostSubject->hostSubject !== null ) {
			$entry['hostSubject'] = self::serializeHostSubject( $hostSubject->hostSubject );
		}

		return $entry;
	}

}
