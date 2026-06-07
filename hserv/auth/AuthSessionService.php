<?php
namespace hserv\auth;

use hserv\System;
use hserv\utilities\USystem;
use hserv\utilities\USanitize;

final class AuthSessionService
{
    private System $system;
    public function __construct(System $system) {
        $this->system = $system;
    }

    /**
     * Starts/restores the Heurist PHP session and stores per-database host metadata.
     * This is the compatibility replacement for System::startMySession().
     */
    public function startMySession($check_session_folder = true): bool
    {
        global $envVersion, $dbHostName;

        if ($check_session_folder && !USystem::sessionCheckFolder()) {
            $this->system->addError(
                HEURIST_SYSTEM_FATAL,
                "The sessions folder has become inaccessible. This is a minor, but annoying, problem for which we apologise. An email has been sent to your system administrator asking them to fix it - this may take up to a day, depending on time differences. Please try again later."
            );
            return false;
        }

        if (!$this->system->session()->start()) {
            return headers_sent();
        }

        $dbname = $this->system->dbnameFull();
        if ($dbname) {
            $keepAlive = $this->system->session()->updateDb($dbname, function (&$dbSession) use ($envVersion, $dbHostName) {
                if (isset($envVersion)) {
                    $dbSession['dbHostCode'] = $envVersion;
                    $dbSession['dbHostName'] = $dbHostName;
                }

                return !empty($dbSession['keepalive']);
            });

            if ($keepAlive && !USystem::sessionUpdateCookies()) {
                USanitize::errorLog('CANNOT UPDATE COOKIE ' . session_id() . '   ' . $dbname);
            }
        }

        return true;
    }

    /**
     * Returns the logged-in user id for a database, or false.
     */
    public function verifyCredentials($db, $closeSession = true)
    {
        if (!$this->system->setDbnameFull($db)) {
            return false;
        }

        $userID = $this->system->session()->getDbValue($this->system->dbnameFull(), 'ugr_ID', false);

        if ($closeSession) {
            $this->system->session()->close();
        }

        return $userID ? (int)$userID : false;
    }

