<?php
// Run in a separate PHP process for each extension tree; no MediaWiki storage or network.
$root = $argv[1] ?? dirname(__DIR__);
$namespace = array_key_first(json_decode(file_get_contents($root.'/composer.json'), true)['autoload']['psr-4']);
$class = static fn(string $suffix): string => $namespace.$suffix;
ob_start(); require $root.'/publication-tests/source-snapshot-contract.php'; ob_end_clean();
$fixture = $json; // Actual legacy storage with source snapshots and author notes from the contract.
$subjectWriterClass=$class('Persistence\\MediaWiki\\Subject\\SubjectContentDataSerializer');
$statementReaderClass=$class('Persistence\\MediaWiki\\Subject\\StatementDeserializer');
$subjectReaderClass=$class('Persistence\\MediaWiki\\Subject\\SubjectContentDataDeserializer');
$testSources=$class('Tests\\Data\\TestSources');$testIds=$class('Tests\\Data\\TestSubjectIds');
$registry=$testSources::newPropertyTypeRegistry();
$reader=new $subjectReaderClass(new $statementReaderClass($registry,$testIds::newParser()),new Psr\Log\NullLogger(),$testSources::newSchemaReferenceParser());
$writer=new $subjectWriterClass();
$input=['formatVersion'=>2,'mainSubject'=>'E42','subjects'=>['E42'=>[
 'identityLanguage'=>'de','labels'=>['de'=>'Vorlagenkatalog','en'=>'Template catalogue'],'descriptions'=>['de'=>'Bestehender Datensatz'],
 'schema'=>['id'=>'S11'],'fieldKeyVersion'=>1,'fieldNotes'=>['FK2'=>'Eigene Forschungsnotiz'],
 'statements'=>[
  'FK2'=>['propertyType'=>'text','value'=>['ProfessionalWiki\\SWS im eigenen Feldwert bleibt unverändert.']],
  'FU1'=>['propertyType'=>'url','value'=>[['url'=>'https://de.wikipedia.org/wiki/Vorlage:Test','label'=>'Test','caption'=>'Wikipedia (de)']]],
 ]]]];
$read=$reader->deserialize(json_encode($input,JSON_THROW_ON_ERROR));
$entityJson=$writer->serialize($read);
if ($read->getMainSubject()?->id->text!=='E42'||!str_contains($entityJson,'E42')||!str_contains($entityJson,'S11')) { throw new RuntimeException('Identity lost'); }
$schemaInput=['formatVersion'=>2,'id'=>'S11','identityLanguage'=>'de','labels'=>['de'=>'Vorlage'],'descriptions'=>['de'=>'Vorlagenkatalog'],
 'propertyDefinitions'=>['FK2'=>['type'=>'text','labels'=>['de'=>'Kurzbeschreibung']],'FU1'=>['type'=>'url','labels'=>['de'=>'URL']]]];
$schemaReaderClass=$class('Persistence\\MediaWiki\\SchemaPersistenceDeserializer');$schemaNameClass=$class('Domain\\Schema\\SchemaName');
$schemaWriterClass=$class('Presentation\\SchemaPresentationSerializer');
$schema=(new $schemaReaderClass($registry))->deserialize(new $schemaNameClass('S11'),json_encode($schemaInput,JSON_THROW_ON_ERROR));
$schemaJson=(new $schemaWriterClass())->serialize($schema);
if ($schema->getId()?->text!=='S11') { throw new RuntimeException('Schema identity lost'); }
$source=(new $subjectWriterClass())->serialize($reader->deserialize($fixture));
$result=['legacyAndSnapshotJson'=>$source,'entityJson'=>$entityJson,'schemaJson'=>$schemaJson];
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
