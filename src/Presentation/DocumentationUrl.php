<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Presentation;

/**
 * Where SWS sends readers in its documentation. Every link from PHP into https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/README.md/ is one of
 * these, so each can be checked against the docs tree. Cases name the topic; their values say where it is covered.
 */
enum DocumentationUrl: string {
	case GettingStarted = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/guide/getting-started.md';
	case CreatingSchemas = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/guide/getting-started.md#_1-create-a-schema';
	case CreatingSubjects = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/guide/getting-started.md#_2-create-a-subject';
	case AuthoringMappings = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/guide/author-an-ontology-mapping.md';
	case Subjects = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/glossary.md#subject';
	case Layouts = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/glossary.md#layout';
	case MappingFormat = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/authoring/mapping-format.md';
	case SubjectFormat = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/api/subject-format.md';
	case SchemaFormat = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/api/schema-format.md';
	case OnWikiSettings = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/operations/installation.md#on-wiki-settings';
	case GraphStoreRebuilds = 'https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/master/docs/operations/maintenance.md#background-rebuilds';
}
