<?php
declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

/** Recognizes only the old receipt writer's conversion of empty objects to empty arrays. */
final class MigrationJsonShapeRepair {
    public static function matches(string $expected,string $actual):bool {
        $left=ReferenceRewriter::object($expected);$right=ReferenceRewriter::object($actual);
        unset($left->sourceRefresh,$right->sourceRefresh);$converted=0;
        return self::sameExceptEmptyObjects($left,$right,$converted)&&$converted>0;
    }
    private static function sameExceptEmptyObjects(mixed $left,mixed $right,int &$converted):bool {
        if($left instanceof \stdClass&&get_object_vars($left)===[]&&$right===[]){$converted++;return true;}
        if($left instanceof \stdClass&&$right instanceof \stdClass){
            $left=get_object_vars($left);$right=get_object_vars($right);ksort($left);ksort($right);
        }elseif(!is_array($left)||!is_array($right)){return $left===$right;}
        if(array_keys($left)!==array_keys($right)){return false;}
        foreach($left as$key=>$value){if(!self::sameExceptEmptyObjects($value,$right[$key],$converted)){return false;}}
        return true;
    }
}
