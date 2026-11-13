<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\HttpException;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\Identity\SchemaIdentityPreparation;
use ProfessionalWiki\SWS\SWSExtension;

/** POST creates a new identity even when another Schema already has the same label. */
class CreateSchemaApi extends SaveSchemaApi {
	private ?array $prepared = null;
    private ?string $existingJson = null;

	public function execute(): \MediaWiki\Rest\Response {
		try { return parent::execute(); }
		catch ( \InvalidArgumentException | \JsonException $error ) {
			return $this->getResponseFactory()->createHttpError( 400, [ 'message'=>$error->getMessage() ] );
		}
	}

	protected function newSchemaPage(): ?Title {
		if ( !$this->getAuthority()->isAllowed( 'sws-schema-edit' ) || !$this->getAuthority()->isAllowed( 'createpage' ) ) {
			throw new HttpException( 'Schema creation permission required', 403 );
		}
		$extension = SWSExtension::getInstance();
		$this->prepared = ( new SchemaIdentityPreparation( $extension->newIdentityRegistry(), $extension->getIdentityOwner( $this->getAuthority() ),
			MediaWikiServices::getInstance()->getContentLanguage()->getCode() ) )->create(
			parent::getSchemaJson(), $this->getValidatedBody()['requestKey'] ?? bin2hex( random_bytes( 16 ) )
		);
		$title = Title::makeTitleSafe( SWSExtension::NS_SCHEMA, $this->prepared['id']->text );
		if ( $title !== null && !$extension->newPageReadAuthorizer( $this->getAuthority() )->authorizeReadByPageTitle( $title ) ) { throw new HttpException( 'Schema not found', 404 ); }
		if ( $title !== null && $title->exists() ) {
			$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
            if ($revision === null || !$revision->userCan(\MediaWiki\Revision\RevisionRecord::DELETED_TEXT,$this->getAuthority()) || !$extension->getRevisionPolicy()->revisionIsReadableBy($revision,$this->getAuthority())) {
                throw new HttpException('Schema not found',404);
            }
            $content=$revision->getContent('main',\MediaWiki\Revision\RevisionRecord::FOR_THIS_USER,$this->getAuthority());
            if (!$content instanceof \ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent) { throw new HttpException('Schema content model changed; no page was replaced',409); }
            $this->existingJson = $content->getText();
            $data = json_decode( $this->existingJson ?? '{}', true );
			if ( ( $data['formatVersion'] ?? 1 ) !== 2 || ( $data['id'] ?? null ) !== $this->prepared['id']->text ) {
				throw new HttpException( 'Reserved Schema title conflicts with a legacy page; no page was replaced', 409 );
			}
		}
		return $title;
	}

    protected function responseForExistingResource(): ?\MediaWiki\Rest\Response {
        if ($this->existingJson === null) { return null; }
        $extension=SWSExtension::getInstance();
        $schema=$extension->getPersistenceSchemaDeserializer()->deserialize(
            new \ProfessionalWiki\SWS\Domain\Schema\SchemaName($this->prepared['id']->text),$this->existingJson
        );
        return $this->getResponseFactory()->createJson(['schema'=>$extension->getSchemaPresentationSerializer()->toArray($schema)]);
    }

	protected function getSchemaJson(): string { return $this->prepared['json'] ?? parent::getSchemaJson(); }
	protected function getActionParameters(): array {
		return parent::getActionParameters() + ( $this->getSchemaPage()->exists() ? [] : [ 'createonly'=>'1' ] );
	}
	public function getParamSettings(): array { return []; }
	public function getBodyParamSettings(): array {
		return parent::getBodyParamSettings() + [ 'requestKey'=>[
			self::PARAM_SOURCE=>'body', \Wikimedia\ParamValidator\ParamValidator::PARAM_TYPE=>'string',
			\Wikimedia\ParamValidator\ParamValidator::PARAM_REQUIRED=>false,
		] ];
	}
}
