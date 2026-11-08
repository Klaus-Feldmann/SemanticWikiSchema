<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Schema\Property;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\UrlType;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;
use ProfessionalWiki\SWS\Tests\JsonSchemaAssertions;

/**
 * @covers \ProfessionalWiki\SWS\Domain\Schema\Property\UrlProperty
 */
class UrlPropertyTest extends TestCase {

	use JsonSchemaAssertions;

	public function testValueSupportsLegacyStringsAndStructuredEntries(): void {
		$schema = TestProperty::buildUrl( multiple: true )->toJsonSchema();
		$this->assertTrue( $this->jsonSchemaAccepts( $schema, [ 'https://example.org' ] ) );
		$this->assertTrue( $this->jsonSchemaAccepts( $schema, [ [ 'url' => 'https://example.org', 'label' => 'Title', 'caption' => 'Source', 'captionPage' => 'Vermerk:Quelle' ] ] ) );
		$this->assertFalse( $this->jsonSchemaAccepts( $schema, [ [ 'url' => 'https://example.org', 'label' => 7 ] ] ) );
		$this->assertFalse( $this->jsonSchemaAccepts( $schema, [ [ 'url' => 'https://example.org', 'unknown' => 'Do not lose' ] ] ) );
	}

	public function testValueDoesNotClaimTheUriFormat(): void {
		$this->assertArrayNotHasKey(
			'format',
			TestProperty::buildUrl()->toJsonSchema()['items'],
			'format: uri rejects the scheme-less values SWS accepts and accepts the schemes it rejects.'
		);
	}

	public function testUniqueItemsConstraintForbidsDuplicateParts(): void {
		$schema = TestProperty::buildUrl( multiple: true, uniqueItems: true )->toJsonSchema();
		$this->assertFalse( $this->jsonSchemaAccepts( $schema, [ [ 'url' => 'https://example.org', 'label' => 'One' ], [ 'url' => 'https://example.org', 'label' => 'One' ] ] ) );
		$this->assertTrue( $this->jsonSchemaAccepts( $schema, [ [ 'url' => 'https://example.org', 'label' => 'One' ], [ 'url' => 'https://example.org', 'label' => 'Two' ] ] ) );
	}

	/**
	 * @dataProvider urlPatternProvider
	 */
	public function testPatternJudgesAUrl( string $url, bool $valid ): void {
		$items = TestProperty::buildUrl()->toJsonSchema()['items'];

		$this->assertSame( $valid, $this->jsonSchemaAccepts( $items, $url ) );
	}

	public static function urlPatternProvider(): iterable {
		yield 'a host alone' => [ 'example.com', true ];
		yield 'an uppercase scheme and host' => [ 'HTTPS://EXAMPLE.COM', true ];
		yield 'a path, query and fragment in mixed case' => [ 'http://example.com/A/B?Q=1#Frag', true ];
		yield 'a colon with no port' => [ 'http://example.com:/a', false ];
		yield 'a host label ending in a hyphen' => [ 'http://exa-.com', false ];
		yield 'a host label starting with a hyphen' => [ 'http://-example.com', false ];
		yield 'a single-letter top-level domain' => [ 'http://example.c', false ];
		yield 'a scheme other than http or https' => [ 'ftp://example.com', false ];
		yield 'a space in the path' => [ 'https://example.com/a b', false ];
	}

}
