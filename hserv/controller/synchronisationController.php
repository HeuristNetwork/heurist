<?php

/**
 * Experimental Heurist database synchronisation controller.
 */

require_once dirname(__FILE__).'/../../autoload.php';

use hserv\synchronisation\RecordIDRemapper;
use hserv\synchronisation\SatelliteSyncService;
use hserv\synchronisation\SyncAuth;
use hserv\synchronisation\SyncConfig;
use hserv\synchronisation\SyncChangeJournal;
use hserv\synchronisation\SyncFeature;
use hserv\synchronisation\SyncSchema;
use hserv\synchronisation\SyncSessionService;

$system = new hserv\System();
$response = null;

if (!$system->init($_REQUEST['db'] ?? null)) {
    $response = $system->getError();
} elseif (!SyncFeature::requireEnabled($system)) {
    $response = $system->getError();
} else {
    $rawBody = file_get_contents('php://input') ?: '';
    $json = $rawBody !== '' ? json_decode($rawBody, true) : [];
    $params = is_array($json) ? $json : $_REQUEST;
    $action = trim((string)($params['action'] ?? $_REQUEST['action'] ?? 'status'));
    $configService = new SyncConfig($system);

    $localAdminActions = ['get_config', 'save_config', 'list_sessions', 'remap_ids', 'start_sync'];
    if (in_array($action, $localAdminActions, true)) {
        if (!$system->isAdmin()) {
            $response = $system->addError(HEURIST_REQUEST_DENIED, 'Database administrator access is required.');
        } elseif ($action === 'get_config') {
            $response = ['status' => HEURIST_OK, 'data' => $configService->load(false)];
        } elseif ($action === 'save_config') {
            $saved = $configService->save(is_array($params['config'] ?? null) ? $params['config'] : []);
            if ($saved) {
                $saved = SyncSchema::ensure($system->getMysqli())
                    && (new SyncChangeJournal($system))->ensureInstalled();
                if (!$saved && !$system->getError()) {
                    $system->addError(HEURIST_DB_ERROR, 'Unable to initialise synchronisation storage.');
                }
            }
            $response = $saved
                ? ['status' => HEURIST_OK, 'data' => $configService->load(false)]
                : $system->getError();
        } elseif ($action === 'list_sessions') {
            $service = new SyncSessionService($system);
            $response = ['status' => HEURIST_OK, 'data' => $service->listSessions()];
        } elseif ($action === 'start_sync') {
            $config = $configService->load(true);
            if (($config['role'] ?? '') !== 'satellite') {
                $response = $system->addError(HEURIST_ACTION_BLOCKED, 'This database is not configured as a satellite.');
            } else {
                $response = (new SatelliteSyncService($system, $configService, $config))->runAllocationStage();
            }
        } else {
            $config = $configService->load(true);
            if (($config['role'] ?? '') !== 'satellite') {
                $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Record IDs may only be remapped on a configured satellite.');
            } else {
                $response = (new RecordIDRemapper($system))->remap(
                    is_array($params['mapping'] ?? null) ? $params['mapping'] : []
                );
            }
        }
    } else {
        $config = $configService->load(true);
        if (($config['role'] ?? '') !== 'master') {
            $response = $system->addError(HEURIST_ACTION_BLOCKED, 'This database is not configured as a synchronisation master.');
        } else {
            $satelliteID = SyncAuth::verifyMasterRequest($system, $config, $action, $rawBody);
            if ($satelliteID < 1) {
                $response = $system->getError();
            } else {
                $service = new SyncSessionService($system);
                if ($action === 'start_session') {
                    $response = $service->startSession($satelliteID, (int)($params['lastMasterChangeReceived'] ?? 0));
                } elseif ($action === 'allocate_ids') {
                    $response = $service->allocateRecordIDs(
                        (string)($params['sessionID'] ?? ''),
                        $satelliteID,
                        is_array($params['records'] ?? null) ? $params['records'] : []
                    );
                } elseif ($action === 'upload_records') {
                    $response = $service->uploadRecords(
                        (string)($params['sessionID'] ?? ''),
                        $satelliteID,
                        is_array($params['payload'] ?? null) ? $params['payload'] : []
                    );
                } else {
                    $response = $system->addError(HEURIST_INVALID_REQUEST, 'Unknown synchronisation action.');
                }
            }
        }
    }
}

if ($response === false || $response === null) {
    $response = $system->getError();
}
$system->setResponseHeader();
print json_encode($response);
