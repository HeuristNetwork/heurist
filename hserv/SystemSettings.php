<?php
/**
* SystemSettings.php - Class SystemSettings
* 
* This file defines the SystemSettings class, which is responsible for managing
* and providing access to various system configurations within the Heurist application.
* These settings can be stored in the database (sysIdentification table),
* in JSON files within the database's 'settings' directory (e.g., text_styles.json, webfonts.json),
* or in files located in the Heurist server's root directory (e.g., for JavaScript allowances, disk quotas).
*
* @project     Heurist academic knowledge management system
* @package Core
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

namespace hserv;

use hserv\utilities\USystem;

/**
* Class SystemSettings
* 
* Manages system-wide and database-specific settings for the Heurist application.
*
* This class provides methods to:
* - Retrieve settings stored in the `sysIdentification` database table.
* - Read and write settings from JSON files located in the database-specific `/settings` directory
*   (e.g., TinyMCE formats, web fonts, invalid URL patterns, user notifications).
* - Check server-level configurations like JavaScript execution allowance and disk quotas,
*   which are typically defined in files in the Heurist application's root directory.
*
* An instance of this class is usually accessed via the main `System` object.
* @package Core
*/
class SystemSettings {

    /**
     * Reference to the main System object.
     * @var \hserv\System
     */
    private $system;

    /**
     * Cached settings loaded from the `sysIdentification` table in the database.
     * Null until loaded by the `get()` method.
     * @var array|null
     */
    private $settingsInDb = null;

    /**
     * Defines a mapping between human-readable setting names and their corresponding JSON filenames
     * within the database's `/settings` directory.
     * e.g., 'TinyMCE formats' maps to 'text_styles.json'.
     * @var array<string, string>
     */
    private $settingsInFiles = [
        'TinyMCE formats' => 'text_styles.json', 
        'Webfonts' => 'webfonts.json',
        'Invalid URLs' => 'invalid_urls.json',
        'Notifications' => 'user_notifications.json',
        'Languages' => 'db_languages.json'
    ];

    /**
     * Constructor for SystemSettings.
     *
     * @param \hserv\System $system A reference to the main System object, used to access database connections and other system functionalities.
     */
    public function __construct( \hserv\System $system ) {
        $this->system = $system;
    }

    /**
     * Constructs the full path to a specific setting's JSON file.
     *
     * @param string $setting_name The human-readable name of the setting (must be a key in `$this->settingsInFiles`).
     * @return string The absolute path to the setting file. Returns an empty string if `settingsInFiles` does not contain `$setting_name`.
     */
    private function getSettingsFileName($setting_name)
    {
        if (!isset($this->settingsInFiles[$setting_name])) {
            return ''; // Or handle error: setting name not configured
        }
        $settingsDir = $this->system->getSysDir('settings');
        if (empty($settingsDir)) {
             // Error logged by getSysDir if dbname is invalid, or System not fully initialized.
            return '';
        }
        return $settingsDir . $this->settingsInFiles[$setting_name];
    }

    /**
     * Validates if a given setting name is a configured file-based setting and if the filestore is accessible.
     *
     * @param string $setting_name The human-readable name of the setting.
     * @return bool True if the setting name is valid and filestore is defined, false otherwise.
     */
    private function isValidParam($setting_name){

        if(!defined('HEURIST_FILESTORE_ROOT')){ // Checks if the primary filestore path constant is set
            $this->system->addError(HEURIST_SYSTEM_CONFIG, 'HEURIST_FILESTORE_ROOT is not defined. Cannot access file-based settings.');
            return false;
        }

        if(!array_key_exists($setting_name, $this->settingsInFiles)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid settings requested: ' . htmlspecialchars($setting_name));
            return false;
        }

        return true;
    }

