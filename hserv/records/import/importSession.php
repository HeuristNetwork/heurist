<?php
/**
* importSession.php - ImportSession
* 
* Methods to work with import session table and import tables.
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\records\import
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/**
 * Class ImportSession
 *
 * Provides static methods to manage import session data. Import sessions store
 * information about an ongoing or completed import process, such as the temporary
 * data table name, column mappings, record counts, and processing state. This data
 * is persisted in the `sysImportFiles` database table, typically with detailed
 * information stored as a JSON string in the `sif_ProcessingInfo` column.
 *
 */
class ImportSession {

    /**
     * @var \hserv\System|null The Heurist system object.
     */
    private static $system = null;
    /**
     * @var \mysqli|null The mysqli database connection object.
     */
    private static $mysqli = null;
    /**
     * @var bool Flag indicating if the class has been initialized.
     */
    private static $initialized = false;

    /**
     * Initializes the class with the global Heurist system object.
     *
     * Ensures that essential static properties like `$system` and `$mysqli` are set.
     * This method is called internally by other static methods if the class
     * hasn't been initialized yet.
     */
private static function initialize()
{
    if (self::$initialized)  {return;}

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->getMysqli();
    self::$initialized = true;
}

    /**
     * Loads import session data from the `sysImportFiles` table by its ID.
     *
     * The core session data is stored as a JSON string in the `sif_ProcessingInfo` column,
     * which is decoded by this method. The temporary data table name (`sif_TempDataTable`)
     * is also retrieved and added to the resulting session array.
     *
     * @param int $import_id The ID of the import session to load (sif_ID).
     * @return array|false An associative array containing the decoded session data,
     *                     including 'import_id' and 'import_file' (or 'import_table' for backward compatibility),
     *                     or `false` if the session is not found or `$import_id` is invalid.
     *                     Errors are added to `$this->system`.
     */
public static function load($import_id){

    self::initialize();

    if($import_id && is_numeric($import_id)){

        $res = mysql__select_row(self::$mysqli,
            "select sif_ProcessingInfo , sif_TempDataTable from sysImportFiles where sif_ID=".intval($import_id));

        $session = json_decode($res[0], true);
        $session["import_id"] = $import_id;
        $session["import_file"] = $res[1];
        if(!@$session["import_table"]){ //backward capability
            $session["import_table"] = $res[1];
        }

        return $session;
    }else{
        self::$system->addError(HEURIST_NOT_FOUND, 'Import session #'.$import_id.' not found');
        return false;
    }
}

/**
* update record in import session table
*
* @param array $imp_session The import session data array to be saved.
*                           This array will be JSON encoded and stored in `sif_ProcessingInfo`.
*                           It should contain keys like 'import_name' (used for `sif_TempDataTable`).
*                           The 'import_id' key will be used to determine if it's an update or insert.
* @return array|string The updated `$imp_session` array (with `import_id` potentially newly assigned
*                      if it was an insert) on success. On failure, returns the error code/message
*                      from `mysql__insertupdate`.
*/
    /**
     * Saves or updates an import session's data in the `sysImportFiles` table.
     *
     * The provided `$imp_session` array is JSON encoded and stored in the `sif_ProcessingInfo` column.
     * The `sif_TempDataTable` is set from `$imp_session["import_name"]`.
     * If `$imp_session["import_id"]` is set, it updates the existing session; otherwise, it creates a new one.
     *
     * @param array $imp_session The associative array of session data to save.
     * @return array|string The updated `$imp_session` array (including the `import_id`) on success,
     *                      or an error string/code from `mysql__insertupdate` on database error.
     */
public static function save($imp_session){

    self::initialize();

    $imp_id = mysql__insertupdate(self::$mysqli, "sysImportFiles", "sif",
        array("sif_ID"=>@$imp_session["import_id"],
            "sif_UGrpID"=>self::$system->getUserId(),
            "sif_TempDataTable"=>$imp_session["import_name"],
            "sif_ProcessingInfo"=>json_encode($imp_session) ));

    if(intval($imp_id)<1){
        return $imp_id;
    }else{
        $imp_session["import_id"] = $imp_id;
        return $imp_session;
    }
}


//
    /**
     * Sets the primary record type for an import session or retrieves its structure tree.
     *
     * - If `$sequence` data is provided: It loads the import session specified by `$imp_ID`,
     *   updates its 'primary_rectype' and 'sequence' information, and saves the session back.
     * - If `$sequence` is null: It calls `dbs_GetRectypeStructureTree` to get a hierarchical
     *   structure of the specified record type (`$rty_ID`), likely for display in the UI
     *   to show dependent types or for field mapping selection.
     *
     * @param int $imp_ID The ID of the import session (used when updating the session).
     * @param int $rty_ID The Heurist record type ID to set as primary or for which to get the structure tree.
     * @param array|null $sequence If provided, this array contains new sequence information to be saved
     *                             in the import session. If null, the method retrieves the structure tree.
     * @return string|array|false Returns 'ok' if the session was successfully updated.
     *                            Returns the record type structure tree (array) if `$sequence` was null and successful.
     *                            Returns `false` on any error (e.g., session not found, DB error).
     *                            Errors are added to `$this->system`.
     */
public static function setPrimaryRectype($imp_ID, $rty_ID, $sequence){

     self::initialize();

     if($sequence!=null){
        //get session
        $imp_session = self::load($imp_ID);
        if($imp_session==false){
                return false;
        }
        //save session with new ID
        $imp_session['primary_rectype'] = $rty_ID;
        $imp_session['sequence'] = $sequence;
        $res = self::save($imp_session);
        if(!is_array($res)){
            self::$system->addError(HEURIST_DB_ERROR, 'Cannot save import session #'.$imp_ID, $res);
            return false;
        }

        return 'ok';
     }else{
        //get dependent record types
        try{
            return dbs_GetRectypeStructureTree(self::$system, $rty_ID, 6, 'resource');//?? 6
        }catch(Exception $e){
            $sMsg = $e->getCode().' ('.get_class($e).'): '.$e->getMessage();
            self::$system->addError(HEURIST_ERROR, 'Cannot generate structure tree for record type '.$rty_ID.' session #'.$imp_ID, $sMsg);
            return false;
        }


     }
}


//
    /**
     * Retrieves field mapping samples from previous import sessions for a given record type.
     *
     * This method queries the `sysImportFiles` table for all import sessions (excluding the current `$imp_ID`),
     * decodes their `sif_ProcessingInfo`, and looks for sequences that match the specified `$rty_ID`.
     * If a match is found and that sequence contains field mapping information (`mapping_flds`),
     * it's added to the results. This is useful for suggesting mappings to the user based on past imports.
     *
     * @param int $imp_ID The ID of the current import session (to exclude it from the search).
     * @param int $rty_ID The Heurist record type ID for which to find mapping samples.
     * @return array An associative array where keys are the names of past import sessions (`import_name`)
     *               and values are the `mapping_flds` arrays from those sessions that matched the `$rty_ID`.
     */
public static function getMatchingSamples($imp_ID, $rty_ID){

     self::initialize();

     $matching = array();

     if(!($imp_ID>0)) {$imp_ID = 0;}

     $sessions = mysql__select_assoc2(self::$mysqli, 'select sif_ID, sif_ProcessingInfo from sysImportFiles where sif_ID!='.$imp_ID);

     foreach($sessions as $id=>$imp_session){

        $imp_session = json_decode($imp_session, true);
        if($imp_session!==false && is_array(@$imp_session['sequence'])){

            foreach($imp_session['sequence'] as $seq){

                if($seq['rectype']==$rty_ID && !isEmptyArray(@$seq['mapping_flds'])){
                    $matching[ $imp_session['import_name'] ] = $seq['mapping_flds'];
                    break;
                }
            }
        }
     }

     return $matching;
}



/**
* load records from import table
*
* @param string $import_table The name of the temporary import table.
* @param array $imp_ids An array of `imp_id` values (row IDs from the import table) to retrieve.
* @return array|null The first row found that matches any of the provided `imp_id`s,
*                    or `null` if no matching row is found.
*                    Note: Despite accepting multiple `imp_ids`, this method, due to `mysql__select_row`,
*                    will only return the first matching row.
*/
    /**
     * Retrieves the first matching row from a temporary import table by a list of import row IDs.
     *
     * Note: Although the query can match multiple `imp_id`s, this function uses `mysql__select_row`
     * which will only return the data for the first row found.
     *
     * @param string $import_table The name of the temporary import table.
     * @param array $imp_ids An array of `imp_id`s (row IDs in the import table).
     * @return array|null The first row found as an associative array, or null if no match.
     */
public static function getRecordsFromImportTable1( $import_table, $imp_ids) {

    self::initialize();

    $mysqli = self::$system->getMysqli();

    $imp_ids = prepareIds($imp_ids);

    $query = 'SELECT * FROM `'.$import_table.'` WHERE imp_id IN ('. implode( ',', $imp_ids ) .')';
    $res = mysql__select_row($mysqli, $query);

    return $res;
}

//
    /**
     * Retrieves records from a temporary import table with filtering, field selection, and pagination.
     *
     * This method allows fetching data from an import table for display or further processing,
     * typically for previewing records that are marked for insert, update, or all records.
     *
     * - Filtering: Based on `$mode` ('insert', 'update', 'all') and the values in `$id_field` column
     *   (negative for insert, positive for update).
     * - Field Selection: If `$mapping` is provided, it selects only the mapped fields. Otherwise, selects all (`*`).
     *   The `$id_field` is usually included for ordering.
     * - Pagination: Uses `$limit` and `$offset`.
     * - Output format hint: `$output` ('csv' or other) influences how many details might be fetched by `mysql__select_all`
     *   (though its direct effect here is minimal beyond potentially fetching slightly more data for non-csv).
     *
     * @param string $import_table The name of the temporary import table.
     * @param string|null $id_field The name of the column in the import table that stores assigned Heurist record IDs.
     *                              Used for filtering by `$mode` and for ordering.
     * @param string $mode Filter mode: 'insert' (negative/null IDs), 'update' (positive IDs), or 'all'.
     * @param string|array|null $mapping JSON string or array defining field mappings. If provided,
     *                                   only mapped fields are selected. Keys are import column indices.
     * @param int $offset The offset for pagination.
     * @param int $limit The maximum number of records to return. Default 100.
     * @param string $output Output format hint (e.g., 'csv'). Primarily affects detail level in `mysql__select_all`.
     * @return array|null An array of associative arrays representing the fetched rows, or null on error.
     */
public static function getRecordsFromImportTable2( $import_table, $id_field, $mode, $mapping, $offset, $limit=100, $output ){

    self::initialize();

    $mysqli = self::$system->getMysqli();

    if($id_field==null || $id_field=='' || $id_field=='null' || $mode=='all'){
        $where  = '1';
        $order_field = 'imp_id';
    }elseif($mode=='insert'){
        $where  = " ($id_field<0 OR $id_field IS NULL) ";
        $order_field = $id_field;
    }else{
        $where  = " ($id_field>0) ";
        $order_field = $id_field;
    }

    if(!($offset>0)) {$offset = 0;}
    if(!is_int($limit)) {$limit = 100;}

    if($mapping!=null && !is_array($mapping)){
        $mapping = json_decode($mapping, true);
    }

    if(!isEmptyArray($mapping)){


        $field_idx = array_keys($mapping);

        $sel_fields = array($order_field);

        foreach($field_idx as $idx){
            if('field_'.$idx!=$id_field){
                array_push($sel_fields, 'field_'.$idx);
            }
        }
        if($mode=='insert' && count($sel_fields)>1){
            $order_field = $sel_fields[1];
        }

        $sel_fields = 'DISTINCT '.implode(',',$sel_fields);
    }else{
        $sel_fields = '*';
    }


    $query = "SELECT $sel_fields FROM $import_table WHERE $where ORDER BY $order_field";
    if($limit>0){
        $query = $query." LIMIT $limit OFFSET $offset";
    }


    $res = mysql__select_all($mysqli, $query, 0, ($output=='csv'?0:30) );
    return $res;
}



} //end class
?>
