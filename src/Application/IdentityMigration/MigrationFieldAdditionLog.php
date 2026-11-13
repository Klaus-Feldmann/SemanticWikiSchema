<?php
declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

/** Private, signed write-ahead report. The original migration journal is never changed. */
final class MigrationFieldAdditionLog {
    private array $entries=[];
    public function __construct(public readonly string $path,private string $secret,private string $planHash){
        if(is_file($path)){
            $data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$signature=$data['signature']??null;unset($data['signature']);
            if(!is_string($signature)||!hash_equals($this->sign($data),$signature)||($data['planHash']??null)!==$planHash||!is_array($data['entries']??null)){
                throw new \RuntimeException('Invalid migration field-addition report');
            }
            $this->entries=$data['entries'];
        } else {$this->persist();}
    }
    public function entries():array{return $this->entries;}
    public function prepare(array $entry):string {
        $key=hash('sha256',$entry['schemaId']."\0".$entry['field']);
        if(isset($this->entries[$key])){
            if($this->entries[$key]['afterJson']!==$entry['afterJson']||$this->entries[$key]['beforeRevision']!==$entry['beforeRevision']){throw new \RuntimeException('Conflicting automatic field addition');}
            return $key;
        }
        $this->entries[$key]=$entry+['status'=>'pending'];$this->persist();return $key;
    }
    public function complete(string $key,int $revision):void {
        if(!isset($this->entries[$key])||$revision<1){throw new \RuntimeException('Invalid field-addition acknowledgement');}
        $this->entries[$key]['status']='completed';$this->entries[$key]['revision']=$revision;$this->persist();
    }
    private function sign(array $body):string{return hash_hmac('sha256',ReferenceRewriter::encode($body),$this->secret);}
    private function persist():void {
        $body=['version'=>1,'planHash'=>$this->planHash,'entries'=>$this->entries];$body['signature']=$this->sign($body);
        $temp=tempnam(dirname($this->path),'.sws-fields-');if($temp===false){throw new \RuntimeException('Cannot create field-addition report');}
        try{chmod($temp,0600);$json=ReferenceRewriter::encode($body)."\n";
            if(file_put_contents($temp,$json)!==strlen($json)||!rename($temp,$this->path)){throw new \RuntimeException('Cannot save field-addition report');}
        }finally{if(is_file($temp)){unlink($temp);}}
    }
}
