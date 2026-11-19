<?php

declare(strict_types=1);
namespace AEWI\SWS\EntryPoints\Semantic;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\{Parser,ParserOutput};
use MediaWiki\User\User;
use AEWI\SWS\Application\Semantic\SmwMappingConfig;

/** Explicit server mappings override content-language message defaults; no Lua execution. */
final class WikiAnnotationConfig {
    public function read(?Parser $parser, ParserOutput $output, User $user): array {
        $settings = MediaWikiServices::getInstance()->getMainConfig()->get('WikiSchemaSmwMapping');
        // Reject malformed explicit settings before reading any message-dependent defaults.
        SmwMappingConfig::resolve($settings);
        $messages = new SmwMessageDefaults();
        $config = SmwMappingConfig::resolve($settings, $messages->read($settings));
        $config['messageDependencies'] = $messages->dependencies($settings, $config);
        return $config;
    }
}
