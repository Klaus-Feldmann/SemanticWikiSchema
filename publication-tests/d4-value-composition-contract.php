<?php
require __DIR__.'/d2-query-syntax-contract.php';
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Subject\{Subject,StatementList};
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\{NeoValue,StringValue};
use ProfessionalWiki\SWS\EntryPoints\{ParserFieldLookup,SWSValueParserFunction};
\ProfessionalWiki\SWS\SWSExtension::$schema = new \ProfessionalWiki\SWS\Domain\Schema\Schema(
 new \ProfessionalWiki\SWS\Domain\Schema\SchemaName('Person'), '',
 new \ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions(['F4'=>$props['F12']]), false );
$compositionChecks=0;
function vcheck($ok,$why){global $compositionChecks;$compositionChecks++;if(!$ok)throw new RuntimeException($why);}
$values=['427 v. Chr.','Müller (Person)','A & B','A|B','<script>alert(1)</script>',
 '[[Kategorie:SWS-D4-Nicht-setzen]]','{{SWS-D4-Nicht-ausführen}}','{{#expr:99*99}}',
 "''kursiv''",'__NOTOC__',"\n* Liste\n{| class=\"wikitable\"\n|A\n|}",
 '&#91;&#91;Kategorie:Doppelt&#93;&#93;','https://example.org/?a=1&b=2','0',''];
foreach($values as $i=>$raw){
 $target=$entity->withStatements(new StatementList([new Statement(new PropertyName('F4'),'text',new StringValue($raw))]));setStorePage($target,300+$i);
 $resolver=new class($target) extends \ProfessionalWiki\SWS\Application\SubjectResolver {
  public function __construct(private Subject $target){}
  public function resolveById(string $id): ?Subject{return $id==='E3'?$this->target:null;}
  public function resolveMainByTitle(Title $title): ?Subject{return $this->target;}
  public function resolveDisplayValue(Subject $subject,Statement $statement): NeoValue{return $statement->getValue();}
 };
 $parser=new Parser();$pf=new SWSValueParserFunction($resolver,ParserFieldLookup::create($parser));
 foreach([[],['origin=own']]as $options){
  $result=$pf->handle($parser,'E3','F4',...$options);
  if($result===''){vcheck($raw==='','Only empty scalar may return empty');continue;}
  vcheck($result[0]===wfEscapeWikiText($raw),'Exact target-core encoding: '.json_encode($raw));
  vcheck($result['noparse']===true&&$result['isHTML']===false,'No HTML strip marker, no template re-expansion');
 }
}
// The caller may supply structural link brackets. Stored structural brackets are encoded.
$encoded=wfEscapeWikiText('[[Kategorie:Injected]] {{Injected}} <b>X</b>');
vcheck(!str_contains($encoded,'[[')&&!str_contains($encoded,'{{')&&!str_contains($encoded,'<b>'),'Field content remains data');
vcheck(wfEscapeWikiText('427 v. Chr.')==='427 v. Chr.','Ordinary title usable without strip markers');
vcheck(html_entity_decode(wfEscapeWikiText('A & B'),ENT_QUOTES|ENT_HTML5)==='A & B','Special characters retain their visible value');
echo "$compositionChecks D4 actual parser-function return/own/effective/encoding checks passed (controlled host).\n";
