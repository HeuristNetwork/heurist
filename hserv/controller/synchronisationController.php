<?php
/** Manifest synchronisation endpoint: terms and files only in version 1.5.0. */
require_once dirname(__FILE__).'/../../autoload.php';

use hserv\synchronisation\ManifestSyncService;
use hserv\synchronisation\SatelliteSyncService;
use hserv\synchronisation\SyncAuth;
use hserv\synchronisation\SyncConfig;
use hserv\synchronisation\SyncFeature;
use hserv\synchronisation\SyncProgress;
use hserv\synchronisation\SyncSchema;

$system=new hserv\System();$response=null;$action='';
try{
    if(!$system->init($_REQUEST['db']??null))$response=$system->getError();
    elseif(!SyncFeature::requireEnabled($system))$response=$system->getError();
    else{
        $raw=file_get_contents('php://input')?:'';$json=$raw!==''?json_decode($raw,true):[];
        $params=is_array($json)?$json:$_REQUEST;$action=trim((string)($params['action']??$_REQUEST['action']??'get_config'));
        $configService=new SyncConfig($system);
        $local=['get_config','save_config','start_sync','reset_progress','sync_progress'];
        if(in_array($action,$local,true)){
            if(!$system->isAdmin())$response=$system->addError(HEURIST_REQUEST_DENIED,'Database administrator access is required.');
            elseif($action==='get_config')$response=['status'=>HEURIST_OK,'data'=>$configService->load(false)];
            elseif($action==='save_config'){
                $saved=$configService->save(is_array($params['config']??null)?$params['config']:[]);
                if($saved&&($configService->load(true)['role']??'')!=='disconnected')$saved=SyncSchema::ensure($system->getMysqli());
                $response=$saved?['status'=>HEURIST_OK,'data'=>$configService->load(false)]:$system->getError();
            }elseif($action==='reset_progress'){
                $progress=new SyncProgress($system);$progress->startCounting();$response=['status'=>HEURIST_OK,'data'=>$progress->get()];
            }elseif($action==='sync_progress')$response=['status'=>HEURIST_OK,'data'=>(new SyncProgress($system))->get()];
            else{
                $config=$configService->load(true);
                if(($config['role']??'')!=='satellite')$response=$system->addError(HEURIST_ACTION_BLOCKED,'This database is not configured as a Satellite.');
                else{@set_time_limit(0);if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
                    $response=(new SatelliteSyncService($system,$configService,$config))->runAllocationStage();}
            }
        }else{
            $config=$configService->load(true);
            if(($config['role']??'')!=='master')$response=$system->addError(HEURIST_ACTION_BLOCKED,'This database is not configured as a synchronisation Master.');
            elseif(!SyncSchema::ensure($system->getMysqli()))$response=$system->addError(HEURIST_DB_ERROR,'Unable to initialise synchronisation support tables.');
            else{
                $satelliteID=SyncAuth::verifyMasterRequest($system,$config,$action,$raw);
                if($satelliteID<1)$response=$system->getError();
                else{
                    $service=new ManifestSyncService($system);
                    // Integrity runs before each independent request so a restart never relies on an earlier request.
                    $service->checkIntegrity();
                    if($action==='term_compare')$data=['missingConcepts'=>$service->missingTerms($params['manifest']??[],$satelliteID)];
                    elseif($action==='term_upload')$data=['inserted'=>$service->acceptSatelliteTerms($params['terms']??[],$satelliteID)];
                    elseif($action==='term_manifest')$data=['manifest'=>$service->masterTermManifest()];
                    elseif($action==='term_fetch')$data=['terms'=>$service->requestedTerms($params['concepts']??[])];
                    elseif($action==='file_allocate')$data=$service->allocateMasterFiles($params['manifest']??[]);
                    elseif($action==='file_upload')$data=$service->applyFiles($params['files']??[],false);
                    elseif($action==='file_manifest')$data=['manifest'=>$service->fileManifest()];
                    elseif($action==='file_fetch')$data=['files'=>$service->filePayload($params['concepts']??[])];
                    else throw new RuntimeException('Unknown synchronisation action.');
                    $response=['status'=>HEURIST_OK,'data'=>$data];
                }
            }
        }
    }
}catch(Throwable $e){
    $response=$system->addError(HEURIST_ACTION_BLOCKED,'Synchronisation stopped: '.$e->getMessage(),
        get_class($e).' in '.$e->getFile().' line '.$e->getLine());
    try{if($action==='start_sync')(new SyncProgress($system))->fail($e->getMessage());}catch(Throwable $ignored){}
}
if($response===false||$response===null)$response=$system->getError();
$system->setResponseHeader();print json_encode($response);
