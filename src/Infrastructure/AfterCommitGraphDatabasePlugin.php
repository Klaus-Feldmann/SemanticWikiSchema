<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Infrastructure;

use Closure;
use ProfessionalWiki\SWS\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\SWS\Domain\Page\{Page, PageId, PageSubjects};
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;

/** External stores must never observe IDs from a transaction which can still be aborted. */
final readonly class AfterCommitGraphDatabasePlugin implements GraphDatabasePlugin {
	/** @param Closure(Closure):void $afterCommit */
	public function __construct( private GraphDatabasePlugin $plugin, private Closure $afterCommit ) {}

	public function initialize(): void { $this->plugin->initialize(); }

	public function savePage( Page $page ): void {
		$copies = new SubjectMap();
		foreach ( $page->getSubjects()->getAllSubjects()->asArray() as $subject ) {
			$copies->addOrUpdateSubject( $subject->withStatements( $subject->getStatements() ) );
		}
		$main = $page->getSubjects()->getMainSubject();
		$frozen = new Page( $page->getId(), $page->getProperties(), new PageSubjects(
			$main === null ? null : $copies->getSubject( $main->id ), $main === null ? $copies : $copies->without( $main->id )
		) );
		( $this->afterCommit )( fn () => $this->plugin->savePage( $frozen ) );
	}

	public function deletePage( PageId $pageId ): void {
		( $this->afterCommit )( fn () => $this->plugin->deletePage( $pageId ) );
	}
}