    /**
     * Loads the current user from the DB-scoped session, optionally refreshing it from sysUGrps.
     * 
     * Verifies the current user's login status and loads their information.
     * This method is central to establishing the `$system->currentUser` property.
     *
     * Behavior depends on the `$user` parameter:
     * - If `$user` is `true` (boolean): Reloads user information (ID, name, groups, permissions) directly from the database.
     * - If `$user` is `false` (boolean): Attempts to load user information from the `$_SESSION`.
     * - If `$user` is an array: It's assumed to be pre-fetched user data (e.g., after a successful login attempt).
     *   The method then uses this data, sets `reload_user_from_db` to true to ensure session is updated consistently.
     *
     * Regardless of `$user`, `ugr_Preferences` are always reloaded from the database if the user ID is established
     * and `reload_user_from_db` becomes true or was initially true.
     *
     * It also handles:
     * - Checking for linked sessions if no direct session user ID is found.
     * - Checking for a marker file (`HEURIST_FILESTORE_DIR . basename($userID)`) that indicates user info might have been updated externally,
     *   triggering a reload from the database.
     * - Setting guest user permissions if `$is_guest_allowed` is true and the user account is normally disabled.
     *
     * @param bool|array $user Determines how user data is loaded:
     *                         `true` to force reload from DB,
     *                         `false` to load from session,
     *                         `array` of user data to use pre-fetched info.
     * @param bool $is_guest_allowed Optional. If true and the user account is 'disabled',
     *                                 treats the user as a guest by overriding the 'disabled' permission
     *                                 and setting 'guest_user' permission. Defaults to false.
     * @return bool True if a user is successfully logged in and verified, false otherwise.
     */
    public function loginVerify($user, $is_guest_allowed = false): bool
    {
        $dbname = $this->system->dbnameFull();
        if (!$dbname) {
            return false;
        }

        $reloadUserFromDb = false;
        $userID = null;

        if (is_array($user) && isset($user['ugr_ID'])) {
            $reloadUserFromDb = true;
            $userID = (int)$user['ugr_ID'];
        } else {
            $reloadUserFromDb = ($user === true);
            $sessionUserID = $this->system->session()->getDbValue($dbname, 'ugr_ID', null);
            $userID = $sessionUserID !== null ? (int)$sessionUserID : null;
        }

        if ($userID === null) {
            $linkedUserID = $this->doLoginByLinkedSession();
            if ($linkedUserID !== null) {
                $userID = $linkedUserID;
                $reloadUserFromDb = true;
            }
        }

        if ($userID === null || $userID <= 0) {
            return false;
        }

        if (defined('HEURIST_FILESTORE_DIR')) {
            $updateMarker = HEURIST_FILESTORE_DIR . basename((string)$userID);
            if (file_exists($updateMarker)) {
                unlink($updateMarker);
                if ($user !== true && !is_array($user)) {
                    $this->system->userSession()->setDbValue('need_refresh', 1);
                }
                $reloadUserFromDb = true;
            }
        }

        if ($reloadUserFromDb) {
            if (!$this->updateSessionForUser($userID)) {
                $this->system->setCurrentUser(null);
                return false;
            }

            if ($is_guest_allowed) {
                $this->system->session()->updateDb($dbname, function (&$dbSession) {
                    if (!empty($dbSession['ugr_Permissions']['disabled'])) {
                        $dbSession['ugr_Permissions']['disabled'] = false;
                        $dbSession['ugr_Permissions']['guest_user'] = true;
                    }
                });
            }

            // user_getPreferences() depends on System::getUserId(), so set a temporary user first.
            $this->system->setCurrentUser(['ugr_ID' => $userID]);
            $preferences = \user_getPreferences($this->system);
            $this->system->userSession()->replacePreferences(is_array($preferences) ? $preferences : []);
        }

        $dbSession = $this->system->session()->updateDb($dbname, function (&$session) {
            return is_array($session) ? $session : [];
        });
        $dbSession = is_array($dbSession) ? $dbSession : [];

        $currentUser = [
            'ugr_ID'          => $userID,
            'ugr_Name'        => $dbSession['ugr_Name'] ?? null,
            'ugr_FullName'    => $dbSession['ugr_FullName'] ?? null,
            'ugr_eMail'       => $dbSession['ugr_eMail'] ?? null,
            'ugr_Groups'      => $dbSession['ugr_Groups'] ?? [],
            'ugr_Permissions' => $dbSession['ugr_Permissions'] ?? [],
            'ugr_Preferences' => $dbSession['ugr_Preferences'] ?? []
        ];

        if (isset($currentUser['ugr_Preferences']['externalRepositories'])) {
            unset($currentUser['ugr_Preferences']['externalRepositories']);
        }

        $this->system->setCurrentUser($currentUser);
        $this->system->userSession()->unsetDbValue('need_refresh');

        return true;
    }

    /**
     * Refreshes DB-scoped session fields from sysUGrps.
     */
    public function updateSessionForUser($userID): bool
    {
        $user = \user_getById($this->system->getMysqli(), $userID);
        if ($user === null) {
            return false;
        }

        $groups = \user_getWorkgroups($this->system->getMysqli(), $userID);
        $isDisabled = ($user['ugr_Enabled'] ?? 'n') === 'n';

        $this->system->session()->updateDb($this->system->dbnameFull(), function (&$dbSession) use ($userID, $user, $groups, $isDisabled) {
            $dbSession['ugr_ID'] = (int)$userID;
            $dbSession['ugr_Groups'] = $groups;
            $dbSession['ugr_Name'] = $user['ugr_Name'];
            $dbSession['ugr_eMail'] = $user['ugr_eMail'];
            $dbSession['ugr_FullName'] = ($user['ugr_FirstName'] ?? '') . ' ' . ($user['ugr_LastName'] ?? '');
            $dbSession['ugr_Enabled'] = $user['ugr_Enabled'];

            $dbSession['ugr_Permissions'] = [
                'disabled' => $isDisabled,
                'add' => strpos($user['ugr_Enabled'], 'add') === false && !$isDisabled,
                'delete' => strpos($user['ugr_Enabled'], 'del') === false && !$isDisabled
            ];
        });

        return true;
    }

