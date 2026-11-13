<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetSubject;

use ProfessionalWiki\SWS\Application\HostSubject;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;

readonly class GetSubjectResponseItem {

	public function __construct(
		public string $id,
		/**
		 * The stored label, absent on a Subject that has none. Read $displayName to show a Subject.
		 */
		public ?string $label,
		public string $displayName,
		/**
		 * Whether $displayName fell back to the Schema name, which is the one name nobody chose. A
		 * client cannot derive it by comparing $displayName with the Schema name: a Main Subject on a
		 * page titled after its Schema matches too, and that name was chosen.
		 */
		public bool $displayNameIsGenerated,
		/**
		 * The Schema reference in its stored shape: the bare name for a Schema of this wiki, a
		 * {source, name} object for one from elsewhere, so read and write name a Schema alike.
		 *
		 * @var string|array{id: string, source?: string}|array{source: string, name: string}
		 */
		public string|array $schema,
		/**
		 * @var array<string, mixed>
		 */
		public array $statements,
		public ?int $pageId,
		public ?string $pageTitle,
		public ?int $pageNamespaceId,
		/**
		 * Set only for a Dependent Subject nobody named, which is then named after its Host Subject rather than by
		 * $displayName.
		 */
		public ?HostSubject $hostSubject,
        public array $fieldNotes = [],
		public ?array $identity = null,
	) {
	}

	/**
	 * Null page identifiers leave the page fields unset, which is how a Subject whose hosting page
	 * cannot be resolved, or whose identifiers were not requested, is represented.
	 *
	 * The name someone chose is passed in rather than derived here, because only the caller holding
	 * the page knows whether the Subject is its Main Subject. Null means nobody chose one, which is
	 * what makes the name and the verdict on it one answer rather than two that could disagree. The
	 * Host Subject comes from the page too.
	 */
	public static function fromSubject(
		Subject $subject,
		?PageIdentifiers $pageIdentifiers,
		?string $chosenName,
		?HostSubject $hostSubject
	): self {
		return new self(
			id: $subject->id->text,
			label: $subject->getLabel()?->text,
			displayName: $chosenName ?? $subject->getSchemaReference()->getText(),
			displayNameIsGenerated: $chosenName === null,
			schema: $subject->getSchemaReference()->toJson(),
			statements: self::arrayifyStatements( $subject->getStatements() ),
			pageId: $pageIdentifiers?->getId()->id,
			pageTitle: $pageIdentifiers?->getTitle(),
			pageNamespaceId: $pageIdentifiers?->getNamespaceId(),
			hostSubject: $hostSubject,
            fieldNotes: $subject->getFieldNotes(),
			identity: $subject->getIdentity()?->toJson(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function arrayifyStatements( StatementList $statements ): array {
		$array = [];

		foreach ( $statements->asArray() as $statement ) {
			$array[$statement->getPropertyName()->text] = [
				'propertyType' => $statement->getPropertyType(),
				'value' => $statement->getValue()->toScalars()
			];
		}

		return $array;
	}

}
