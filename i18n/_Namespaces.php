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
	SWSExtension::NS_SUBJECT => 'Subject',
	SWSExtension::NS_SUBJECT + 1 => 'Subject_talk',
];
