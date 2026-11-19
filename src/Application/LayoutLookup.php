<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application;

use AEWI\SWS\Domain\Layout\Layout;
use AEWI\SWS\Domain\Layout\LayoutName;

interface LayoutLookup {

	public function getLayout( LayoutName $layoutName ): ?Layout;

}