    /**
     * Reads and decodes a JSON settings file for a given setting name.
     *
     * @param string $setting_name The human-readable name of the setting (must be a key in `$this->settingsInFiles`).
     * @return array|false An associative array of settings if successful, an empty array if the file is empty or not found,
     *                     or false on a read or JSON decode error (an error will be added to the System object).
     */
    private function readSettings($setting_name){

        $setting_file = $this->getSettingsFileName($setting_name);

        $settings = array();

        if(!empty($setting_file) && file_exists($setting_file)){

            $settings = file_get_contents($setting_file);
            if($settings === false){
                $this->system->addError(HEURIST_ERROR, "An error occurred while attempting to read database settings for $setting_name");
            }elseif(empty($settings)){
                $settings = array();
            }else{
                $settings = json_decode($settings, true);
                if(json_last_error() !== JSON_ERROR_NONE){
                    $this->system->addError(HEURIST_ERROR, "An error occurred while decoding the existing database settings for $setting_name");
                    return false;
                }
            }
        }
        return $settings;
    }

    /**
    * Retrieves settings for the current database that are stored in a JSON file within the 'settings/' directory.
    *
    * @param string $setting_name The human-readable name of the setting. This must match a key in the
    *                             `$this->settingsInFiles` array (e.g., 'TinyMCE formats', 'Webfonts').
    * @return array|false Returns an associative array of the settings if the file exists and is successfully decoded.
    *                     Returns an empty array if the file doesn't exist or is empty.
    *                     Returns false if the `$setting_name` is invalid, or if there's an error reading or decoding the file
    *                     (in which case an error is also added to the System object).
    */
    public function getDatabaseSetting($setting_name){

        if(!$this->isValidParam($setting_name)){ // Logs error if invalid
            return false;
        }

        return $this->readSettings($setting_name); // Logs error if read/decode fails
    }

    /**
    * Saves settings for the current database to a JSON file in the 'settings/' directory.
    *
    * @param string $setting_name The human-readable name of the setting (must be a key in `$this->settingsInFiles`).
    * @param array  $settings The settings to save, as an associative array. This will be JSON encoded.
    * @param int    $replace_settings Optional. Defines how to handle existing settings:
    *                                 - `0` (default): Completely replace existing settings with the new ones.
    *                                 - `1`: Merge new settings into existing ones, with new values replacing existing ones if keys conflict (recursive merge).
    *                                 - `2`: Merge new settings into existing ones, but retain existing values if keys conflict (new values are only added if key doesn't exist).
    * @return bool True on successful save, false otherwise (an error will be added to the System object).
    */
    public function setDatabaseSetting($setting_name, $settings, $replace_settings = 0){

        // isValidParam is implicitly called by getDatabaseSetting or getSettingsFileName
        $existing_settings = $this->getDatabaseSetting($setting_name);

        // If getDatabaseSetting returned false due to an error (e.g., invalid param, read error),
        // and we are in a merge mode, we should not proceed.
        if($existing_settings === false && $replace_settings > 0){
            // An error would have already been logged by getDatabaseSetting or its sub-methods.
            // Explicitly state that merge cannot happen if initial load failed.
            $this->system->addError(HEURIST_ERROR, "Cannot merge settings for '$setting_name' because existing settings could not be loaded.");
            return false;
        }

        if(!is_array($settings)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid settings format provided for ' . htmlspecialchars($setting_name));
            return false;
        }
        
        // If existing_settings is false (due to read error on an existing file for example, but not simple non-existence for $replace_settings=0)
        // it should ideally not proceed with a merge. However, original logic implies empty array for non-existent.
        // getDatabaseSetting returns empty array for non-existent file, false for error.
        // If $existing_settings is false, it means an error occurred, so we should stop.
        if ($existing_settings === false && $replace_settings > 0) {
             $this->system->addError(HEURIST_ERROR, "Cannot merge settings for '".htmlspecialchars($setting_name)."' as existing settings could not be definitively read.");
             return false;
        }
        // If file didn't exist, $existing_settings will be an empty array.
        if ($existing_settings === false) $existing_settings = [];


        $merged_settings = [];
        if($replace_settings === 0 || isEmptyArray($existing_settings) ){
            $merged_settings = $settings;
        }elseif($replace_settings === 1){ // Merge and replace existing keys
            $merged_settings = array_replace_recursive($existing_settings, $settings);
        }else{ // $replace_settings === 2, Merge and retain existing keys
            $merged_settings = array_replace_recursive($settings, $existing_settings);
        }

        $setting_file = $this->getSettingsFileName($setting_name);
        if (empty($setting_file)) {
            // Error should have been logged by isValidParam if $setting_name was the issue.
            // This could happen if getSysDir failed more subtly.
            $this->system->addError(HEURIST_ERROR, "Cannot determine file path for settings: " . htmlspecialchars($setting_name));
            return false;
        }

        $final_settings_json = json_encode($merged_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if(json_last_error() !== JSON_ERROR_NONE){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'JSON ENCODE ERROR for ' . htmlspecialchars($setting_name) . ': ' . json_last_error_msg());
            return false;
        }
        
        // fileSave is assumed to be a global helper function.
        // It should return bytes written or false/0 on failure.
        if(fileSave($final_settings_json, $setting_file) === 0){ // Check explicitly for 0 or false if fileSave can return that.
            $this->system->addError(HEURIST_ERROR, "An error occurred while attempting to save database settings for " . htmlspecialchars($setting_name));
            return false;
        }

        return true;
    }


