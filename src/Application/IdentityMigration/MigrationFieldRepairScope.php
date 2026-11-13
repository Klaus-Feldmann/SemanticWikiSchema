<?php
declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

/** Observes missing local fields only inside a maintenance-controlled projection attempt. */
final class MigrationFieldRepairScope {
    private static ?array $missing=null;
    public static function record(string $schemaId,string $field,string $page,string $caller):void {
        if(self::$missing!==null){self::$missing[$schemaId."\0".$field]=compact('schemaId','field','page','caller');}
    }
    public static function attempt(\Closure $work):array {
        if(self::$missing!==null){throw new \LogicException('Nested migration field repair');}
        self::$missing=[];
        try { $result=$work();return ['result'=>$result,'missing'=>array_values(self::$missing),'error'=>null]; }
        catch(\Throwable $error){return ['result'=>null,'missing'=>array_values(self::$missing),'error'=>$error];}
        finally {self::$missing=null;}
    }
}
