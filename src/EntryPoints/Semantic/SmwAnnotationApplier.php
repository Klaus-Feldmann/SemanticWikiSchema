<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\EntryPoints\Semantic;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\{Parser,ParserOptions,ParserOutput};
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\Semantic\AnnotationPlan;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIdentity;
use SMW\DataModel\SemanticData;
use SMW\ParserData;
use SMW\ParserParameterProcessor;

/** Use SMW's existing structured handlers, without Lua, Wikitext expansion or parser hook dispatch.
 * This retains #set/#subobject parameter semantics, warnings and metadata, including custom names.
 */
final class SmwAnnotationApplier {
    public function apply(Title $title,ParserOutput $output,AnnotationPlan $plan): SemanticData {
        $existing=new ParserData($title,$output);
        if(!$existing->canUse()) { return $existing->getSemanticData(); }
        $services=MediaWikiServices::getInstance();
        $stage=$services->getParserFactory()->create();
        $stage->startExternalParse($title,ParserOptions::newFromUser(ProcessingIdentity::get()),Parser::OT_HTML);
        $factory=$services->getService('SMW.ParserFunctionFactory');
        foreach($plan->rows as $row) {
            $factory->newSetParserFunction($stage)->parse(new ParserParameterProcessor([$row]));
        }
        foreach($plan->objects as $args) {
            $factory->newSubobjectParserFunction($stage)->parse(new ParserParameterProcessor($args));
        }
        $staged=(new ParserData($title,$stage->getOutput()))->getSemanticData();
        $data=$existing->getSemanticData();
        $data->importDataFrom($staged);
        $output->setExtensionData('smwdata',$data);
        return $data;
    }
}
