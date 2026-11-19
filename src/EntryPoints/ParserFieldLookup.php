<?php

declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints;

use MediaWiki\Context\{ DerivativeContext, RequestContext };
use MediaWiki\Parser\Parser;
use AEWI\SWS\Infrastructure\ParserPageDependencyRecorder;

/** Parser authority and dependency recording are mandatory on both Wikitext and Lua paths. */
final class ParserFieldLookup {
	public static function create( Parser $parser ): EffectiveFieldLookup {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( ParserAuthority::of( $parser ) );
		return new EffectiveFieldLookup( $context, new ParserPageDependencyRecorder( $parser ), canonicalId: fn ( $id ) => \AEWI\SWS\SWSExtension::getInstance()->newIdentityBoundaryResolver()->entity( $id ) );
	}
}
