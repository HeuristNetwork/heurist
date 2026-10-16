<?php
namespace hserv\synchronisation;

/** Signed HTTPS client for the small, restartable manifest operations. */
final class SyncHttpClient
{
    private \hserv\System $system; private array $config;
    public function __construct(\hserv\System $system,array $config){$this->system=$system;$this->config=$config;}
    public function termCompare(array $v):array{return $this->request('term_compare',['manifest'=>$v]);}
    public function termUpload(array $v):array{return $this->request('term_upload',['terms'=>$v]);}
    public function termManifest():array{return $this->request('term_manifest',[]);}
    public function termFetch(array $v):array{return $this->request('term_fetch',['concepts'=>$v]);}
    public function fileAllocate(array $v):array{return $this->request('file_allocate',['manifest'=>$v]);}
    public function fileUpload(array $v):array{return $this->request('file_upload',['files'=>$v]);}
    public function fileManifest():array{return $this->request('file_manifest',[]);}
    public function fileFetch(array $v):array{return $this->request('file_fetch',['concepts'=>$v]);}

    private function request(string $action,array $payload):array
    {
        $master=$this->config['master']??[];$satelliteID=(int)($this->config['databaseID']??0);$key=(string)($master['sharedKey']??'');
        if($satelliteID<1||$key==='')return $this->system->addError(HEURIST_SYSTEM_CONFIG,'Satellite authentication is not configured.');
        $payload=['action'=>$action]+$payload;$body=json_encode($payload,JSON_UNESCAPED_SLASHES);
        $timestamp=time();$nonce=bin2hex(random_bytes(16));
        $canonical=$satelliteID."\n".$timestamp."\n".$nonce."\n".$action."\n".hash('sha256',$body);
        $signature=hash_hmac('sha256',$canonical,$key);
        $url=rtrim((string)$master['url'],'/').'/hserv/controller/synchronisationController.php?db='.rawurlencode((string)$master['database']);
        $curl=curl_init($url);curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>300,CURLOPT_HTTPHEADER=>['Content-Type: application/json',
            'X-Heurist-Satellite-ID: '.$satelliteID,'X-Heurist-Sync-Timestamp: '.$timestamp,
            'X-Heurist-Sync-Nonce: '.$nonce,'X-Heurist-Sync-Signature: '.$signature]]);
        $raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$error=curl_error($curl);curl_close($curl);
        if($raw===false)return $this->diagnostic('connection_failed','The satellite could not connect to the Master.',$error.' Endpoint: '.$url);
        $response=json_decode($raw,true);
        if($status<200||$status>=300)return $this->diagnostic('http_error','The Master returned HTTP '.$status.'.',substr(strip_tags((string)$raw),0,500));
        if(!is_array($response))return $this->diagnostic('invalid_response','The Master did not return valid synchronisation JSON.',substr(strip_tags((string)$raw),0,500));
        return $response;
    }
    private function diagnostic(string $code,string $message,string $detail):array
    {return ['status'=>HEURIST_ACTION_BLOCKED,'message'=>$message,'sysmsg'=>$detail,'diagnosticCode'=>$code];}
}
