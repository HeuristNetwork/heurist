<?php
/**
* DbSysIdentification.php - Class DbSysIdentification
*
* Operations for the `sysIdentification` table.
*
* @project     Heurist academic knowledge management system
* @package Entity 
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0

*/
namespace hserv\entity;
use hserv\entity\DbEntityBase;

/**
* Class DbSysIdentification
*
* Provides database access and operations for the `sysIdentification` table.
* This table stores a single row of database-specific properties and settings,
* such as its name, version, owner, and various configuration options.
*
*/
class DbSysIdentification extends DbEntityBase
{

    /**
     * Retrieves the single record from the `sysIdentification` table.
     *
     * This table contains database-specific properties and settings. This method
     * directly queries the table (expecting only one row) and formats the result
     * to mimic a standard Heurist search result structure.
     *
     * It adds a calculated field `sys_dbVersion` to the result, which is a concatenation of
     * `sys_dbVersion`, `sys_dbSubVersion`, and `sys_dbMinorVersion` from the table.
     *
     * Note: This method does not use `parent::search()` or the `DbEntitySearch` manager
     * as it targets a single, specific row.
     *
     * @return array|false An array structured like a search result:
     *                     - `count`: 1 if the record is found.
     *                     - `reccount`: 1 if the record is found.
     *                     - `fields`: Array of field names from `sysIdentification` plus 'sys_dbVersion'.
     *                     - `records`: An associative array where the key is the `sys_ID` (typically 1)
     *                       and the value is a numerically indexed array of the record's values.
     *                     - `order`: The `sys_ID` of the record.
     *                     - `entityName`: 'sysIdentification'.
     *                     Returns `false` if there's a database query error.
     */
    public function search(){

        $query = 'SELECT * FROM sysIdentification LIMIT 1';

        $mysqli = $this->system->getMysqli();
        $res = $mysqli->query($query);

        if (!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'Search error', $mysqli->error);
            return false;
        }

        // read all field names
        $_flds =  $res->fetch_fields();
        $fields = array();
        foreach($_flds as $fld){
            array_push($fields, $fld->name);
        }
        array_push($fields, 'sys_dbVersion');

        $records = array();
        $order = array();
        // load record
        $row = $res->fetch_row();
        if($row){
            $row[] = $row[2].'.'.$row[3].'.'.$row[4];//sys_dbVersion
            $records[$row[0]] = $row;
            $order =  $row[0];
        }
        $res->close();


        $response = array(
                'count'=>1,
                'reccount'=>1,
                'fields'=>$fields,
                'records'=>$records,
                'order'=>$order,
                'entityName'=>'sysIdentification');
        return $response;
    }

    /**
     * Saves the `sysIdentification` record.
     *
     * Before calling `parent::save()`, it checks if the `sys_ExternalReferenceLookups` column
     * exists in the `sysIdentification` table and attempts to add it if missing.
     * After saving, it handles the `sys_Thumb` image by renaming any temporary file.
     *
     * @return array|false The result from `parent::save()` (array of saved IDs, typically just one, or false).
     */
    public function save(){


        //add new field into sysIdentification
        $sysValues = $this->system->settings->get();
        if(!array_key_exists('sys_ExternalReferenceLookups', $sysValues))
        {
            $query = "ALTER TABLE `sysIdentification` ADD COLUMN `sys_ExternalReferenceLookups` TEXT default NULL COMMENT 'Record type-function-field specifications for lookup to external reference sources such as GeoNames'";
            $mysqli = $this->system->getMysqli();
            $res = $mysqli->query($query);
        }


        $ret = parent::save();

        if($ret!==false){
            //copy temporary file
            foreach($this->records as $idx=>$record){
                $sys_ID = @$record['sys_ID'];
                if($sys_ID>0 && in_array($sys_ID, $ret)){
                    //treat database image
                    $thumb_file_name = @$record['sys_Thumb'];
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $sys_ID);
                    }
                }
            }
        }
        return $ret;
    }
    //
    // deletion not allowed for db properties
    //
    /**
     * Disables deletion of the `sysIdentification` record.
     *
     * This record is essential for database operation and should not be deleted.
     *
     * @param bool $disable_foreign_checks Unused.
     * @return false Always returns false.
     */
    public function delete($disable_foreign_checks = false){
        //virtual method
        return false;
    }


}
?>
