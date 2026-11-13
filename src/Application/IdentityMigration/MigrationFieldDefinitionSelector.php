<?php
declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

use ProfessionalWiki\SWS\Domain\Schema\PropertyNameRules;

/** Deterministic reuse of an unambiguous functional majority, otherwise the agreed text placeholder. */
final class MigrationFieldDefinitionSelector {
    public static function select(string $field,array $schemas,string $placeholder):array {
        PropertyNameRules::assertValid($field);$groups=[];$count=0;
        uksort($schemas,static fn($a,$b)=>strnatcmp($a,$b));
        foreach($schemas as$id=>$schema){
            $definition=$schema->propertyDefinitions->{$field}??null;
            if(!$definition instanceof \stdClass||($definition->type??null)==='wikitext'){continue;}
            $signature=ReferenceRewriter::fingerprint(ReferenceRewriter::encode((object)[
                'type'=>$definition->type??null,'externalProperty'=>$definition->externalProperty??null,
                'multiple'=>$definition->multiple??false,'targetSchema'=>$definition->targetSchema??null,
                'targetSchemas'=>$definition->targetSchemas??null,'options'=>$definition->options??null,
                'pageNamespace'=>$definition->pageNamespace??null,'assignCategories'=>$definition->assignCategories??false,
            ]));
            $groups[$signature]??=[];$groups[$signature][]=[$id,$definition];$count++;
        }
        foreach($groups as$group){
            if(count($group)>$count/2){
                [$source,$definition]=$group[0];
                return ['definition'=>ReferenceRewriter::object(ReferenceRewriter::encode($definition)),
                    'sourceSchema'=>$source,'needsReview'=>false,'selection'=>'functional-majority',
                    'matchingCandidates'=>count($group),'candidates'=>$count];
            }
        }
        return ['definition'=>(object)['type'=>'text','required'=>false,'multiple'=>false,'uniqueItems'=>false,'default'=>[$placeholder]],
            'sourceSchema'=>null,'needsReview'=>true,'selection'=>'text-placeholder','matchingCandidates'=>0,'candidates'=>$count];
    }
}
