<?php
/**
* DbDefFileExtToMimetype.php - Class DbDefFileExtToMimetype
*
* Operations for the `defFileExtToMimetype` table.
*
* @project     Heurist academic knowledge management system
* @package Entity 
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
namespace hserv\entity;
use hserv\entity\DbEntityBase;

/**
* Class DbDefFileExtToMimetype
*
* Provides database access and operations for the `defFileExtToMimetype` table,
* which maps file extensions to MIME types.
*
*/
class DbDefFileExtToMimetype extends DbEntityBase
{
   /**
     * Searches for file extension to MIME type mappings based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * It then adds specific predicates for this entity:
     * - `fxm_Extension`: If provided in `$this->data['fxm_Extension']`.
     * - `fxm_MimeType`: If provided in `$this->data['fxm_MimeType']`.
     * - `fxm_FiletypeName`: If provided in `$this->data['fxm_FiletypeName']`.
     *
     * The fields returned in the search results depend on `$this->data['details']`:
     * - 'id': Returns only `fxm_Extension` (which is the primary key for this table).
     * - 'name': Returns `fxm_Extension`, `fxm_MimeType`.
     * - 'list' or 'full': Returns all fields defined in `$this->fields` for this entity.
     *
     * Results are ordered by `fxm_Extension`.
     * The primary key `fxm_Extension` is always included as the first field in the results.
     *
     * @return array|false An array containing the search results as structured by `DbEntitySearch::execute()`,
     *                     typically including 'records', 'count', 'total_count', etc.
     *                     Returns `false` if `parent::search()` fails (e.g., parameter validation error)
     *                     or if the database query fails.
     */
    public function search(){

        if(parent::search()===false){
              return false;
        }

        //compose WHERE
        $where = array();

        $pred = $this->searchMgr->getPredicate('fxm_Extension');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('fxm_MimeType');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('fxm_FiletypeName');
        if($pred!=null) {array_push($where, $pred);}


        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'fxm_Extension';

        }elseif(@$this->data['details']=='name'){

            $this->data['details'] = 'fxm_Extension,fxm_MimeType';

        }elseif(@$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = implode(',', array_keys($this->fields) );
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        //validate names of fields
        foreach($this->data['details'] as $fieldname){
            if(!@$this->fields[$fieldname]){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                return false;
            }
        }

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('fxm_Extension', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'], 'fxm_Extension');
        }
        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
                .' FROM defFileExtToMimetype';

         if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
         }

         $query = $query.' ORDER BY fxm_Extension ';

         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $res = $this->searchMgr->execute($query, $is_ids_only, 'defFileExtToMimetype');
        return $res;

    }

    //
    // Since in this table primary key is varchar need special treatment
    //
    /**
     * Deletes a file extension to MIME type mapping.
     *
     * The primary key for this table (`fxm_Extension`) is a VARCHAR, so this method
     * handles deletion based on this string key.
     *
     * @param bool $disable_foreign_checks Unused in this implementation, but part of parent signature.
     * @return bool True on successful deletion, false on failure (e.g., record ID not provided,
     *              permission denied, or database error).
     */
    public function delete($disable_foreign_checks = false){

        $rec_ID = @$this->data[$this->primaryField];
        if($rec_ID==null){
            $this->system->addError(HEURIST_INVALID_REQUEST,
                                 "Cannot delete from table ".$this->config['entityName'],
                                 'Record ID provided is an invalid value');
            return false;
        }

        $this->recordIDs = array($rec_ID);
        if(!$this->_validatePermission()){
            return false;
        }
        $ret = null;

        $query = SQL_DELETE.$this->config['tableName'].SQL_WHERE.$this->primaryField." = '".$rec_ID."'";

        $mysqli = $this->system->getMysqli();
        $res = $mysqli->query($query);

        if(!$res){
            $ret = $mysqli->error;
            $this->system->addError(HEURIST_INVALID_REQUEST,
                             "Cannot delete from table ".$this->config['entityName'], $mysqli->error);
            return false;
        }else{
            $ret = true;
        }

        return $ret;
    }

}
?>