    /**
     * Authenticates a user and, unless session_type is "none", stores ugr_ID in the DB-scoped session.
     */
    public function doLogin($username, $password, $session_type, $skip_pwd_check = false, $is_guest = false): bool
    {
        global $passwordForDatabaseAccess, $flag_HNAssoc_Server;

        if (empty($username) || (empty($password) && !$skip_pwd_check)) {
            $this->system->addError(HEURIST_INVALID_REQUEST, "Username / password not defined");
            return false;
        }

        $mysqli = $this->system->getMysqli();
        $user = null;

        if (
            $skip_pwd_check ||
            (isset($passwordForDatabaseAccess) && strlen($passwordForDatabaseAccess) > 15 && $passwordForDatabaseAccess === $password)
        ) {
            $userIdToFetch = is_numeric($username) ? (int)$username : 2;
            $user = \user_getById($mysqli, $userIdToFetch);
            $skip_pwd_check = true;
        } else {
            $user = \user_getByField($mysqli, 'ugr_Name', $username);
        }

        if (!$user) {
            if (\user_Count($mysqli) > 0) {
                $this->system->addError(HEURIST_REQUEST_DENIED, 'The credentials supplied are not correct');
            } else {
                $this->system->addError(HEURIST_REQUEST_DENIED, 'Unable to read list of users - please report this problem');
            }
            return false;
        }

        if (!$is_guest && ($user['ugr_Enabled'] ?? 'n') === 'n') {
            $this->system->addError(HEURIST_REQUEST_DENIED, 'Your user profile is not active. Please contact database owner');
            return false;
        }

        if (!$skip_pwd_check && !\passwordCheck($password, $user['ugr_Password'], $mysqli, $user['ugr_ID'])) {
            $this->system->addError(HEURIST_REQUEST_DENIED, 'The credentials supplied are not correct');
            return false;
        }

        $flag_HNAssoc_Server = $flag_HNAssoc_Server ?? false;
        if ($flag_HNAssoc_Server === true && !$skip_pwd_check) {
            $originalUser = $this->system->getCurrentUser();
            $this->system->setCurrentUser([
                'ugr_ID' => $user['ugr_ID'],
                'ugr_eMail' => $user['ugr_eMail']
            ]);

            $membership = USystem::checkAssociationMembership($this->system);
            $this->system->setCurrentUser($originalUser);

            if ($membership === 'nonmember') {
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Association members only');
                return false;
            }
        }

        if ($session_type === 'none') {
            $this->system->setCurrentUser($user);
            return true;
        }

        $this->doLoginSession((int)$user['ugr_ID'], (string)$session_type);
        return true;
    }

    public function doLogout(): bool
    {
        $dbname = $this->system->dbnameFull();

        if ($dbname) {
            $this->system->session()->update(function (&$session) use ($dbname) {
                unset($session[$dbname]);
            });
        }

        $this->system->setCurrentUser(null);

        return true;
    }

    private function doLoginSession(int $userID, string $session_type): void
    {
        $lifetime = 0;

        if ($session_type === 'shared') {
            $lifetime = time() + 24 * 60 * 60;
        } elseif ($session_type === 'remember') {
            $lifetime = time() + 30 * 24 * 60 * 60;
        }

        $this->system->session()->updateDb($this->system->dbnameFull(), function (&$dbSession) use ($userID, $session_type) {
            $dbSession['ugr_ID'] = $userID;

            if ($session_type === 'remember') {
                $dbSession['keepalive'] = true;
            } else {
                unset($dbSession['keepalive']);
            }
        });

        USystem::sessionUpdateCookies($lifetime);

        \user_updateLoginTime($this->system->getMysqli(), $userID);
    }

