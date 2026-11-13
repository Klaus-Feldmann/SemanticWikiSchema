<?php
/** Namespace metadata is evaluated as PHP; this does not simulate a running MediaWiki router. */
declare( strict_types = 1 );
require_once __DIR__ . '/../src/SWSExtension.php';
$checks = 0;
function verifyEntityNamespace( bool $ok, string $message ): void {
 global $checks;
 if ( !$ok ) { throw new RuntimeException( $message ); }
 $checks++;
}
require __DIR__ . '/../i18n/_Namespaces.php';
verifyEntityNamespace( $namespaceNames['en'][7480] === 'Entity', 'Canonical English entity namespace' );
verifyEntityNamespace( $namespaceNames['de'][7480] === 'Entität', 'German entity namespace' );
verifyEntityNamespace( $namespaceNames['en'][7481] === 'Entity_talk', 'English talk namespace' );
verifyEntityNamespace( $namespaceNames['de'][7481] === 'Entität_Diskussion', 'German talk namespace' );
foreach ( ['en','de'] as $language ) {
 verifyEntityNamespace( $namespaceAliases[$language]['Subject'] === 7480, 'Subject alias preserved' );
 verifyEntityNamespace( $namespaceAliases[$language]['Subjekt'] === 7480, 'Subjekt alias preserved' );
}
require __DIR__ . '/../i18n/_Aliases.php';
verifyEntityNamespace( $specialPageAliases['en']['Subject'] === ['Entity','Subject'], 'English access names' );
verifyEntityNamespace( $specialPageAliases['de']['Subject'] === ['Entität','Subjekt'], 'German access names' );
verifyEntityNamespace( in_array( 'Subjects', $specialPageAliases['en']['Subjects'], true ), 'Legacy list access preserved' );
$extension = json_decode( file_get_contents( __DIR__ . '/../extension.json' ), true, 512, JSON_THROW_ON_ERROR );
$namespaces = array_column( $extension['namespaces'], null, 'id' );
verifyEntityNamespace( $namespaces[7480]['name'] === 'Entity' && $namespaces[7480]['content'], 'Manifest keeps namespace ID and content flag' );
verifyEntityNamespace( $extension['config']['SWSSubjectPageNamespace']['value'] === 7480, 'Standalone pages still default to namespace 7480' );
foreach ( ['de','en','qqq'] as $language ) {
 $messages = json_decode( file_get_contents( __DIR__ . "/../i18n/$language.json" ), true, 512, JSON_THROW_ON_ERROR );
 foreach ( ['sws-identity-terms','sws-identity-label','sws-identity-description','sws-identity-language','sws-identity-add-language'] as $key ) {
  verifyEntityNamespace( isset( $messages[$key] ), 'Own metadata message exists: ' . $key );
 }
}
echo "$checks namespace/alias/identity-message checks passed (configuration evaluation; live MediaWiki routing not tested).\n";
