<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\{Response, ResponseInterface, SimpleHandler, StringStream};
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Rdf\RdfFormat;
use ProfessionalWiki\SWS\EntryPoints\REST\ReadOnlyEndpoint;
use ProfessionalWiki\SWS\SWSExtension;
use Wikimedia\ParamValidator\ParamValidator;
use RuntimeException;

/** Complete view downloads, separate from the raw Subject edit/read API and graph-store projections. */
abstract class DataViewExportApi extends SimpleHandler {
	use ReadOnlyEndpoint;

	protected function exportView( Title $title, ?string $subjectId = null ): Response {
		if ( !$this->getAuthority()->authorizeRead( 'read', $title ) ) { return $this->unavailable(); }
		$params = $this->getValidatedParams();
		$context = new DerivativeContext( RequestContext::getMain() );
		// Preserve the REST caller's complete authority, including OAuth restrictions.
		$context->setAuthority( $this->getAuthority() );
		$language = $params['language'] ?? $context->getLanguage()->getCode();
		if ( preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $language ) !== 1 ) {
			return $this->getResponseFactory()->createHttpError( 400, [ 'message' => 'Invalid data language.' ] );
		}
		try {
			$view = ( new DataViewLookup( $context ) )->read( $title, $language, $subjectId );
		} catch ( RuntimeException ) { return $this->unavailable(); }
		if ( !$view['complete'] ) {
			return $this->getResponseFactory()->createHttpError( 503, [
				'message' => 'The complete SWS data view could not be resolved. No partial export was produced. Retry or inspect the data-view API for field/source status.',
			] );
		}
		$format = $params['format'] ?? 'json';
		if ( $format === 'json' ) {
			$body = json_encode( $view, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE |
				JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION ) . "\n";
			$mime = 'application/json; charset=utf-8'; $extension = 'json';
		} else {
			$sws = SWSExtension::getInstance();
			$gateway = new EntityGateway( $context );
			$quads = ( new DataViewRdfProjector( $sws->getRdfNamespaces(),
				static fn ( string $source, string $id ): ?string => $gateway->conceptUri( $source, $id ) ) )->project( $view );
			$body = $sws->getRdfSerializer()->serialize( $quads, $format === 'turtle' ? RdfFormat::Turtle : RdfFormat::TriG );
			$mime = $format === 'turtle' ? 'text/turtle; charset=utf-8' : 'application/trig; charset=utf-8';
			$extension = $format === 'turtle' ? 'ttl' : 'trig';
		}
		$response = $this->getResponseFactory()->create();
		$response->setHeader( 'Content-Type', $mime );
		$response->setHeader( 'Cache-Control', 'private, no-store' );
		$response->setHeader( 'Content-Disposition', 'attachment; filename="sws-' .
			( $subjectId === null ? 'page-' . $title->getArticleID() : 'subject-' . rawurlencode( $subjectId ) ) . '-data-view.' . $extension . '"' );
		$response->setHeader( 'X-Content-Type-Options', 'nosniff' );
		$response->setBody( new StringStream( $body ) );
		return $response;
	}

	public function applyCacheControl( ResponseInterface $response ): void {
		parent::applyCacheControl( $response );
		$response->setHeader( 'Cache-Control', 'private,no-store' );
	}

	protected function unavailable(): Response {
		return $this->getResponseFactory()->createHttpError( 404, [ 'message' => 'SWS export unavailable.' ] );
	}
	protected function commonParams(): array {
		return [
			'format' => [ self::PARAM_SOURCE => 'query', ParamValidator::PARAM_TYPE => [ 'json', 'turtle', 'trig' ], ParamValidator::PARAM_DEFAULT => 'json' ],
			'language' => [ self::PARAM_SOURCE => 'query', ParamValidator::PARAM_TYPE => 'string' ],
		];
	}
}
