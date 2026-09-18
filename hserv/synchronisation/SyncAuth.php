<?php

namespace hserv\synchronisation;

/**
 * HMAC authentication and replay protection for satellite-to-master requests.
 */
final class SyncAuth
{
    public static function verifyMasterRequest(
        \hserv\System $system,
        array $config,
        string $action,
        string $rawBody
    ): int {
        $satelliteID = (int)($_SERVER['HTTP_X_HEURIST_SATELLITE_ID'] ?? 0);
        $timestamp = (int)($_SERVER['HTTP_X_HEURIST_SYNC_TIMESTAMP'] ?? 0);
        $nonce = trim((string)($_SERVER['HTTP_X_HEURIST_SYNC_NONCE'] ?? ''));
        $signature = trim((string)($_SERVER['HTTP_X_HEURIST_SYNC_SIGNATURE'] ?? ''));

        if ($satelliteID < 1 || abs(time() - $timestamp) > 300
            || !preg_match('/^[a-f0-9-]{16,64}$/i', $nonce) || $signature === '') {
            $system->addError(HEURIST_REQUEST_DENIED, 'Invalid or expired synchronisation authentication headers.');
            return 0;
        }

        $satellite = null;
        foreach ($config['satellites'] ?? [] as $candidate) {
            if ((int)($candidate['databaseID'] ?? 0) === $satelliteID && !empty($candidate['enabled'])) {
                $satellite = $candidate;
                break;
            }
        }
        if (!$satellite || empty($satellite['sharedKey'])) {
            $system->addError(HEURIST_REQUEST_DENIED, 'Satellite is not authorised by this master.');
            return 0;
        }

        $canonical = $satelliteID."\n".$timestamp."\n".$nonce."\n".$action."\n".hash('sha256', $rawBody);
        $expected = hash_hmac('sha256', $canonical, (string)$satellite['sharedKey']);
        if (!hash_equals($expected, $signature)) {
            $system->addError(HEURIST_REQUEST_DENIED, 'Synchronisation signature is invalid.');
            return 0;
        }

        if (!SyncSchema::ensure($system->getMysqli())) {
            $system->addError(HEURIST_DB_ERROR, 'Unable to initialise synchronisation replay protection.');
            return 0;
        }
        $stmt = $system->getMysqli()->prepare(
            'INSERT INTO sysSyncNonces (snc_SatelliteDBID,snc_Nonce) VALUES (?,?)'
        );
        $stmt->bind_param('is', $satelliteID, $nonce);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $system->addError(HEURIST_REQUEST_DENIED, 'This synchronisation request has already been processed.');
            return 0;
        }
        $system->getMysqli()->query("DELETE FROM sysSyncNonces WHERE snc_SeenAt < UTC_TIMESTAMP() - INTERVAL 1 DAY");
        return $satelliteID;
    }
}
