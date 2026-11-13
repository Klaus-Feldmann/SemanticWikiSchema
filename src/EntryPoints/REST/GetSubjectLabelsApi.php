<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\SWS\Application\SubjectLabelLookupResult;
use ProfessionalWiki\SWS\SWSExtension;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetSubjectLabelsApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function run(): Response {
		$params = $this->getValidatedParams();

        $extension = SWSExtension::getInstance();
        $filters = $params['schemaKey'] ?? null;
        if ($filters === null && $params['schema'] !== null) {
            $filters = [];
            foreach ($params['schema'] as $name) {
                try {
                    $reference = $extension->getSchemaReferenceParser()->localName($name);
                    $schema = $extension->getSchemaResolverFor($this->getAuthority())->getSchema($reference);
                } catch (\InvalidArgumentException) { continue; }
                if ($schema !== null) { $filters[] = $schema->getName()->getText(); }
            }
        }
        $results = $extension->getSubjectLabelLookup()->getSubjectLabelsMatching( $params['search'], $params['limit'], $filters );
        $ids = new \ProfessionalWiki\SWS\Domain\Subject\SubjectIdList( array_map( static fn ( $result ) => new \ProfessionalWiki\SWS\Domain\Subject\SubjectId( $result->id ), $results ) );
        $published = $extension->newIdentityPresentationLookup( $this->getAuthority() )->getSubjects( $ids );
        $subjects = array_map( static fn ( $result ) => \ProfessionalWiki\SWS\Presentation\IdentityLabelResult::serialize(
            $result, $published->getSubject( new \ProfessionalWiki\SWS\Domain\Subject\SubjectId( $result->id ) ), $params['language'] ?? null ), $results );

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $subjects ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'language' => [ self::PARAM_SOURCE=>'query', ParamValidator::PARAM_TYPE=>'string', ParamValidator::PARAM_REQUIRED=>false ],
            'search' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => '',
				self::PARAM_DESCRIPTION => 'Case-insensitive label prefix or entity-ID prefix; own display terms use the requested language.',
			],
			'schemaKey' => [ self::PARAM_SOURCE=>'query', ParamValidator::PARAM_TYPE=>'string', ParamValidator::PARAM_ISMULTI=>true, ParamValidator::PARAM_REQUIRED=>false, self::PARAM_DESCRIPTION=>'Explicit current schema page keys. Unlike the legacy schema parameter, these do not resolve old name aliases.' ],
            'schema' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Names of the Schemas to filter results by, separated by "|" (e.g. "Person|Group"). Case-sensitive. Only Subjects of these Schemas are returned. Omit it to search Subjects of every Schema.',
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				self::PARAM_DESCRIPTION => 'Maximum number of items to return.',
			],
		];
	}

}
