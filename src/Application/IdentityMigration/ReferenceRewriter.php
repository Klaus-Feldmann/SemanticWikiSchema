<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdParser;
use stdClass;

/** Rewrites only declared identity/reference positions, never arbitrary research text or source values. */
final readonly class ReferenceRewriter {
    public function __construct(
        private array $schemas,
        private array $entities,
        private string $localSource,
        private string $language,
        private \Closure $normalizeName,
    ) {}

    public function schemaReference( mixed $value ): mixed {
        if ( is_object($value) && isset($value->id) ) { return $value; }
        if ( is_object($value) && ($value->source ?? null) !== $this->localSource ) { return $value; }
        $name = is_string($value) ? $value : ($value->name ?? null);
        if ( !is_string($name) ) { throw new InvalidArgumentException('Invalid schema reference'); }
        $key = ($this->normalizeName)($name);
        if ( !isset($this->schemas[$key]) ) { throw new InvalidArgumentException('Unresolved local schema: ' . $name); }
        return (object)['id'=>$this->schemas[$key]];
    }

    public function entityReference( string $text ): string {
        $id = (new SubjectIdParser($this->localSource))->parseOrThrow($text);
        return $id->isLocal() ? ($this->entities[$id->text] ?? $id->text) : $id->text;
    }

    public function schema( string $json, string $name, string $id ): string {
        $data = self::object($json);
        $data->formatVersion=2; $data->id=$id;
        $this->identity($data,$name);
        foreach ( $data->propertyDefinitions ?? [] as $property ) {
            if ( !is_object($property) || ($property->type ?? null) !== 'relation' ) { continue; }
            if ( isset($property->targetSchemas) ) {
                $property->targetSchemas=array_map($this->schemaReference(...),$property->targetSchemas);
            } elseif ( isset($property->targetSchema) ) {
                $property->targetSchema=$this->schemaReference($property->targetSchema);
            }
            if ( isset($property->default) ) { $this->relations($property->default); }
        }
        return self::encode($data);
    }

    public function subjects( string $json, string $pageName ): string {
        $data=self::object($json);
        if ( !is_object($data->subjects ?? null) ) { throw new InvalidArgumentException('Missing subject map'); }
        $new=new stdClass();
        foreach ( $data->subjects as $oldId=>$subject ) {
            if ( !is_object($subject) ) { throw new InvalidArgumentException('Invalid subject'); }
            $id=$this->entityReference($oldId);
            if ( isset($new->$id) ) { throw new InvalidArgumentException('Duplicate entity identity'); }
            $legacySchema=is_string($subject->schema ?? null) ? $subject->schema : ($subject->schema->name ?? $subject->schema->id ?? 'Entity');
            $subject->schema=$this->schemaReference($subject->schema);
            $isMain=($data->mainSubject ?? null)===$oldId;
            $ownPage=preg_match('/(?:^|:)'.preg_quote(ucfirst($oldId),'/').'$/i',$pageName)===1;
            $fallback=$isMain && !$ownPage ? $pageName : $legacySchema;
            $this->identity($subject,$fallback);
            foreach ( $subject->statements ?? [] as $statement ) {
                if ( is_object($statement) && ($statement->propertyType ?? null)==='relation' && isset($statement->value) ) { $this->relations($statement->value); }
            }
            if ( isset($subject->sourceSnapshot) ) { $subject->sourceSnapshot->subjectId=$id; }
            $new->$id=$subject;
        }
        if ( isset($data->mainSubject) ) { $data->mainSubject=$this->entityReference($data->mainSubject); }
        $data->subjects=$new; $data->formatVersion=2;
        unset($data->sourceRefresh);
        return self::encode($data);
    }

    private function identity( stdClass $data, string $fallback ): void {
        if ( !isset($data->identityLanguage) ) { $data->identityLanguage=$this->language; }
        if ( !isset($data->labels) ) {
            $label=is_string($data->label ?? null) && trim($data->label)!=='' ? $data->label : $fallback;
            $data->labels=(object)[$data->identityLanguage=>$label];
        }
        if ( !isset($data->descriptions) ) {
            $description=is_string($data->description ?? null) ? $data->description : '';
            $data->descriptions=$description==='' ? new stdClass() : (object)[$data->identityLanguage=>$description];
        }
    }

    private function relations( mixed &$values ): void {
        if ( !is_array($values) ) { throw new InvalidArgumentException('Relation values must be a list'); }
        foreach ($values as $value) {
            if (!is_object($value)||!is_string($value->target ?? null)) { throw new InvalidArgumentException('Invalid relation target'); }
            $value->target=$this->entityReference($value->target);
        }
    }

    public function mapping( string $json ): string {
        $data=self::object($json); $ids=$data->schemaIds ?? new stdClass();
        foreach ($data->schemas ?? [] as $name=>$definition) {
            $key=($this->normalizeName)($name);
            if (!isset($this->schemas[$key])) { continue; }
            $id=$this->schemas[$key];
            if (isset($ids->$id) && !self::equivalent(self::encode($ids->$id),self::encode($definition))) { throw new InvalidArgumentException('Conflicting mapping for '.$id); }
            $ids->$id=$definition; unset($data->schemas->$name);
        }
        $data->schemaIds=$ids;
        return self::encode($data);
    }

    public function layout( string $json ): string {
        $data=self::object($json);$data->schema=$this->schemaReference($data->schema);
        return self::encode($data);
    }

    public static function object(string $json):stdClass {
        $data=json_decode($json,false,512,JSON_THROW_ON_ERROR);
        if (!$data instanceof stdClass) { throw new InvalidArgumentException('Expected a JSON object'); }
        return $data;
    }
    public static function encode(mixed $data):string { return json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    public static function equivalent(string $a,string $b):bool { return self::canonical(json_decode($a,false,512,JSON_THROW_ON_ERROR))===self::canonical(json_decode($b,false,512,JSON_THROW_ON_ERROR)); }
    public static function fingerprint(string $json):string { return hash('sha256',self::encode(self::canonical(json_decode($json,false,512,JSON_THROW_ON_ERROR)))); }
    private static function canonical(mixed $value):mixed {
        if (is_object($value)) { $map=get_object_vars($value);ksort($map);return ['@object'=>array_map(self::canonical(...),$map)]; }
        if (is_array($value)) { return array_map(self::canonical(...),$value); }
        return $value;
    }
}