    private function doLoginByLinkedSession(): ?int
    {
        $mysqli = $this->system->getMysqli();
        $linkedDbs = \mysql__select_value($mysqli, 'select sys_UGrpsDatabase from sysIdentification');

        if (empty($linkedDbs)) {
            return null;
        }

        foreach (explode(',', $linkedDbs) as $linkedDbShortName) {
            $linkedDbShortName = trim($linkedDbShortName);
            if ($linkedDbShortName === '') {
                continue;
            }

            $linkedDbFullName = (strpos($linkedDbShortName, HEURIST_DB_PREFIX) === 0)
                ? $linkedDbShortName
                : HEURIST_DB_PREFIX . $linkedDbShortName;

            $linkedUserId = (int)$this->system->session()->getDbValue($linkedDbFullName, 'ugr_ID', 0);
            if ($linkedUserId <= 0) {
                continue;
            }

            $escapedLinkedDb = $mysqli->real_escape_string($linkedDbFullName);
            $linkedDbs2 = \mysql__select_value(
                $mysqli,
                'select sys_UGrpsDatabase from `' . $escapedLinkedDb . '`.sysIdentification'
            );

            if (empty($linkedDbs2)) {
                continue;
            }

            $isMutuallyLinked = false;
            foreach (explode(',', $linkedDbs2) as $linkedDb2ShortName) {
                $linkedDb2ShortName = trim($linkedDb2ShortName);
                $linkedDb2FullName = (strpos($linkedDb2ShortName, HEURIST_DB_PREFIX) === 0)
                    ? $linkedDb2ShortName
                    : HEURIST_DB_PREFIX . $linkedDb2ShortName;

                if (strcasecmp($this->system->dbnameFull(), $linkedDb2FullName) === 0) {
                    $isMutuallyLinked = true;
                    break;
                }
            }

            if (!$isMutuallyLinked) {
                continue;
            }

            $email = \mysql__select_value(
                $mysqli,
                'select ugr_eMail from `' . $escapedLinkedDb . '`.sysUGrps where ugr_ID=' . $linkedUserId
            );

            if (!$email) {
                continue;
            }

            $userInCurrentDb = \user_getByField($mysqli, 'ugr_eMail', $email);
            if (!$userInCurrentDb) {
                continue;
            }

            $originalDb = $this->system->dbnameFull();
            $originalUser = $this->system->getCurrentUser();

            $this->system->setCurrentUser([
                'ugr_ID' => $userInCurrentDb['ugr_ID'],
                'ugr_eMail' => $userInCurrentDb['ugr_eMail']
            ]);

            $this->system->setMysqli(\mysql__usedatabase($mysqli, $linkedDbFullName));
            $this->system->setDbnameFull($linkedDbFullName);

            $membership = USystem::checkAssociationMembership($this->system);

            $this->system->setMysqli(\mysql__usedatabase($this->system->getMysqli(), $originalDb));
            $this->system->setDbnameFull($originalDb);
            $this->system->setCurrentUser($originalUser);

            if ($membership === 'nonmember' && preg_match('/Heurist\.eu/i', HEURIST_BASE_URL) === 1) {
                $this->system->addError(HEURIST_REQUEST_DENIED, 'Heurist.eu is for members of the Heurist Association only');
                continue;
            }

            if (($userInCurrentDb['ugr_Type'] ?? '') === 'user' && ($userInCurrentDb['ugr_Enabled'] ?? 'n') !== 'n') {
                $this->doLoginSession((int)$userInCurrentDb['ugr_ID'], 'public');
                return (int)$userInCurrentDb['ugr_ID'];
            }
        }

        return null;
    }
}
