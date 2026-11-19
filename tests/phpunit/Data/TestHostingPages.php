<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Data;

use AEWI\SWS\Application\PageReadAuthorizer;
use AEWI\SWS\Application\SubjectHostingPageResolver;
use AEWI\SWS\Domain\Page\PageId;
use AEWI\SWS\Domain\Page\PageIdentifiers;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use AEWI\SWS\Tests\TestDoubles\StubPageReadAuthorizer;

class TestHostingPages {

	public const string SUBJECT_ID = 's11111111111127';

	/**
	 * The Subject under test sits on a namespaced page between two others, so neither a hardcoded
	 * main-namespace id nor an implementation answering with some other seeded page passes.
	 */
	public static function newResolverForSubjectOnNamespacedPage( ?PageReadAuthorizer $readAuthorizer = null ): SubjectHostingPageResolver {
		return new SubjectHostingPageResolver(
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111126' ), new PageIdentifiers( new PageId( 6 ), 'Earlier page', 0 ) ],
				[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 7 ), 'Help:Test page', 12 ) ],
				[ new SubjectId( 's11111111111128' ), new PageIdentifiers( new PageId( 8 ), 'Talk:Later page', 1 ) ],
			] ),
			$readAuthorizer ?? new StubPageReadAuthorizer( allowed: true )
		);
	}

}
