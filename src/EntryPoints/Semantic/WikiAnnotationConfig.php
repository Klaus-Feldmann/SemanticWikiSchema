<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\EntryPoints\Semantic;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\{Parser,ParserOutput};
use MediaWiki\User\User;
use ProfessionalWiki\SWS\Application\Semantic\SmwMappingConfig;

/** Server settings only: no wiki module execution or configuration-page dependency. */
final class WikiAnnotationConfig {
    public function read(?Parser $parser, ParserOutput $output, User $user): array {
        return SmwMappingConfig::resolve(
            MediaWikiServices::getInstance()->getMainConfig()->get('WikiSchemaSmwMapping')
        );
    }
}
