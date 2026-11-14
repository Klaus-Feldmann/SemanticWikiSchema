<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Subject;

use ProfessionalWiki\SWS\Domain\Relation\TypedRelationList;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;

class Subject {

	public function __construct(
		public readonly SubjectId $id,
		public ?SubjectLabel $label,
		private readonly SchemaReference $schema,
		private StatementList $statements,
        private array $fieldNotes = [],
		private ?SourceSnapshot $sourceSnapshot = null,
		private ?\ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity $identity = null,
		private int $fieldKeyVersion = 0,
	) {
		$this->setSourceSnapshot( $sourceSnapshot );
		if ( $identity !== null ) { $this->setIdentity( $identity ); }
	}

	public static function newSubject( SubjectId $id, ?SubjectLabel $label, SchemaReference $schema ): self {
		return new self(
			id: $id,
			label: $label,
			schema: $schema,
			statements: new StatementList( [] ),
		);
	}

	public function hasSameIdentity( self $subject ): bool {
		return $this->id->equals( $subject->id );
	}

	public function getId(): SubjectId {
		return $this->id;
	}

	/**
	 * Null when nobody named this Subject. Use {@see SubjectDisplayName} to get something to show.
	 */
	public function getLabel(): ?SubjectLabel {
		return $this->label;
	}

	public function getSchemaReference(): SchemaReference {
		return $this->schema;
	}

	/**
	 * The Schema's name without its Source. Resolve a Schema through {@see self::getSchemaReference()};
	 * this is for the places that name a Schema locally, such as the graph's Schema node label.
	 */
	public function getSchemaName(): SchemaName {
		return $this->schema->name;
	}

	public function getStatements(): StatementList {
		return $this->statements;
	}

	public function getIdentity(): ?\ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity { return $this->identity; }
	public function setIdentity( \ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity $identity ): void {
		$this->identity = $identity;
		$this->label = SubjectLabel::fromText( $identity->labels[$identity->language] ?? null );
	}

	public function getFieldKeyVersion(): int { return $this->fieldKeyVersion; }
	public function setFieldKeyVersion( int $version ): void { if ( $version < 0 ) { throw new \InvalidArgumentException( "Invalid field-key version" ); } $this->fieldKeyVersion = $version; }

	public function getSourceSnapshot(): ?SourceSnapshot {
		return $this->sourceSnapshot;
	}

	/** Internal source processing only. Public edit requests never supply this value. */
	public function setSourceSnapshot( ?SourceSnapshot $snapshot ): void {
		if ( $snapshot !== null && $snapshot->getSubjectId() !== $this->id->text ) {
			throw new \InvalidArgumentException( 'Source snapshot belongs to another Subject' );
		}
		$this->sourceSnapshot = $snapshot;
	}

	public function getFieldNotes(): array { return $this->fieldNotes; }
    public function setFieldNotes( array $notes ): void {
        $this->fieldNotes = \ProfessionalWiki\SWS\Domain\FieldNotes::validate( $notes );
    }

    public function getTypedRelations(): TypedRelationList {
		return $this->statements->getTypedRelations();
	}

	public function getReferencedSubjects(): SubjectIdList {
		return $this->statements->getReferencedSubjects();
	}

	public function setLabel( ?SubjectLabel $newLabel ): void {
		$this->label = $newLabel;
		if ( $this->identity !== null ) { $this->identity = $this->identity->withDefaultLabel( $newLabel?->text ); }
	}

	public function setStatements( StatementList $statements ): void {
		$this->statements = $statements;
	}

	public function withStatements( StatementList $statements ): self {
		return new self(
			id: $this->id,
			label: $this->label,
			schema: $this->schema,
			statements: $statements,
            fieldNotes: $this->fieldNotes,
			sourceSnapshot: $this->sourceSnapshot,
			fieldKeyVersion: $this->fieldKeyVersion,
			identity: $this->identity,
		);
	}

}
