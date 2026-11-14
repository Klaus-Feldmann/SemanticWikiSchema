<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\SWS\Application\SubjectResolver;
use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Value\BooleanValue;
use ProfessionalWiki\SWS\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\NumberValue;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;

class SWSValueParserFunction {

	private const string DEFAULT_SEPARATOR = ', ';

	public function __construct(
		private readonly SubjectResolver $subjectResolver,
		private readonly ?EffectiveFieldLookup $fields = null,
	) {
	}

	/**
	 * @return array{0: string, noparse: true, isHTML: false}|string
	 */
	public function handle( Parser $parser, string ...$args ): string|array {
		$call = self::parseCall( $args );
		if ( $call === null ) { return ''; }
		[ $propertyName, $params ] = $call;
		if ( ( isset( $params['subject'] ) || isset( $params['page'] ) ) && !$parser->incrementExpensiveFunctionCount() ) {
			return '';
		}
		if ( $this->fields !== null && ( ( $params['origin'] ?? 'all' ) !== 'own' || isset( $params['part'] ) ) ) {
			return $this->effective( $parser, $propertyName, $params );
		}


		$subject = $this->resolveSubject( $parser, $params );

		if ( $subject === null ) {
			return '';
		}

		$statement = $subject->getStatements()->getStatement( new PropertyName( $propertyName ) );

		if ( $statement === null ) {
			return '';
		}

		$separator = $params['separator'] ?? self::DEFAULT_SEPARATOR;
		$formatted = $this->formatValue( $this->subjectResolver->resolveDisplayValue( $subject, $statement ), $separator );

		if ( $formatted === '' ) {
			return '';
		}

		return [ wfEscapeWikiText( $formatted ), 'noparse' => true, 'isHTML' => false ];
	}

	/** Return core-escaped Wikitext text, without HTML strip markers: caller links remain composable. */
	private function effective( Parser $parser, string $name, array $params ): string|array {
		$part = $params['part'] ?? 'value';
		if ( !in_array( $part, [ 'value', 'authorNote', 'sourceNote', 'origin', 'status', 'sourceStatus' ], true ) ) { return ''; }
		$title = $parser->getTitle();
		if ( $title === null ) { return ''; }
		$options = array_intersect_key( $params, array_flip( [ 'page', 'subject', 'language', 'origin' ] ) );
		$options['field'] = $name;
		try { $result = $this->fields->read( $title, $options ); }
		catch ( \RuntimeException|\InvalidArgumentException $e ) {
			if ( $processing = \ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext::current() ) { $processing->failure ??= $e->getMessage(); }
			return '';
		}
		$field = $result['fields'][0] ?? null;
		if ( $field === null ) { return ''; }
		$text = match ( $part ) {
			'value' => implode( $params['separator'] ?? self::DEFAULT_SEPARATOR, array_column( $field['values'], 'value' ) ),
			'origin' => implode( $params['separator'] ?? self::DEFAULT_SEPARATOR, array_values( array_unique( array_column( $field['values'], 'origin' ) ) ) ),
			default => $field[$part] ?? '',
		};
		return [ wfEscapeWikiText( (string)$text ), 'noparse' => true, 'isHTML' => false ];
	}

	/**
	 * Public syntax: field [| options] or E-ID | field [| options].
	 * `subject` below is an internal lookup option, never an accepted public argument.
	 * Reject invalid/ambiguous calls before any lookup, including the removed subject= spelling.
	 * @param string[] $args
	 * @return array{string, array<string, string>}|null
	 */
	private static function parseCall( array $args ): ?array {
		$field = trim( $args[0] ?? '' );
		if ( $field === '' ) { return null; }
		$params = [];
		$rest = array_slice( $args, 1 );
		if ( isset( $rest[0] ) && !str_contains( $rest[0], '=' ) ) {
			if ( preg_match( '/^E[1-9][0-9]{0,17}\z/', $field ) !== 1 ) { return null; }
			$params['subject'] = $field;
			$field = trim( array_shift( $rest ) );
			if ( $field === '' ) { return null; }
		}
		foreach ( $rest as $arg ) {
			$parts = explode( '=', $arg, 2 );
			if ( count( $parts ) !== 2 ) { return null; }
			$key = trim( $parts[0] );
			if ( !in_array( $key, [ 'page', 'separator', 'language', 'origin', 'part' ], true ) || array_key_exists( $key, $params ) ) {
				return null;
			}
			$params[$key] = trim( $parts[1] );
		}
		if ( isset( $params['page'] ) && ( $params['page'] === '' || isset( $params['subject'] ) ) ) { return null; }
		if ( isset( $params['origin'] ) && !in_array( $params['origin'], [ 'all', 'own', 'external' ], true ) ) { return null; }
		return [ $field, $params ];
	}

	/**
	 * @param array<string, string> $params
	 */
	private function resolveSubject( Parser $parser, array $params ): ?Subject {
		if ( isset( $params['subject'] ) ) {
			return $this->subjectResolver->resolveById( $params['subject'] );
		}

		if ( isset( $params['page'] ) ) {
			return $this->subjectResolver->resolveMainByPageName( $params['page'] );
		}

		$title = $parser->getTitle();

		if ( $title === null ) {
			return null;
		}

		return $this->subjectResolver->resolveMainByTitle( $title );
	}

	private function formatValue( NeoValue $value, string $separator ): string {
		if ( $value->isEmpty() ) {
			return '';
		}

		if ( $value instanceof StringValue ) {
			return implode( $separator, $value->strings );
		}

		if ( $value instanceof NumberValue ) {
			return (string)$value->number;
		}

		if ( $value instanceof BooleanValue ) {
			return $value->boolean ? 'true' : 'false';
		}

		if ( $value instanceof RelationValue ) {
			return $this->formatRelationValue( $value, $separator );
		}

		if ( $value instanceof MonolingualTextValue ) {
			return implode( $separator, $value->getTexts() );
		}

		return '';
	}

	private function formatRelationValue( RelationValue $value, string $separator ): string {
		$labels = array_map(
			fn( Relation $relation ) => $this->subjectResolver->resolveRelationLabel( $relation ),
			$value->relations
		);

		return implode( $separator, $labels );
	}

}
