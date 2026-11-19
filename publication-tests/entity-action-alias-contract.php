<?php
// Exercise the real action class through both registered action names, without a MediaWiki installation.
namespace {
 class FormlessAction {}
 require __DIR__ . '/bootstrap.php';
 $manifest = json_decode( file_get_contents( dirname( __DIR__ ) . '/extension.json' ), true, 512, JSON_THROW_ON_ERROR );
 $checks = 0;
 foreach ( [ 'entities', 'subjects' ] as $name ) {
  $class = $manifest['Actions'][$name] ?? null;
  if ( $class !== \AEWI\SWS\EntryPoints\Actions\SubjectsAction::class ) { throw new \RuntimeException( 'Missing action registration: ' . $name ); }
  $action = new $class();
  if ( $action->getName() !== 'entities' || $action->getRestriction() !== 'read' || $action->requiresWrite() ) { throw new \RuntimeException( 'Action behavior changed: ' . $name ); }
  if ( !$class::isActionName( $name ) ) { throw new \RuntimeException( 'Data tab would not be selected: ' . $name ); }
  $checks++;
 }
 foreach ( [ null, '', 'view', 'edit', 'Subjects', 'Entities' ] as $name ) {
  if ( \AEWI\SWS\EntryPoints\Actions\SubjectsAction::isActionName( $name ) ) { throw new \RuntimeException( 'Other action incorrectly selected' ); }
  $checks++;
 }
 echo "OK $checks entity-action registration/alias/read-permission checks (controlled MediaWiki base class; live routing pending)\n";
}
