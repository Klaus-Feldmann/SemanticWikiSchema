<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use AEWI\SWS\Domain\Schema\Property\TextProperty;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\Schema\PropertyDefinitions;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Schema\Schema;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Domain\Validation\Violation;
use AEWI\SWS\Presentation\RestCreateSubjectPresenter;
use AEWI\SWS\Tests\Data\TestSchema;

/**
 * @covers \AEWI\SWS\Presentation\RestCreateSubjectPresenter
 */
class RestCreateSubjectPresenterTest extends TestCase {

	private function newSubject( ?int $pageId = 42 ): GetSubjectResponseItem {
		return new GetSubjectResponseItem(
			id: 's1demo1aaaaaaa1',
			label: 'ACME Corp',
			displayName: 'ACME Corp',
			displayNameIsGenerated: false,
			schema: 'Organization',
			statements: [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			pageId: $pageId,
			pageTitle: $pageId === null ? null : 'ACME Corp',
			pageNamespaceId: $pageId === null ? null : 0,
			hostSubject: null,
		);
	}

	private function newSchema(): Schema {
		return TestSchema::build(
			name: new SchemaName( 'Organization' ),
			description: 'Companies and the like',
			properties: new PropertyDefinitions( [
				'Animal' => new TextProperty(
					core: new PropertyCore( description: 'Mascot', required: false, default: null ),
					multiple: false,
					uniqueItems: false,
					minLength: null,
					maxLength: null,
				),
			] )
		);
	}

	public function testPresentCreatedYields201WithSerializedViolations(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), null, [
			new Violation( propertyName: new PropertyName( 'Status' ), code: 'required' ),
		] );

		$this->assertSame( 201, $presenter->getStatusCode() );
		$this->assertSame( 'created', $presenter->getJsonArray()['status'] );
		$this->assertSame( 's1demo1aaaaaaa1', $presenter->getJsonArray()['subjectId'] );
		$this->assertSame(
			[ [ 'propertyName' => 'Status', 'code' => 'required', 'args' => [], 'severity' => 'warning' ] ],
			$presenter->getJsonArray()['violations']
		);
	}

	public function testPresentCreatedWithNoViolationsYieldsEmptyViolationsArray(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), null, [] );

		$this->assertSame( 201, $presenter->getStatusCode() );
		$this->assertSame( [], $presenter->getJsonArray()['violations'] );
	}

	public function testPresentCreatedIncludesTheCanonicalSubjectWithItsPageIdentifiers(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), null, [] );

		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => 'ACME Corp',
				'displayName' => 'ACME Corp',
				'displayNameIsGenerated' => false,
				'schema' => 'Organization',
				'pageId' => 42,
				'pageTitle' => 'ACME Corp',
				'pageNamespaceId' => 0,
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			$presenter->getJsonArray()['subject']
		);
	}

	public function testPresentCreatedOmitsPageKeysWhenTheHostingPageIsUnresolved(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject( pageId: null ), null, [] );

		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => 'ACME Corp',
				'displayName' => 'ACME Corp',
				'displayNameIsGenerated' => false,
				'schema' => 'Organization',
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			$presenter->getJsonArray()['subject']
		);
	}

	public function testPresentCreatedIncludesTheSchemaBodyWithoutItsName(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), $this->newSchema(), [] );

		$this->assertSame(
			[
				'description' => 'Companies and the like',
				'propertyDefinitions' => [
					'Animal' => [
						'type' => 'text',
						'description' => 'Mascot',
						'required' => false,
						'default' => null,
						'multiple' => false,
						'uniqueItems' => false,
						'minLength' => null,
						'maxLength' => null,
					],
				],
			],
			$presenter->getJsonArray()['schema']
		);
	}

	public function testPresentCreatedOmitsTheSchemaKeyWhenTheSchemaIsMissing(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), null, [] );

		$this->assertArrayNotHasKey( 'schema', $presenter->getJsonArray() );
	}

	public function testPresentSubjectAlreadyExistsYields409ErrorWithoutViolations(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentSubjectAlreadyExists();

		$this->assertSame( 409, $presenter->getStatusCode() );
		$this->assertSame(
			[ 'status' => 'error', 'message' => 'Subject already exists' ],
			$presenter->getJsonArray()
		);
	}

	public function testPresentValidationFailedYields422WithErrorBody(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentValidationFailed( [
			new Violation( propertyName: new PropertyName( 'Required' ), code: 'required' ),
		] );

		$this->assertSame( 422, $presenter->getStatusCode() );
		$this->assertSame(
			[
				'status' => 'error',
				'message' => 'Validation failed',
				'violations' => [
					[ 'propertyName' => 'Required', 'code' => 'required', 'args' => [], 'severity' => 'warning' ],
				],
			],
			$presenter->getJsonArray()
		);
	}

}
