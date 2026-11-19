<?php

declare( strict_types = 1 );
namespace AEWI\SWS\Tests\Application\Search;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\Search\MissingMainDescription;
use AEWI\SWS\Domain\Identity\LocalizedIdentity;
use AEWI\SWS\Domain\Page\PageSubjects;
use AEWI\SWS\Domain\Subject\SubjectMap;
use AEWI\SWS\Tests\Data\TestSubject;

final class MissingMainDescriptionTest extends TestCase {
	public function testAnEmptyPageDoesNotNeedADescription(): void {
		$this->assertFalse( MissingMainDescription::appliesTo( PageSubjects::newEmpty() ) );
	}
	public function testALegacyMainEntityWithoutMetadataIsMarked(): void {
		$this->assertTrue( MissingMainDescription::appliesTo( new PageSubjects( TestSubject::build(), new SubjectMap() ) ) );
	}
	public function testWhitespaceOnlyDescriptionsAreMissing(): void {
		$main = TestSubject::build();
		$main->setIdentity( new LocalizedIdentity( 'de', [], [ 'de' => '  ', 'en' => '' ] ) );
		$this->assertTrue( MissingMainDescription::appliesTo( new PageSubjects( $main, new SubjectMap() ) ) );
	}
	public function testAnOwnDescriptionInAnyLanguageSufficesAndAnUndescribedSecondaryEntityDoesNotMatter(): void {
		$main = TestSubject::build();
		$main->setIdentity( new LocalizedIdentity( 'de', [], [ 'en' => 'Nested quotations.' ] ) );
		$secondary = TestSubject::build( id: 's22222222222222' );
		$this->assertFalse( MissingMainDescription::appliesTo( new PageSubjects( $main, new SubjectMap( $secondary ) ) ) );
	}
	public function testSecondaryEntitiesWithoutAMainEntityDoNotMarkThePage(): void {
		$this->assertFalse( MissingMainDescription::appliesTo( new PageSubjects( null, new SubjectMap( TestSubject::build() ) ) ) );
	}
}
