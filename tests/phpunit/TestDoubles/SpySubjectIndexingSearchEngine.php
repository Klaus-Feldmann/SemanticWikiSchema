<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\EntryPoints\Search\IndexesSubjectText;

/**
 * A search engine that adds Subject text the way SWS's database engines do, over a parent that
 * records what it is handed instead of writing it anywhere. The trait's own update() is what runs,
 * as it does in those engines.
 */
class SpySubjectIndexingSearchEngine extends RecordingSearchEngine {

	use IndexesSubjectText;

}
