<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\Content;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\SWS\Domain\Schema\DependentSubjectSchemaRules;
use ProfessionalWiki\SWS\Domain\Schema\PropertyNameRules;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaContentValidator;
use ProfessionalWiki\SWS\Presentation\PropertyNameProblemMessage;
use StatusValue;

class SchemaContentHandler extends JsonContentHandler {

	use ReportsValidationErrors;

	protected function getContentClass(): string {
		return SchemaContent::class;
	}

	public function validateSave( Content $content, ValidationParams $validationParams ): StatusValue {
		$status = parent::validateSave( $content, $validationParams );

		if ( !$status->isOK() ) {
			return $status;
		}

		$title = Title::newFromPageIdentity( $validationParams->getPageIdentity() );

		try {
			$schemaName = new SchemaName( $title->getText() );
		} catch ( InvalidArgumentException $exception ) {
			$schemaName = null;
			$status->fatal( 'sws-schema-name-invalid', $exception->getMessage() );
		}

		$validator = SchemaContentValidator::newInstance();

		if ( !$validator->validate( $content->getText() ) ) {
			$this->reportValidationErrors(
				$status,
				'sws-schema-invalid',
				'sws-schema-invalid-detail',
				$validator->getErrors()
			);
		}

		if ( $status->isOK() ) {
			try {
				$new = json_decode( $content->getText(), true, 512, JSON_THROW_ON_ERROR );
				$oldRevision = \MediaWiki\MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
				$old = json_decode( $oldRevision?->getContent( 'main' )?->getText() ?? '{}', true );
				\ProfessionalWiki\SWS\Application\Identity\SchemaIdentityWritePolicy::validate(
					$schemaName, $oldRevision === null ? null : ( is_array( $old ) ? $old : [] ), $new,
					SWSExtension::getInstance()->newIdentityRegistry(), SWSExtension::getInstance()->getIdentityOwner()
				);
			} catch ( \Throwable $error ) { $status->fatal( 'sws-identity-invalid', $error->getMessage() ); }
		}

		if ( $status->isOK() ) {
			$this->checkPropertyNames( $status, $content->getText() );
		}

		if ( $schemaName !== null && $status->isOK() ) {
			try {
				$this->checkDependentSubjectRules( $status,
					SWSExtension::getInstance()->getPersistenceSchemaDeserializer()->deserialize( $schemaName, $content->getText() ) );
			} catch ( InvalidArgumentException $error ) { $status->fatal( 'sws-identity-invalid', $error->getMessage() ); }
		}

        if ($status->isOK() && class_exists(\SWSBaseBridge\SchemaPropertyBindings::class)) {
            try {
                $old=\MediaWiki\MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle($title);
                $oldContent=$old?->getContent('main');
                $previous=$oldContent instanceof SchemaContent ? json_decode($oldContent->getText(),true) : [];
                $previous=\ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::originalSchema($content->getText()) ?? $previous;
                \SWSBaseBridge\SchemaPropertyBindings::validate(json_decode($content->getText(),true),$previous ?? [],\MediaWiki\Context\RequestContext::getMain());
            } catch (\Throwable $e) {
                $status->fatal('sws-property-datatype-invalid',$e->getMessage());
            }
        }
        return $status;
    }

    /**
	 * Read from the JSON rather than from the Schema it holds, which leaves out integer names.
	 */
	private function checkPropertyNames( StatusValue $status, string $json ): void {
		/** @var array{propertyDefinitions: array<array-key, mixed>} $schema */
		$schema = json_decode( $json, true );
		$problems = PropertyNameRules::problemsInSchema(
			array_map( strval( ... ), array_keys( $schema['propertyDefinitions'] ) )
		);

		if ( $problems !== [] ) {
			$this->reportErrors(
				$status,
				'sws-schema-invalid',
				array_map( PropertyNameProblemMessage::from( ... ), $problems )
			);
		}
	}

	private function checkDependentSubjectRules( StatusValue $status, Schema $schema ): void {
		$rules = new DependentSubjectSchemaRules( SWSExtension::getInstance()->getSchemaResolver() );

		if ( $rules->changesDeclaration( $schema ) ) {
			$status->fatal( 'sws-schema-dependence-unchangeable' );
			return;
		}

		$mixingProperties = $rules->propertiesMixingTargetSchemas( $schema );

		if ( $mixingProperties !== [] ) {
			$status->fatal(
				'sws-schema-relation-targets-mixed',
				Message::listParam( $mixingProperties ),
				count( $mixingProperties )
			);
		}
	}

	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		$parserOutput->setContentHolderText( '' );
	}

	public function makeEmptyContent(): SchemaContent {
		return new SchemaContent( <<<JSON
{
	"propertyDefinitions": {

	}
}
JSON
		);
	}

	public function canBeUsedOn( Title $title ): bool {
		return $title->getNamespace() === SWSExtension::NS_SCHEMA;
	}

}
