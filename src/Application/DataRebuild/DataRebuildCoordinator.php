<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\DataRebuild;

/** Bounded current-data walk. A failed item is never acknowledged as completed. */
final readonly class DataRebuildCoordinator {
    public function __construct(private \Closure $pageIdsAfter,private \Closure $rebuildPage) {}
    public function run(int $after,int $batchSize,bool $refreshSources,\Closure $checkpoint):array {
        if($after<0||$batchSize<1||$batchSize>1000) { throw new \InvalidArgumentException('Invalid rebuild cursor or batch size'); }
        $processed=0;
        do {
            $ids=($this->pageIdsAfter)($after,$batchSize);
            if(!is_array($ids)||count($ids)>$batchSize) { throw new \RuntimeException('Invalid rebuild batch'); }
            foreach($ids as$id) {
                if(!is_int($id)||$id<=$after) { throw new \RuntimeException('Rebuild cursor did not advance'); }
                ($this->rebuildPage)($id,$refreshSources);
                $after=$id;$processed++;
                $checkpoint(['after'=>$after,'processed'=>$processed]);
            }
        }while(count($ids)===$batchSize);
        return ['after'=>$after,'processed'=>$processed];
    }
}
