<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application;

use ProfessionalWiki\SWS\Domain\Layout\Layout;
use ProfessionalWiki\SWS\Domain\Layout\LayoutName;

interface LayoutLookup {

	public function getLayout( LayoutName $layoutName ): ?Layout;

}
