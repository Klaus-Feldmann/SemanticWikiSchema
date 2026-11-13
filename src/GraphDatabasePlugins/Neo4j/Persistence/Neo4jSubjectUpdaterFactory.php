<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use ProfessionalWiki\SWS\Application\Source\SchemaResolver;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use Psr\Log\LoggerInterface;

class Neo4jSubjectUpdaterFactory {

	public function __construct(
		private readonly SchemaResolver $schemaResolver,
		private readonly Neo4jValueBuilderRegistry $valueBuilderRegistry,
		private readonly LoggerInterface $logger,
		private readonly string $wikiId,
        private readonly ?\Closure $canonicalize = null,
	) {
	}

	public function newSubjectUpdater(
		TransactionInterface $transaction,
		PageId $pageId,
		Neo4jOrphanCandidates $orphanCandidates
	): Neo4jSubjectUpdater {
		return new Neo4jSubjectUpdater(
			$transaction,
			$pageId,
			$this->schemaResolver,
			$this->valueBuilderRegistry,
			$this->logger,
			$this->wikiId,
			$orphanCandidates, $this->canonicalize,
		);
	}

}
