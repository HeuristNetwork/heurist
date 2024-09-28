<?php
namespace hserv\controllers;

use hserv\utilities\DbUtils;
use hserv\utilities\DbVerify;
use hserv\utilities\USanitize;
use hserv\System;

/**
 * Class DatabaseController
 *
 * This class handles various database-related actions such as listing databases, 
 * creating databases, restoring, deleting, cloning, and verifying databases in the Heurist system.
 */
class DatabaseController
{
    /**
     * @var System $system The system object for interacting with Heurist.
     */
    private $system;

    /**
     * @var array $req_params Sanitized request parameters.
     */
    private $req_params;

    /**
     * @var int $session_id Session ID from the request, if provided.
     */
    private $session_id;

    /**
     * DatabaseController constructor.
     *
     * @param System $system The system object.
     */
    public function __construct(System $system)
    {
        $this->system = $system;
        $this->req_params = USanitize::sanitizeInputArray();
        $this->session_id = intval(@$this->req_params['session']);
    }

    /**
     * Handle the request based on the action parameter.
     *
     * It processes actions such as 'list', 'check_newdefs', 'create', 'restore', 'delete', 'clear', 'rename', 
     * 'clone', and 'verify'. Each action is directed to a corresponding method.
     *
     * @return mixed The response of the handled request, which can be an array or error message.
     */
    public function handleRequest()
    {
        $action = @$this->req_params['a'] ?? @$this->req_params['action'];
        if (!$this->system->init(@$this->req_params['db'], $action !== 'create')) {
            // Database initialization failed
            return $this->system->getError();
        }

        if (!$this->system->has_access() && $action !== 'create') {
            return $this->system->addError(HEURIST_REQUEST_DENIED, 'You must be logged in');
        }

        DbUtils::initialize();
        if ($this->session_id > 0) {
            DbUtils::setSessionId($this->session_id);
        }

        switch ($action) {
            case 'list':
                return $this->listDatabases();
            case 'check_newdefs':
                return $this->checkNewDefinitions();
            case 'create':
                return $this->createDatabase();
            case 'restore':
                return $this->restoreDatabase();
            case 'delete':
            case 'clear':
                return $this->deleteOrClearDatabase($action);
            case 'rename':
            case 'clone':
                return $this->renameOrCloneDatabase($action);
            case 'verify':
                return $this->verifyDatabase();
            default:
                return $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid action");
        }
    }

    /**
     * List all available databases.
     *
     * @return string A list of databases (Placeholder implementation).
     */
    private function listDatabases()
    {
        // Implement logic to list databases
        return "List of databases";
    }

    /**
     * Check for new definitions in the current database.
     *
     * @return mixed False if no new definitions are found, or a list of new definitions.
     */
    private function checkNewDefinitions()
    {
        $res = DbUtils::databaseCheckNewDefs();
        return $res === false ? '' : $res;
    }

    /**
     * Create a new database with the specified parameters.
     *
     * This method verifies the action password, handles new user registration (if necessary),
     * and creates the new database.
     *
     * @return array|bool Array containing database creation details, or false on failure.
     */
    private function createDatabase()
    {
        $create_pwd = USanitize::getAdminPwd('create_pwd');
        $passwordForDatabaseCreation = @$this->req_params['passwordForDatabaseCreation'];
        
        if (!$this->system->verifyActionPassword($create_pwd, $passwordForDatabaseCreation, 6)) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid action password");
        }

        $database_name = $this->__composeDbName();
        if ($database_name === false) {
            return false;
        }

        $usr_owner = $this->handleUserRegistration($database_name);
        if (!$usr_owner) {
            return false;
        }

