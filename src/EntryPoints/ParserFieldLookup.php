<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints;

use MediaWiki\Context\{ DerivativeContext, RequestContext };
use MediaWiki\Parser\Parser;
use ProfessionalWiki\SWS\Infrastructure\ParserPageDependencyRecorder;

/** Parser authority and dependency recording are mandatory on both Wikitext and Lua paths. */
final class ParserFieldLookup {
	public static function create( Parser $parser ): EffectiveFieldLookup {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( ParserAuthority::of( $parser ) );
		return new EffectiveFieldLookup( $context, new ParserPageDependencyRecorder( $parser ), canonicalId: fn ( $id ) => \ProfessionalWiki\SWS\SWSExtension::getInstance()->newIdentityBoundaryResolver()->entity( $id ) );
	}
}
