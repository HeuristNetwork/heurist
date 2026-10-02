<?php
/**
 * CLI worker for login-triggered database safeguards.
 * Usage: php automaticSafeguard.php <database name>
 * Never accept an unauthenticated HTTP request to this script.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 2 || !preg_match('/^[A-Za-z0-9_]+$/', $argv[1])) {
    exit(1);
}

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/structure/dbsUsersGroups.php';

use hserv\utilities\SafeguardBackup;

$system = new hserv\System();
if (!$system->init($argv[1], false, false)) {
    exit(1);
}
if (!$system->initPathConstants()) {
    exit(1);
}
if (!SafeguardBackup::isEnabled()) {
    exit(0); // A queued job must stop if the server disables the experiment.
}
$settings = $system->getSysDir('settings');
$lock = fopen($settings.'safeguard_worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // another login has already started the worker
}
$config = SafeguardBackup::settings($system);
$accounts = $config['accounts'] ?? [];
if (!empty($config['email_file'])) {
    $accounts[] = 'email_owner';
}
if (!$accounts) {
    exit(0);
}
$today = time();
$days = max(1, min(365, (int)($config['interval_days'] ?? 30)));
$due = [];
foreach ($accounts as $account) {
    $last = strtotime($config['deposits'][$account]['date'] ?? '') ?: 0;
    if (!$last || $last + $days * 86400 <= $today) {
        $due[] = $account;
    }
}
if (!$due) {
    exit(0);
}

$ownerEmail = user_getDbOwner($system->getMysqli(), 'ugr_eMail');
$message = '';
try {
    $fingerprint = SafeguardBackup::fingerprint($system);
    $needed = [];
    foreach ($due as $account) {
        if (($config['deposits'][$account]['fingerprint'] ?? null) !== $fingerprint) {
            $needed[] = $account;
        }
    }
    if (!$needed) {
        // Keep checking on each subsequent login once the interval has passed:
        // an edit may have occurred since this unchanged check.
        $config['last_attempt'] = null;
        $system->settings->setDatabaseSetting('Safeguard backups', $config);
        exit(0); // no changes, so do not advance the last backup date
    }

    $php = PHP_BINARY;
    $builder = __DIR__.'/buildArchivePackagesCMD.php';
    $cmd = escapeshellarg($php).' '.escapeshellarg($builder)
        .' -- '.escapeshellarg('-db='.$system->dbname()).' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    $archive = $system->getFileStoreRootFolder().'_BATCH_PROCESS_ARCHIVE_PACKAGE/'.$system->dbname().'.zip';
    if ($exitCode !== 0 || !is_file($archive) || filesize($archive) === 0
        || !preg_match('/\bOK\s*$/', implode("\n", $output))) {
        throw new RuntimeException('The safeguard archive could not be created');
    }
    // Unique filename on the repository; locally the builder uses its established path.
    $datedArchive = dirname($archive).'/'.$system->dbname().'_'.gmdate('Ymd_His').'.zip';
    if (!rename($archive, $datedArchive)) {
        throw new RuntimeException('Could not retain the newly generated archive');
    }
    $archive = $datedArchive;
    $errors = [];
    $successes = [];
    foreach ($needed as $account) {
        try {
            if ($account === 'email_owner') {
                if (!$ownerEmail) {
                    throw new RuntimeException('The database owner has no email address');
                }
                require_once dirname(__FILE__).'/../../hserv/utilities/UMail.php';
                if (!sendEmail($ownerEmail, 'Heurist database safeguard: '.$system->dbname(),
                    'Your database safeguard file is attached.', false, $archive)) {
                    throw new RuntimeException('Email delivery to the database owner failed');
                }
                $config['deposits'][$account] = ['date' => gmdate('c'), 'fingerprint' => $fingerprint];
                if (!$system->settings->setDatabaseSetting('Safeguard backups', $config)) {
                    throw new RuntimeException('Could not save the successful email delivery');
                }
                $successes[] = 'the database owner by email';
                continue;
            }
            $checkpoint = function ($entry) use ($system, &$config, $account) {
                $config['deposits'][$account] = $entry;
                if (!$system->settings->setDatabaseSetting('Safeguard backups', $config)) {
                    throw new RuntimeException('Could not save repository draft state');
                }
            };
            $previous = $config['deposits'][$account] ?? [];
            $deposited = SafeguardBackup::deposit($system, $account, $archive, $previous, $checkpoint);
            if (empty($deposited['doi'])) {
                throw new RuntimeException('The repository did not return a DOI');
            }
            $deposited['date'] = gmdate('c');
            $deposited['fingerprint'] = $fingerprint;
            $dois = $system->settings->getDatabaseSetting('DOIs');
            $dois = is_array($dois) ? $dois : [];
            $dois['database'][$account] = [
                'doi' => $deposited['doi'], 'version_doi' => $deposited['version_doi'] ?? $deposited['doi'],
                'date' => $deposited['date']
            ];
            $dois['database_doi'] = $dois['database_doi'] ?? $deposited['doi'];
            if (!$system->settings->setDatabaseSetting('DOIs', $dois)) {
                throw new RuntimeException('Could not write DOIs.json; deposit succeeded: '.$deposited['doi']);
            }
            $config['deposits'][$account] = $deposited;
            if (!$system->settings->setDatabaseSetting('Safeguard backups', $config)) {
                throw new RuntimeException('Could not save the successful deposit');
            }
            $successes[] = $account.' ('.$deposited['doi'].')';
        } catch (Throwable $e) {
            $errors[] = $account.': '.$e->getMessage();
        }
    }
    if ($successes) {
        $config['last_backup'] = gmdate('c');
        $system->settings->setDatabaseSetting('Safeguard backups', $config);
    }
    $message = $successes ? 'Your database has been backed up to '.implode(', ', $successes).' on '.gmdate('Y-m-d').'.' : '';
    if ($errors) {
        $message .= ($message ? ' ' : '').'Safeguard upload failed: '.implode('; ', $errors);
    }
    if (empty($config['email_file'])) {
        unlink($archive);
    }
} catch (Throwable $e) {
    $message = 'Automatic safeguard failed for '.$system->dbname().': '.$e->getMessage();
    error_log($message);
}

if ($message !== '') {
    $config = SafeguardBackup::settings($system); // preserve per-account checkpoints
    $config['notice'] = ['id' => bin2hex(random_bytes(8)), 'text' => $message, 'seen' => []];
    $system->settings->setDatabaseSetting('Safeguard backups', $config);
    if ($ownerEmail) {
        require_once dirname(__FILE__).'/../../hserv/utilities/UMail.php';
        $attachment = null; // The selected email destination was handled and checkpointed above.
        if (!sendEmail($ownerEmail, 'Heurist database safeguard: '.$system->dbname(), $message, false, $attachment)) {
            $config['notice']['text'] .= ' Email delivery to the database owner failed.';
            $system->settings->setDatabaseSetting('Safeguard backups', $config);
        }
    }
}
if (isset($archive) && is_file($archive)) {
    unlink($archive);
}
flock($lock, LOCK_UN);
fclose($lock);
