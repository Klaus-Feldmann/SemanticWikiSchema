<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Domain\PropertyType\Types;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\ImageReference;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\ImageType;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Schema\Property\ImageProperty;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\SWS\Tests\Data\TestSources;

class ImageTypeTest extends TestCase {
	/** @dataProvider validReferences */
	public function testQualifiedIdentityRoundTrips( string $reference, string $repository, string $filename ): void {
		$parsed = ImageReference::parse( $reference );
		$this->assertSame( $reference, $parsed->toString() );
		$this->assertSame( $repository, $parsed->repository );
		$this->assertSame( $filename, $parsed->filename );
	}
	public static function validReferences(): array {
		return [ [ 'local:Goethe.jpg', 'local', 'Goethe.jpg' ], [ 'commons:Example.jpg', 'commons', 'Example.jpg' ],
			[ 'commons:Émile: Porträt.svg', 'commons', 'Émile: Porträt.svg' ] ];
	}
	/** @dataProvider invalidReferences */
	public function testRejectsUnqualifiedOrMalformedIdentity( string $reference ): void {
		$this->assertNull( ImageReference::parse( $reference ) );
	}
	public static function invalidReferences(): array {
		return array_map( static fn ( $s ) => [ $s ], [ 'Example.jpg', 'https://evil/Example.jpg', 'commons:', 'remote:X.jpg',
			'commons: X.jpg', 'local:X.jpg ', "local:X\n.jpg", 'local:[X].jpg', 'local:X#Y.jpg', 'local::X.jpg', 'local:' . str_repeat( 'x', 501 ) ] );
	}
	private function property( array $attributes = [] ): ImageProperty {
		return PropertyDefinition::fromJson( [ 'type' => 'image' ] + $attributes, TestSources::newPropertyTypeRegistry() );
	}
	public function testMetadataSurvivesAndNoNewGlobalValueTypeIsNeeded(): void {
		$json = [ 'multiple' => true, 'uniqueItems' => true, 'wikiLink' => [ 'labelTarget' => 'Konzept:Bild' ],
			'externalProperty' => [ 'source' => 'wikidata', 'id' => 'P18' ] ];
		$p = $this->property( $json );
		$this->assertSame( 'image', $p->getPropertyType() );
		foreach ( $json as $key => $value ) { $this->assertSame( $value, $p->toJson()[$key] ); }
		$this->assertSame( [], ( new ImageType() )->validate( new StringValue( 'commons:Example.jpg', 'local:Example.jpg' ), $p ) );
	}
	public function testRequiredSingleAndUniqueConstraints(): void {
		$type = new ImageType();
		$this->assertSame( 'required', $type->validate( new StringValue(), $this->property( [ 'required' => true ] ) )[0]->code );
		$this->assertSame( [], $type->validate( new StringValue(), $this->property() ) );
		$this->assertSame( 'image-single', $type->validate( new StringValue( 'local:A.jpg', 'commons:B.jpg' ), $this->property() )[0]->code );
		$this->assertSame( 'unique', $type->validate( new StringValue( 'local:A.jpg', 'local:A.jpg' ), $this->property( [ 'multiple' => true, 'uniqueItems' => true ] ) )[0]->code );
		$this->assertSame( 'invalid-image', $type->validate( new StringValue( 'https://evil/X.jpg' ), $this->property() )[0]->code );
	}
	public function testRdfAndSearchPreserveRepositoryIdentity(): void {
		$value = new StringValue( 'commons:Example.jpg', 'local:Example.jpg' );
		$terms = RdfValueMapperRegistry::withCoreMappers()->mapValue( 'image', $value );
		$this->assertCount( 2, $terms );
		$this->assertSame( $value->toScalars(), ( new ImageType() )->searchText( $value, $this->property() ) );
		$this->assertSame( $value->strings, array_map( static fn ( $term ) => $term->lexicalForm, $terms ) );
	}
	public function testJsonSchemaAndNeo4jPreserveReferences(): void {
		$p = $this->property( [ 'multiple' => true, 'uniqueItems' => true ] );
		$schema = json_decode( json_encode( $p->toJsonSchema() ) );
		$validator = new \Opis\JsonSchema\Validator();
		$this->assertTrue( $validator->validate( [ 'commons:Example.jpg', 'local:Example.jpg' ], $schema )->isValid() );
		$this->assertFalse( $validator->validate( [ 'https://evil/X.jpg' ], $schema )->isValid() );
		$this->assertFalse( $validator->validate( [ 'local:X.jpg', 'local:X.jpg' ], $schema )->isValid() );
		$value = new StringValue( 'commons:Example.jpg', 'local:Example.jpg' );
		$this->assertSame( $value->strings, \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry::withCoreBuilders()->buildNeo4jValue( 'image', $value ) );
		$this->assertTrue( \ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaContentValidator::newInstance()->validate( json_encode( [ 'propertyDefinitions' => [ 'Bild' => $p->toJson() ] ] ) ) );
	}

}
