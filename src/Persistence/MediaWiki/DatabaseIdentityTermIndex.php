<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Subject\SubjectDisplayName;
use Wikimedia\Rdbms\IDatabase;

/** Derived from published revisions only; uses the caller's ordinary transaction. */
final readonly class DatabaseIdentityTermIndex {
    public const TABLE = 'sws_search_term';
    public function __construct( private IDatabase $db ) {}
    public function setSubjects( PageId $page, string $pageName, PageSubjects $subjects ): void {
        $rows = [];
        foreach ( $subjects->getAllSubjects()->asArray() as $subject ) {
            $identity = $subject->getIdentity();
            $labels = $identity?->labels ?? [];
            $fallback = SubjectDisplayName::labelOrPageName( $subject, $subjects, $pageName );
            if ( $fallback !== null ) { $labels += [ '' => $fallback ]; }
            $rows = array_merge( $rows, self::rows( $page->id, 'entity', $subject->id->text, $labels, $identity?->descriptions ?? [] ) );
        }
        $this->replace( $page->id, 'entity', $rows );
    }
    public function setSchema( PageId $page, ?array $schema, string $name ): void {
        $rows = $schema === null ? [] : self::rows( $page->id, 'schema', $name,
            [ '' => $name ] + (array)( $schema['labels'] ?? [] ),
            [ '' => (string)( $schema['description'] ?? '' ) ] + (array)( $schema['descriptions'] ?? [] ) );
        $this->replace( $page->id, 'schema', $rows );
    }
    /** Public pure builder also used by import/rebuild verification. */
    public static function rows( int $page, string $kind, string $reference, array $labels, array $descriptions ): array {
        $rows = [];
        foreach ( [ 'label'=>$labels, 'description'=>$descriptions ] as $role=>$terms ) {
            foreach ( $terms as $language=>$term ) {
                if ( !is_string($term) || trim($term) === '' ) { continue; }
                $rows[] = [ 'swst_page_id'=>$page, 'swst_kind'=>$kind, 'swst_reference'=>$reference,
                    'swst_language'=>(string)$language, 'swst_role'=>$role,
                    'swst_key'=>mb_strcut( CaseInsensitiveKey::of($term), 0, 255, 'UTF-8' ) ];
            }
        }
        return $rows;
    }
    private function replace( int $page, string $kind, array $rows ): void {
        $wanted = []; foreach ($rows as $row) { $wanted[self::key($row)]=$row; }
        $existing = [];
        foreach ($this->db->newSelectQueryBuilder()->select('*')->from(self::TABLE)->where(['swst_page_id'=>$page,'swst_kind'=>$kind])->caller(__METHOD__)->fetchResultSet() as $row) {
            $row=(array)$row; $row['swst_page_id']=(int)$row['swst_page_id']; $existing[self::key($row)]=$row;
        }
        if ($wanted === $existing) { return; }
        $scope=$this->db->startAtomic(__METHOD__, IDatabase::ATOMIC_CANCELABLE);
        try {
            // Delete/insert only changed primary keys, avoiding a language-wide rebuild on label edits.
            foreach ($existing as $key=>$row) {
                if (!isset($wanted[$key]) || $wanted[$key] !== $row) {
                    unset($row['swst_key']);
                    $this->db->newDeleteQueryBuilder()->deleteFrom(self::TABLE)->where($row)->caller(__METHOD__)->execute();
                }
            }
            $insert=[];
            foreach ($wanted as $key=>$row) { if (!isset($existing[$key]) || $existing[$key] !== $row) { $insert[]=$row; } }
            if ($insert !== []) { $this->db->newInsertQueryBuilder()->insertInto(self::TABLE)->rows($insert)->caller(__METHOD__)->execute(); }
            $this->db->endAtomic(__METHOD__);
        } catch (\Throwable $e) { $this->db->cancelAtomic(__METHOD__,$scope); throw $e; }
    }
    private static function key(array $row): string {
        unset($row['swst_key']); return json_encode($row,JSON_THROW_ON_ERROR);
    }
    public function removePage(PageId $page):void {
        $this->db->newDeleteQueryBuilder()->deleteFrom(self::TABLE)->where(['swst_page_id'=>$page->id])->caller(__METHOD__)->execute();
    }
}
