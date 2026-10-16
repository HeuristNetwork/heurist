<?php
/**
* DbSysDatabases.php - Class DbSysDatabases
*
* Functionality to list databases accessible to a user.
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
* Class DbSysDatabases
*
* This class provides functionality to list databases accessible to a user.
* It does not directly interact with a `sysDatabases` table for CRUD operations
* in the same way other DbEntityBase subclasses do. Instead, its `search()` method
* utilizes `mysql__getdatabases4` to retrieve a list of databases based on
* server-level access and user roles (filtered by email if provided).
* Direct save and delete operations are disabled for this entity.
*
*/
class DbSysDatabases extends DbEntityBase
{
    /**
     * Lists databases accessible to the current user, optionally filtered by email.
     *
     * This method does not perform a typical entity search using `DbEntitySearch`.
     * Instead, it calls the global function `mysql__getdatabases4` to retrieve a list
     * of database names. If `$this->data['ugr_eMail']` is provided, it's used to
     * filter the list of databases to those accessible by the user with that email.
     *
     * The returned array is structured to mimic the format of a standard Heurist search result,
     * containing keys like 'records', 'count', 'fields', etc. The 'records' themselves
     * are associative arrays where the key is the database name and the value is an array
     * containing just the database name.
     *
     * @return array An array structured like a search result:
     *               - `queryid`: From `$this->data['request_id']`.
     *               - `entityName`: The name of this entity (`sysDatabases`).
     *               - `pageno`: From `$this->data['pageno']`.
     *               - `offset`: From `$this->data['offset']`.
     *               - `count`: Total number of accessible databases found.
     *               - `reccount`: Number of databases returned (same as `count`).
     *               - `records`: An associative array of database entries (e.g., `['dbname' => ['dbname']]`).
     *               - `order`: A simple array of database names in the order they were retrieved.
     *               - `fields`: An array containing a single string: `['sys_Database']`.
     */
    public function search(){

        //compose WHERE
        $email_filter = '';
        $database_filter = '';
        $mysqli = $this->system->getMysqli();

        if(@$this->data['ugr_eMail']){
            $email_filter = $this->data['ugr_eMail'];
        }
        $databases = mysql__getdatabases4($mysqli, false, $database_filter, $email_filter, 'user');
        
        
        if(@$this->data['restapi']==1){

            if(parent::search()===false){
                return false;
            }

            $predReg = $this->searchMgr->getPredicate('sys_dbRegisteredID');
            $requestedDatabase = $this->data['sys_Database'] ?? null;

            // "id" returns database names only. Any other details value returns
            // the same row/field structure produced by DbEntitySearch::execute().
            $is_ids_only = (($this->data['details'] ?? 'id') === 'id');
            $fields = array(
                'sys_Database',
                'sys_dbRegisteredID',
                'sys_dbName',
                'sys_dbRights',
                'sys_dbDescription'
            );

            $matched = array();

            foreach($databases as $database){
                if($requestedDatabase!==null && $requestedDatabase!==''
                    && strcmp($database, $requestedDatabase)!==0){
                    continue;
                }

                if($is_ids_only && $predReg===null){
                    $matched[] = $database;
                    continue;
                }

                $databaseIdentifier = str_replace('`', '``', HEURIST_DB_PREFIX.$database);
                $databaseLiteral = '"'.$mysqli->real_escape_string($database).'"';

                if($is_ids_only){
                    $query = 'SELECT '.$databaseLiteral.' AS sys_Database FROM `'
                        .$databaseIdentifier.'`.`sysIdentification`';
                }else{
                    $query = 'SELECT '.$databaseLiteral.' AS sys_Database, '
                        .'sys_dbRegisteredID,sys_dbName,sys_dbRights,sys_dbDescription FROM `'
                        .$databaseIdentifier.'`.`sysIdentification`';
                }

                if($predReg!==null){
                    $query .= ' WHERE '.$predReg;
                }

                $record = mysql__select_row_assoc($mysqli, $query);
                if($record){
                    $matched[] = $is_ids_only ? $database : $record;
                }
            }

            $total = count($matched);
            $offset = max(0, intval($this->data['offset'] ?? 0));
            $limit = intval($this->data['limit'] ?? 1000);
            if($limit<1){
                $limit = 1000;
            }
            $matched = array_slice($matched, $offset, $limit);

            if($is_ids_only){
                return array(
                    'queryid' => $this->data['request_id'] ?? null,
                    'offset' => $offset,
                    'count' => $total,
                    'reccount' => count($matched),
                    'records' => array_values($matched)
                );
            }

            $records = array();
            $order = array();
            foreach($matched as $record){
                $database = $record['sys_Database'];
                $records[$database] = array_values($record);
                $order[] = $database;
            }

            $response = array(
                'queryid' => $this->data['request_id'] ?? null,
                'entityName' => $this->config['entityName'],
                'pageno' => $this->data['pageno'] ?? null,
                'offset' => $offset,
                'count' => $total,
                'reccount' => count($records),
                'records' => $records,
                'order' => $order,
                'fields' => $fields
            );

            // Preserve the legacy REST response for direct entityScrud callers.
            // The public API supplies api_response_context and formats this full
            // result at the controller boundary, exactly as for other entities.
            if(empty($this->data['api_response_context'])){
                $legacyResponse = array();
                foreach($records as $row){
                    $item = array();
                    foreach($fields as $idx=>$field){
                        $item[$field] = $row[$idx] ?? null;
                    }
                    $legacyResponse[] = $item;
                }
                if($requestedDatabase!==null && count($legacyResponse)===1){
                    return $legacyResponse[0];
                }
                return $legacyResponse;
            }

            return $response;
        }//restapi
        
        $order = [];
        $records = [];
        
        foreach($databases as $database){
            $records[$database] = [$database];
            $order[] = $database;
        }

        return [
            'queryid'=> @$this->data['request_id'],  //query unqiue id set in doRequest
            'entityName'=> $this->config['entityName'],
            'pageno'=> @$this->data['pageno'],  //page number to sync
            'offset'=> @$this->data['offset'],
            'count'=> count($records),
            'reccount'=> count($records),
            'records'=> $records,

            'order'=> $order,
            'fields'=> ['sys_Database']
        ];

    }

    //
    // deletion and not allowed
    //
    /**
     * Disables direct deletion of database entries through this class.
     *
     * Database deletion is handled by other mechanisms (e.g., `databaseController.php`).
     *
     * @param bool $disable_foreign_checks Unused.
     * @return false Always returns false.
     */
    public function delete($disable_foreign_checks = false){
        //virtual method
        return false;
    }
    /**
     * Disables direct saving/creation of database entries through this class.
     *
     * Database creation is handled by other mechanisms (e.g., `databaseController.php`).
     *
     * @return false Always returns false.
     */
    public function save(){
        //virtual method
        return false;
    }

}
?>
