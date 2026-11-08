<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\ParserAuthority;
use ProfessionalWiki\SWS\Infrastructure\ParserPageDependencyRecorder;
use Throwable;

/** Read access for the parser's user; all cached results live only in this library instance. */
class ScribuntoLuaLibrary extends LibraryBase {

	private array $results = [];

	public function register(): array {
		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.swsbase.lua', [ 'getFields' => [ $this, 'getFields' ] ], []
		);
	}

	public function getFields( ?array $options = null ): array {
		$options ??= [];
		foreach ( $options as $key => $value ) {
			if ( !in_array( $key, [ 'page', 'subject', 'language' ], true ) || !is_string( $value ) ) {
				throw new LuaError( 'SWSBaseBridge: invalid lookup option' );
			}
		}
		$title = isset( $options['page'] ) ? Title::newFromText( $options['page'] ) : $this->getTitle();
		$language = $options['language'] ?? MediaWikiServices::getInstance()->getContentLanguage()->getCode();
		if ( $title === null || $title->isExternal() || !$title->canExist() ||
			preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $language ) !== 1 ) {
			throw new LuaError( 'SWSBaseBridge: invalid page or language' );
		}
		$authority = ParserAuthority::of( $this->getParser() );
		if ( !$authority->authorizeRead( 'read', $title ) ) {
			throw new LuaError( 'SWSBaseBridge: page unavailable' );
		}
		$key = json_encode( [ $title->getPrefixedText(), $options['subject'] ?? null, $language ] );
		if ( !array_key_exists( $key, $this->results ) ) {
			$this->incrementExpensiveFunctionCount();
			$context = new DerivativeContext( RequestContext::getMain() );
			$context->setUser( MediaWikiServices::getInstance()->getUserFactory()
				->newFromUserIdentity( $this->getParser()->getUserIdentity() ) );
			try {
				$this->results[$key] = ( new ExternalValuesLookup( $context ) )->read( $title, [
					'pageid' => $title->getArticleID(), 'subject' => $options['subject'] ?? null,
					'language' => $language, 'presentation' => false,
				], new ParserPageDependencyRecorder( $this->getParser() ) );
			} catch ( Throwable ) {
				throw new LuaError( 'SWSBaseBridge: derived values unavailable' );
			}
		}
		return [ self::toLua( $this->results[$key] ) ];
	}

	/** Lua ipairs requires lists starting at 1, including nested field values. */
	private static function toLua( mixed $value ): mixed {
		if ( !is_array( $value ) ) {
			return $value;
		}
		$result = [];
		$list = array_is_list( $value );
		foreach ( $value as $key => $item ) {
			$result[$list ? $key + 1 : $key] = self::toLua( $item );
		}
		return $result;
	}
}
