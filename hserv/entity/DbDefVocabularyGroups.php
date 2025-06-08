<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
     * Class DbDefVocabularyGroups
     *
     * Provides database access and operations for the `defVocabularyGroups` table,
     * which stores groups for vocabularies (collections of terms).
     *
     * @package     Heurist academic knowledge management system
     * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
class DbDefVocabularyGroups extends DbEntityBase
{
    /**
     * Searches for Vocabulary Groups based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * It then adds specific predicates for this entity:
     * - `vcg_ID`: If provided in `$this->data['vcg_ID']`.
     * - `vcg_Name`: If provided in `$this->data['vcg_Name']`.
     *
     * The fields returned in the search results depend on `$this->data['details']` (defaults to 'full'):
     * - 'id': Returns only `vcg_ID`.
     * - 'name': Returns `vcg_ID`, `vcg_Name`.
     * - 'list': Returns `vcg_ID`, `vcg_Name`, `vcg_Description`, `vcg_Order`.
     * - 'full': Returns all fields defined in `$this->fieldNames` for this entity.
     * - If `$this->data['details']` is an array or comma-separated string, those specific fields are selected.
     *
     * The primary key `vcg_ID` is always included as the first field in the results.
     * Results are ordered by `vcg_Order`.
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

        $pred = $this->searchMgr->getPredicate('vcg_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('vcg_Name');
        if($pred!=null) {array_push($where, $pred);}

        if(@$this->data['details']==null) {$this->data['details'] = 'full';}//default

        //compose SELECT it depends on param 'details' ------------------------
        //@todo - take it form fiels using some property
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'vcg_ID';

        }elseif(@$this->data['details']=='name'){

            $this->data['details'] = 'vcg_ID,vcg_Name';

        }elseif(@$this->data['details']=='list'){

            $this->data['details'] = 'vcg_ID,vcg_Name,vcg_Description,vcg_Order';

        }elseif(@$this->data['details']=='full'){

            $this->data['details'] = implode(',', $this->fieldNames );
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        /*validate names of fields
        foreach($this->data['details'] as $fieldname){
            if(!@$this->fields[$fieldname]){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                return false;
            }
        }*/

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('vcg_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'vcg_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.$this->config['tableName'];

         if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
         }
         $query = $query.' ORDER BY vcg_Order '.$this->searchMgr->getLimit().$this->searchMgr->getOffset();


        $res = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName']);
        return $res;
    }

    //
    //
    //
    /**
     * Deletes vocabulary group(s).
     *
     * Before deletion, it sets up a foreign key check to prevent deletion of non-empty groups
     * (groups that still contain vocabularies/root terms).
     *
     * @param bool $disable_foreign_checks Unused in this implementation, passed to parent.
     * @return bool|array Result of `parent::delete()`.
     */
    public function delete($disable_foreign_checks = false){

        $this->isDeleteReady = false;

        $this->foreignChecks = array(
                    array('SELECT count(trm_ID) FROM defTerms WHERE (trm_ParentTermID IS NULL OR trm_ParentTermID=0) AND `trm_VocabularyGroupID`',
                          'Cannot delete non empty group')
                );

        return parent::delete();
    }

    //
    //
    //
    /**
     * Prepares vocabulary group records before saving.
     *
     * Validates `vcg_Name` for duplication.
     * Sets `vcg_Modified` to the current date/time.
     * Sets `vcg_Domain` to 'relation' if `vcg_ID` is 9 or `vcg_Domain` is already 'relation', otherwise defaults to 'enum'.
     * Sets `vcg_Order` to 2 if not already a positive integer.
     * Sets `is_new` flag.
     *
     * @return bool True if preparation is successful and validation passes, false otherwise.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            //validate duplication
            $mysqli = $this->system->getMysqli();

            if(@$this->records[$idx]['vcg_Name']){
                $res = mysql__select_value($mysqli,
                        "SELECT vcg_ID FROM ".$this->config['tableName']."  WHERE vcg_Name='"
                        .$mysqli->real_escape_string( $this->records[$idx]['vcg_Name'])."'");
                if($res>0 && $res!=@$this->records[$idx]['vcg_ID']){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Vocabulary group cannot be saved. The provided name already exists');
                    return false;
                }
            }

            $this->records[$idx]['vcg_Modified'] = date(DATE_8601);//reset
            $this->records[$idx]['vcg_Domain'] = ($this->records[$idx]['vcg_ID']==9
                        || @$this->records[$idx]['vcg_Domain']=='relation')?'relation':'enum';

            if(!(@$this->records[$idx]['vcg_Order']>0)){
                $this->records[$idx]['vcg_Order'] = 2;
            }

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['vcg_ID']>0));
        }

        return $ret;
    }
}
?>
