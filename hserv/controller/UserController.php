<?php
/**
* UserController.php - User preference controller
*
* Provides class-based user preference actions intended to replace the relevant
* legacy usr_info.php operations over time.
*
* @project     Heurist academic knowledge management system
* @package     Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/
namespace hserv\controller;

use hserv\System;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';

/**
 * Handles authenticated user preference operations.
 *
 * The controller keeps the existing preference persistence contract: values
 * are stored in `sysUGrps.ugr_Preferences` and reflected in the per-database
 * user session.
 */
class UserController
{
    /** @var System */
    private $system;

    /** @var array */
    private $req_params;

    /**
     * Initialise the user controller.
     *
     * @param System $system Initialised Heurist system context.
     * @param array|null $params Sanitised request parameters.
     */
    public function __construct($system, $params = null)
    {
        $this->system = $system;
        $this->req_params = is_array($params) ? $params : USanitize::sanitizeInputArray();
    }

    /**
     * Dispatch supported user actions.
     *
     * Supported actions: save_prefs, get_prefs.
     *
     * @param string|null $action Requested action.
     * @return void
     */
    public function handleRequest($action): void
    {
        $result = false;

        try {
            $this->requireAuthenticatedUser();

            switch($action){
                case 'save_prefs':
                    $result = $this->savePreferences();
                    break;

                case 'get_prefs':
                    $result = $this->getPreferences();
                    break;

                default:
                    throw new \Exception('Invalid "action" parameter');
            }
        } catch (\Throwable $e) {
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
            $result = false;
        }

        $this->system->session()->close();

        if(is_bool($result) && $result === false){
            dataOutput($this->system->getError());
        }else{
            dataOutput(['status' => HEURIST_OK, 'data' => $result]);
        }
    }

    /**
     * Save one or more preference values.
     *
     * Two request forms are supported:
     * - `prefs`: associative array or JSON object merged into current preferences.
     * - `key` + `value`: saves one named preference only. JSON object/array values
     *   may be supplied as strings and are decoded before persistence.
     *
     * @return mixed Updated preferences, or the saved keyed value when `key` is used.
     */
    public function savePreferences()
    {
        $key = trim((string)($this->req_params['key'] ?? ''));

        if($key !== ''){
            if(!array_key_exists('value', $this->req_params)){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Parameter "value" is required when "key" is specified');
                return false;
            }

            [$isValid, $value] = $this->decodePreferenceValue($this->req_params['value']);
            if(!$isValid){
                return false;
            }

            user_setPreferences($this->system, [$key => $value]);
            return $this->system->userSession()->getPreference($key);
        }

        $prefs = $this->req_params['prefs'] ?? null;
        if(is_string($prefs)){
            $prefs = json_decode($prefs, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid preferences JSON');
                return false;
            }
        }
        if(!is_array($prefs)){
            $this->system->addError(HEURIST_INVALID_REQUEST,
                'Provide either "prefs" as a preferences object or "key" with "value"');
            return false;
        }

        user_setPreferences($this->system, $prefs);
        return $this->system->userSession()->getPreferences();
    }

    /**
     * Return current user preferences.
     *
     * Optional request parameter `key` returns only one preference value. The
     * database-backed helper is used to preserve the legacy preference/default
     * behaviour, and the session copy is refreshed from that result.
     *
     * @return mixed Preference collection or one requested value.
     */
    public function getPreferences()
    {
        $prefs = user_getPreferences($this->system);
        if(!is_array($prefs)){
            $prefs = [];
        }
        $this->system->userSession()->replacePreferences($prefs);

        $key = trim((string)($this->req_params['key'] ?? ''));
        if($key !== ''){
            return $prefs[$key] ?? null;
        }
        return $prefs;
    }


    /**
     * Decode a keyed preference value when it contains a JSON object or array.
     * Scalar strings are intentionally preserved unchanged.
     *
     * @param mixed $value Preference value from the request.
     * @return array{0:bool,1:mixed} Validation flag and decoded/original value.
     */
    private function decodePreferenceValue($value): array
    {
        if(!is_string($value)){
            return [true, $value];
        }

        $trimmed = trim($value);
        if($trimmed === ''){
            return [true, $value];
        }

        $first = $trimmed[0];
        if($first !== '{' && $first !== '['){
            return [true, $value];
        }

        $decoded = json_decode($trimmed, true);
        if(json_last_error() !== JSON_ERROR_NONE){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid JSON in preference "value"');
            return [false, null];
        }

        return [true, $decoded];
    }

    /** Require the current database user to be authenticated. */
    private function requireAuthenticatedUser(): void
    {
        if($this->system->getUserId() < 1
            || $this->system->authSession()->verifyCredentials($this->system->dbname()) < 1){
            throw new \Exception('Authentication is required');
        }
    }
}
