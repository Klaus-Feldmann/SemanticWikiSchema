<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\RedHerb;

use MediaWiki\Output\OutputPage;
use PHPUnit\Framework\TestCase;
use AEWI\RedHerb\RedHerbFrontendModulesHook;
use Skin;

/**
 * @covers \AEWI\RedHerb\RedHerbFrontendModulesHook
 */
class RedHerbFrontendModulesHookTest extends TestCase {

	public function testAddsRedHerbModules(): void {
		$modules = [];
		$hook = new RedHerbFrontendModulesHook();

		$hook->onSWSGetFrontendModules(
			$modules,
			$this->createStub( OutputPage::class ),
			$this->createStub( Skin::class )
		);

		$this->assertSame(
			[ 'ext.redherb', 'ext.redherb-create-subject', 'ext.redherb-edit-main-subject' ],
			$modules
		);
	}

	public function testPreservesExistingModules(): void {
		$modules = [ 'ext.preexisting' ];
		$hook = new RedHerbFrontendModulesHook();

		$hook->onSWSGetFrontendModules(
			$modules,
			$this->createStub( OutputPage::class ),
			$this->createStub( Skin::class )
		);

		$this->assertSame(
			[ 'ext.preexisting', 'ext.redherb', 'ext.redherb-create-subject', 'ext.redherb-edit-main-subject' ],
			$modules
		);
	}

}
