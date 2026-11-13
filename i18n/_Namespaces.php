<?php

use ProfessionalWiki\SWS\SWSExtension;

$namespaceNames = [];

$namespaceNames['en'] = [
	SWSExtension::NS_SCHEMA => 'Schema',
	SWSExtension::NS_SCHEMA + 1 => 'Schema_talk',
	SWSExtension::NS_LAYOUT => 'Layout',
	SWSExtension::NS_LAYOUT + 1 => 'Layout_talk',
	SWSExtension::NS_MAPPING => 'Mapping',
	SWSExtension::NS_MAPPING + 1 => 'Mapping_talk',
	SWSExtension::NS_SUBJECT => 'Entity',
	SWSExtension::NS_SUBJECT + 1 => 'Entity_talk',
];

$namespaceNames['de'] = [
 SWSExtension::NS_SUBJECT => 'Entität',
 SWSExtension::NS_SUBJECT + 1 => 'Entität_Diskussion',
];
$namespaceAliases = [];
$namespaceAliases['en'] = [ 'Subject' => SWSExtension::NS_SUBJECT, 'Subject_talk' => SWSExtension::NS_SUBJECT + 1,
 'Subjekt' => SWSExtension::NS_SUBJECT, 'Subjekt_Diskussion' => SWSExtension::NS_SUBJECT + 1 ];
$namespaceAliases['de'] = $namespaceAliases['en'] + [ 'Entity' => SWSExtension::NS_SUBJECT, 'Entity_talk' => SWSExtension::NS_SUBJECT + 1 ];