        $res = DbUtils::databaseCreateFull($database_name, $usr_owner);
        if ($res !== false) {
            sendEmail_NewDatabase($usr_owner, $database_name, null);
            return [
                'newdbname' => $database_name,
                'newdblink' => HEURIST_BASE_URL . PARAM_WELCOME . $database_name,
                'newusername' => $usr_owner['ugr_Name'],
                'warnings' => $res
            ];
        }
        return $this->system->getError();
    }

    /**
     * Restore a database from an archive.
     *
     * This method restores the database using the provided archive file and folder.
     *
     * @return array|bool Array containing database restoration details, or false on failure.
     */
    private function restoreDatabase()
    {
        $sysadmin_pwd = USanitize::getAdminPwd('pwd');
        $passwordForServerFunctions = @$this->req_params['passwordForServerFunctions'];
        $database_name = $this->__composeDbName();

        if ($database_name === false) {
            return false;
        }

        if ($this->system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)) {
            $archive_file = @$this->req_params['file'];
            $archive_folder = intval(@$this->req_params['folder']);
            $res = DbUtils::databaseRestoreFromArchive($database_name, $archive_file, $archive_folder);

            if ($res !== false) {
                return [
                    'newdbname' => $database_name,
                    'newdblink' => HEURIST_BASE_URL . PARAM_WELCOME . $database_name
                ];
            }
        }
        return $this->system->getError();
    }

    /**
     * Delete or clear a database.
     *
     * This method handles the deletion or clearing of a database based on the action provided.
     *
     * @param string $action The action to perform ('delete' or 'clear').
     * @return mixed Array containing the result of the action or error message.
     */
    private function deleteOrClearDatabase($action)
    {
        $sysadmin_pwd = USanitize::getAdminPwd('pwd');
        $db_target = $this->__sanitizeDbTarget();
        $create_archive = !array_key_exists('noarchive', $this->req_params);
        $passwordForDatabaseDeletion = @$this->req_params['passwordForDatabaseDeletion'];

        if ($this->system->verifyActionPassword($sysadmin_pwd, $passwordForDatabaseDeletion, 15)) {
            if ($action === 'clear') {
                return DbUtils::databaseEmpty($db_target, false);
            } elseif ($action === 'delete') {
                return DbUtils::databaseDrop(false, $db_target, $create_archive);
            }
        }
        return $this->system->getError();
    }

    /**
     * Rename or clone a database.
     *
     * This method handles the renaming or cloning of a database based on the action provided.
     *
     * @param string $action The action to perform ('rename' or 'clone').
     * @return mixed Array containing the result of the action or error message.
     */
    private function renameOrCloneDatabase($action)
    {
        $db_source = @$this->req_params['db'];
        $db_target = $this->__composeDbName();
        $create_archive = !array_key_exists('noarchive', $this->req_params);
        $nodata = array_key_exists('nodata', $this->req_params);

        if ($action === 'rename') {
            $res = DbUtils::databaseRename($db_source, $db_target, $create_archive);
            if ($res !== false) {
                return [
                    'newdbname' => $db_target,
                    'newdblink' => HEURIST_BASE_URL . PARAM_WELCOME . $db_target,
                    'warning' => $this->system->getErrorMsg()
                ];
            }
        } elseif ($action === 'clone') {
            $res = DbUtils::databaseCloneFull($db_source, $db_target, $nodata, false);
            if ($res !== false) {
                DbUtils::databaseResetRegistration($db_target);
                return [
                    'newdbname' => $db_target,
                    'newdblink' => HEURIST_BASE_URL . PARAM_WELCOME . $db_target
                ];
            }
        }
        return $this->system->getError();
    }

    /**
     * Verify the integrity of the database.
     *
     * This method checks the database for issues or problems based on the checks specified in the request.
     *
     * @return array|bool The results of the verification checks, or false on failure.
     */
    private function verifyDatabase()
    {
        if (!$this->system->is_admin()) {
            return $this->system->addError(HEURIST_REQUEST_DENIED, "You must be logged in as an Administrator");
        }

        $dbVerify = new DbVerify($this->system);
        $checks = @$this->req_params['checks'] ?? 'all';
        $actions = $checks === 'all' ? $this->getAllVerificationMethods($dbVerify) : explode(',', $checks);

        $res = [];
        foreach ($actions as $action) {
            $method = 'check_' . $action;
            if (method_exists($dbVerify, $method)) {
                $res[$action] = $dbVerify->$method($this->req_params);
            }
        }

        if (empty($res)) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, "'Checks' parameter is missing or incorrect");
        }

        return $res;
    }

    /**
     * Get all verification methods from the DbVerify class.
     *
     * @param DbVerify $dbVerify The DbVerify instance.
     * @return array An array of available verification methods.
     */
    private function getAllVerificationMethods($dbVerify)
    {
        $class_methods = get_class_methods($dbVerify);
        return array_filter($class_methods, function ($method) {
            return strpos($method, 'check_') === 0;
        });
    }

    /**
     * Compose a database name based on request parameters.
     *
     * @return string|bool The composed database name, or false if the name is invalid.
     */
    private function __composeDbName()
    {
        $uName = '';
        if (@$this->req_params['uname']) {
            $uName = trim(preg_replace(REGEX_ALPHANUM, '', @$this->req_params['uname'])) . '_';
            if ($uName == '_') {
                $uName = '';
            }
        }
        $dbName = trim(preg_replace(REGEX_ALPHANUM, '', @$this->req_params['dbname']));

        if ($dbName == '') {
            $this->system->addError(HEURIST_INVALID_REQUEST, "Database name is missing or incorrect");
            return false;
        }

        return $uName . $dbName;
    }

    /**
     * Sanitize the target database name from the request parameters.
     *
     * @return string The sanitized target database name.
     */
    private function __sanitizeDbTarget()
    {
        return trim(preg_replace(REGEX_ALPHANUM, '', @$this->req_params['database'] ?: @$this->req_params['db']));
    }

    /**
     * Handle user registration for creating a database.
     *
     * @param string $database_name The name of the database being created.
     * @return array|bool Array containing user registration details, or false on failure.
     */
    private function handleUserRegistration($database_name)
    {
        // Registration logic
        return ['ugr_Name' => 'test_user'];  // Example return value for now
    }
}

// Example usage
$system = new System();
$controller = new DatabaseController($system);
$response = $controller->handleRequest();
header(CTYPE_JSON);
print json_encode($response);