    /**
     * Generates CSS for embedding web fonts defined in the 'Webfonts' database setting.
     * It constructs `@font-face` rules for locally specified fonts and includes `@import` rules directly.
     * It also prepends a `font-family` style rule for `body` and common UI elements to use these fonts.
     *
     * @param string|null $default_family Optional. A default font family name to append to the list of web fonts
     *                                    in the main `font-family` CSS rule. E.g., "sans-serif".
     * @return string The generated CSS string. Returns an empty string if no web fonts are configured or if settings cannot be read.
     */
    public function getWebFontsLinks($default_family=null){

        $webfonts = $this->getDatabaseSetting('Webfonts'); // This will be an array or false
        if ($webfonts === false || isEmptyArray($webfonts)) {
            return ''; // No webfonts configured or error reading them
        }

        $settingsURL = $this->system->getSysUrl('settings');
        if (empty($settingsURL)) {
            $this->system->addError(HEURIST_SYSTEM_CONFIG, "Cannot get settings URL, webfonts may not load correctly.");
            // Proceeding might generate incorrect URLs, but original logic did not stop.
        }

        $font_styles_rules = '';
        $font_families_list = [];

        foreach($webfonts as $font_family_name => $src_value){
            // Sanitize font_family_name if it's used directly in CSS without quotes, though quotes are used here.
            $escaped_font_family_name = htmlspecialchars($font_family_name, ENT_QUOTES);

            // Ensure $settingsURL ends with a slash if it's not empty.
            $actual_settings_url = !empty($settingsURL) ? rtrim($settingsURL, '/') . '/' : '';
            
            $processed_src = str_replace("url('settings/", "url('" . $actual_settings_url, $src_value);
            
            if(strpos($processed_src,'@import') === 0){
                $font_styles_rules .= $processed_src . ';'; // Ensure statement terminator
            } else {
                $font_styles_rules .= ' @font-face {font-family:"'.$escaped_font_family_name.'";src:'.$processed_src.';} ';
            }
            $font_families_list[] = '"' . $escaped_font_family_name . '"'; // Quote family names for the list
        }

        $final_css = '';
        if(!empty($font_families_list)){
            if($default_family !== null){
                // Sanitize default_family as well, assuming it might be a generic family like 'sans-serif' or a specific quoted name.
                // For simplicity, if it contains spaces or special chars, it should be quoted by the caller or handled carefully.
                $font_families_list[] = $default_family;
            }
            $final_css = 'body,.ui-widget,.ui-widget input,.ui-widget textarea,.ui-widget select{font-family: '
                            .implode(',',$font_families_list).';} ';
        }
        $final_css .= $font_styles_rules;

        return trim($final_css);
    }

    /**
     * Loads system settings from the `sysIdentification` table in the database.
     * These are typically global settings for the Heurist database instance.
     * Results are cached internally; use `$need_reset` to force a reload.
     *
     * @param string|null $fieldname Optional. If provided, returns the value of this specific setting field.
     *                               If null (default), returns an associative array of all settings from `sysIdentification`.
     * @param bool $need_reset Optional. If true, forces a reload of settings from the database, clearing any cached values.
     *                         Defaults to false.
     * @return mixed|null An array of all settings, the value of a specific setting, or null if settings cannot be loaded
     *                    or if a specific fieldname is requested but not found (an error will be added to System object on load failure).
     */
    public function get( $fieldname=null, $need_reset = false ){

        if($this->settingsInDb === null || $need_reset) // Check specifically for null for initial load
        {
            //load from database

            $mysqli = $this->system->getMysqli();
            $this->settingsInDb = getSysValues($mysqli);

            if(!$this->settingsInDb){
                //HEURIST_SYSTEM_FATAL
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to read sysIdentification', $mysqli->error);
                return null;
            }
        }

        //returns all or particular value
        return ($fieldname) ?@$this->settingsInDb[$fieldname] :$this->settingsInDb;
    }

