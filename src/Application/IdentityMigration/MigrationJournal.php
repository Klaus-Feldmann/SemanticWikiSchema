<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

/** Local locked journal, HMAC-bound to this installation. Contains original current content, not a rollback tool. */
final class MigrationJournal {
    private $lock;
    private ?string $planHash=null;
    public function __construct(private string $path,private string $secret) {
        $this->lock=fopen($path.'.lock','c');
        if($this->lock===false||!flock($this->lock,LOCK_EX|LOCK_NB)) { throw new \RuntimeException('Migration journal is locked or unwritable'); }
        @chmod($path.'.lock',0600);
    }
    public function write(array $plan,array $state=[]):void {
        if($this->planHash!==null) { throw new \LogicException('Original migration plan is immutable; use checkpoint'); }
        $body=['plan'=>$plan,'state'=>$state];$body['signature']=$this->sign($body);
        $temporary=tempnam(dirname($this->path),'.sws-migration-');
        if($temporary===false) { throw new \RuntimeException('Cannot create migration checkpoint'); }
        try {
            chmod($temporary,0600);
            $json=ReferenceRewriter::encode($body)."\n";
            if(file_put_contents($temporary,$json)!==strlen($json)||!rename($temporary,$this->path)) { throw new \RuntimeException('Cannot commit migration checkpoint'); }
        } finally { if(is_file($temporary)) { unlink($temporary); } }
        $this->planHash=hash('sha256',ReferenceRewriter::encode($plan));
    }
    public function read():array {
        $data=json_decode(file_get_contents($this->path),true,512,JSON_THROW_ON_ERROR);$signature=$data['signature']??'';unset($data['signature']);
        if(!is_string($signature)||!hash_equals($this->sign($data),$signature)) { throw new \RuntimeException('Migration journal signature does not match'); }
        $this->planHash=hash('sha256',ReferenceRewriter::encode($data['plan']));
        if(is_file($this->path.'.state')) {
            $progress=json_decode(file_get_contents($this->path.'.state'),true,512,JSON_THROW_ON_ERROR);
            $signature=$progress['signature']??'';unset($progress['signature']);
            if(!is_string($signature)||!hash_equals($this->sign($progress),$signature)||($progress['planHash']??null)!==$this->planHash||($progress['state']['planHash']??null)!==$this->planHash) { throw new \RuntimeException('Migration progress signature does not match the plan'); }
            $data['state']=$progress['state'];
        }
        return $data;
    }
    /** Only the small progress file changes; original source documents are written once. */
    public function checkpoint(array $state):void {
        if($this->planHash===null||($state['planHash']??null)!==$this->planHash) { throw new \RuntimeException('Checkpoint does not match the loaded migration plan'); }
        $body=['planHash'=>$this->planHash,'state'=>$state];$body['signature']=$this->sign($body);
        $temporary=tempnam(dirname($this->path),'.sws-progress-');
        if($temporary===false) { throw new \RuntimeException('Cannot create migration progress'); }
        try {
            chmod($temporary,0600);$json=ReferenceRewriter::encode($body)."\n";
            if(file_put_contents($temporary,$json)!==strlen($json)||!rename($temporary,$this->path.'.state')) { throw new \RuntimeException('Cannot commit migration progress'); }
        } finally { if(is_file($temporary)) { unlink($temporary); } }
    }

    private function sign(array $data):string { return hash_hmac('sha256',ReferenceRewriter::encode($data),$this->secret); }
    public function __destruct() { if(is_resource($this->lock)) { flock($this->lock,LOCK_UN);fclose($this->lock); } }
}
