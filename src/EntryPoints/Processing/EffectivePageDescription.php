<?php

declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints\Processing;

use MediaWiki\Context\{DerivativeContext,RequestContext};
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use AEWI\SWS\Application\Source\SourceDataMode;

/** Publishes one ranked description from the completed parse. Search only reads this projection. */
final class EffectivePageDescription {
 public const PROPERTY = 'sws-description';
 public const ORIGIN = 'sws-description-source';

 /** Lazy source read: SHORTDESC avoids any further lookup. No SMW reads and no recursive parse. */
 public static function choose( mixed $shortdesc, \Closure $identities ): ?array {
  if ( is_string($shortdesc) && trim($shortdesc) !== '' ) {
   return ['text'=>trim($shortdesc),'source'=>'shortdesc'];
  }
  $values=$identities();
  foreach ( ['wikibase-local','wikidata'] as $source ) {
   $item=$values[$source] ?? [];
   if ( ($item['status'] ?? '') !== 'resolved' ) { continue; }
   $description=$item['description'] ?? null;
   $text=is_array($description) ? ($description['value'] ?? null) : $description;
   if ( is_string($text) && trim($text) !== '' ) { return ['text'=>trim($text),'source'=>$source]; }
  }
  return null;
 }

 public function apply( Title $title, ParserOutput $output, User $user ): void {
  $shortdesc=$output->getPageProperty('shortdesc');
  $selected=self::choose($shortdesc,static function()use($title,$user):array {
   if (!class_exists(\SWSBaseBridge\DataViewLookup::class)) { return []; }
   try {
    $context=new DerivativeContext(RequestContext::getMain());$context->setUser($user);
    $language=MediaWikiServices::getInstance()->getContentLanguage()->getCode();
    // Store mode uses the current authorized processing overlay. Cache mode keeps its usual
    // cached source reads, during processing only; no source access occurs while typing.
    return SourceDataMode::usesStore()
     ? (new \SWSBaseBridge\StoredSourceLookup($context))->identities($title,$language)
     : (new \SWSBaseBridge\DataViewLookup($context))->readIdentities($title,$language);
   } catch (\Throwable $e) {
    if (class_exists(\MediaWiki\Logger\LoggerFactory::class)) {
     \MediaWiki\Logger\LoggerFactory::getInstance('SemanticWikiSchema')->warning('Description projection unavailable: {error}',['error'=>$e->getMessage()]);
    }
    return [];
   }
  });
  if ($selected !== null) {
   $output->setPageProperty(self::PROPERTY,$selected['text']);
   $output->setPageProperty(self::ORIGIN,$selected['source']);
  }
 }
}