    /**
     * Checks if custom JavaScript execution is allowed for the current database
     * in contexts like Smarty reports and CMS content.
     *
     * This is determined by the presence of the current database's short name
     * in a file named `js_in_database_authorised.txt`, expected to be located
     * two directories above the directory of the current SystemSettings.php file
     * (i.e., in the Heurist application's root server folder).
     *
     * @return bool True if JavaScript is allowed for the current database, false otherwise.
     */
    public function isJavaScriptAllowed(){

        $is_allowed = false;
        // Construct path relative to the application root, assuming __DIR__ is hserv/
        $auth_file_path = realpath(__DIR__ . "/../../js_in_database_authorised.txt");

        if($auth_file_path !== false && file_exists($auth_file_path)){
            $current_dbname = $this->system->dbname();
            if (empty($current_dbname)) {
                // Cannot check if dbname is not available.
                return false;
            }

            $handle = @fopen($auth_file_path, "r");
            if ($handle) {
                while (($line = fgets($handle, 256)) !== false) { // Increased buffer slightly for safety
                    if(trim($line) === $current_dbname){
                        $is_allowed = true;
                        break;
                    }
                }
                fclose($handle);
            } else {
                // Log error: Could not open the authorization file
                error_log("isJavaScriptAllowed: Could not open js_in_database_authorised.txt at " . $auth_file_path);
            }
        }
        return $is_allowed;
    }

    /**
     * Retrieves the allowed disk quota in bytes for the current database.
     * This quota typically applies to combined usage of 'file_uploads' and 'uploaded_tilestacks'.
     *
     * The quota is read from a file named `disk_quota_allowances.txt`, expected to be
     * located two directories above the directory of the current SystemSettings.php file
     * (i.e., in the Heurist application's root server folder).
     * Each line in this file should be in the format: `databasename allowance` (e.g., `mydbase 10GB`).
     *
     * @return int The disk quota in bytes. Returns 0 if no specific quota is found for the database
     *             or if the allowances file cannot be read. A value of 0 might mean no limit or no allowance,
     *             depending on application logic.
     */
    public function getDiskQuota(){

        $quota_bytes = 0;
        // Construct path relative to the application root
        $quota_file_path = realpath(__DIR__ . "/../../disk_quota_allowances.txt");

        if($quota_file_path !== false && file_exists($quota_file_path)){
            $current_dbname = $this->system->dbname();
            if (empty($current_dbname)) {
                // Cannot get quota if dbname is not available.
                return 0;
            }

            $handle = @fopen($quota_file_path, "r");
            if ($handle) {
                while (($line = fgets($handle, 256)) !== false) { // Increased buffer
                    $trimmed_line = trim($line);
                    // Check if line starts with dbname followed by a space
                    if(strpos($trimmed_line, $current_dbname . ' ') === 0){
                        $allowance_str = trim(substr($trimmed_line, strlen($current_dbname)));
                        // USystem::getConfigBytes is assumed to handle parsing strings like "10GB", "500M" into bytes.
                        // The first parameter to getConfigBytes was null in original, assuming it might be for a PHP ini setting name.
                        $quota_bytes = USystem::getConfigBytes(null, $allowance_str);
                        break;
                    }
                }
                fclose($handle);
            } else {
                // Log error: Could not open the quota file
                error_log("getDiskQuota: Could not open disk_quota_allowances.txt at " . $quota_file_path);
            }
        }

        // Ensure it's a positive integer, default to 0 if not or if conversion failed.
        if(!isPositiveInt($quota_bytes)){
            $quota_bytes = 0;
            // Default: $quota_bytes = 10737418240; //10GB
        }
        return $quota_bytes;
    }
}
