<?php
/**
* dbVerify.php : methods to validate and fix database struture and data integrity
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
* @subpackage  DataStore
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Static class to perform database operations
*
* Methods:
*
* databaseDrop - Removes database entirely with optional beforehand archiving 
* databaseDump - dumps all tables (except csv import cache) into SQL dump
* databaseCreateFull - Creates new heurist database, with file folders, given user and ready to use
* databaseValidateName - Verifies that database name is valid and optionally that database exists or unique
* databaseRestoreFromArchive - Restores database from archive 
* databaseEmpty - Clears data tables (retains defintions)
* databaseCloneFull - clones database including folders     
* databaseResetRegistration 
* databaseRename - renames database (in fact it clones database with new name and archive/drop old database)
* 
* databaseCheckNewDefs  
* updateOriginatingDB - Assigns given Origin ID for rectype, detail and term defintions
* updateImportedOriginatingDB - Assigns Origin ID for rectype, detail and term defintions after import from unregistered database
*
* private:
*    
* _databaseInitForNew - updates dbowner, adds default saved searches and lookups
* databaseClone - copy all tables (except csv import cache) from one db to another (@todo rename to _databaseCopyTables)
* _emptyTable - delete all records for given table
* databaseCreateFolders - creates if not exists the set of folders for given database
* databaseCreate - Creates new heurist database 
* databaseCreateConstraintsAndTriggers - Recreates constraints and triggers
*/

require_once dirname(__FILE__).'/../System.php';
require_once dirname(__FILE__).'/../../admin/verification/verifyValue.php';
require_once dirname(__FILE__).'/../../admin/verification/verifyFieldTypes.php';

class DbVerify {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private $mysqli = null;
    private $system = null;

    private $session_id = 0;
    private $progress_step = 0;

    public function __construct($system) {
       $this->system = $system;
       $this->mysqli = $system->get_mysqli();
    }    
    
    // 
    // init progress session
    //
    public function setSessionId($id){
        $this->session_id = $id;
        $this->progress_step = 0;
    }

    //
    // update progress session value
    // returns true if session has been terminated
    //
    public function setSessionVal($session_val){
        
        if($this->progress_step>0 && intval($session_val)>0){
            $session_val = $this->progress_step+$session_val;
        }
        
        $current_val = mysql__update_progress($this->mysqli, $this->session_id, false, $session_val);
        if($current_val=='terminate'){ //session was terminated from client side
            $this->session_id = 0;
            return true;
        }else{
            return false;
        }
    }
    
    /**
    * Check that records have valid Owner and Added by User references
    * 
    * @param array $params 
    */
    public function check_owner_ref($params=null){
        
        $resStatus = true;
        $resMsg = '';
        $wasassigned1 = 0;
        $wasassigned2 = 0;
        
        
        if(is_array($params) && @$params['fix']==1){
            
            $this->mysqli->query('SET SQL_SAFE_UPDATES=0');

            $query = 'UPDATE Records left join sysUGrps on rec_AddedByUGrpID=ugr_ID '
            .' SET rec_AddedByUGrpID=2 WHERE ugr_ID is null';
            $wasassigned1 = mysql__exec_param_query($this->mysqli, $query, null, true );
            if(is_string($wasassigned1))
            {
                $resMsg = '<div class="error">Cannot assign correct User reference (Added By) for Records.</div>';
                $resStatus = false;
            }

            $query = 'UPDATE Records left join sysUGrps on rec_OwnerUGrpID=ugr_ID '
            .' SET rec_OwnerUGrpID=2 WHERE ugr_ID is null';
            $wasassigned2 = mysql__exec_param_query($this->mysqli, $query, null, true );
            if(is_string($wasassigned2))
            {
                $resMsg = '<div class="error">Cannot assign correct User ownership for Records.</div>';
                $resStatus = false;
            }

            $this->mysqli->query('SET SQL_SAFE_UPDATES=1');
        }

        $wrongUser_Add = mysql__select_value($this->mysqli,
                'SELECT count(distinct rec_ID) FROM Records left join sysUGrps on rec_AddedByUGrpID=ugr_ID where ugr_ID is null');
        $wrongUser_Add = intval($wrongUser_Add);

        $wrongUser_Owner = mysql__select_value($this->mysqli,   
                'SELECT count(distinct rec_ID) FROM Records left join sysUGrps on rec_OwnerUGrpID=ugr_ID where ugr_ID is null');
        $wrongUser_Owner = intval($wrongUser_Owner);

        if($wrongUser_Add==0 && $wrongUser_Owner==0){
            $resMsg .= '<div><h3 class="res-valid">OK: All records have valid Owner and Added by User references</h3></div>';

            if($wasassigned1>0){
                $resMsg .= "<div>$wasassigned1 records 'Added by' value were set to user # 2 Database Manager</div>";
            }
            if($wasassigned2>0){
                $resMsg .= "<div>$wasassigned2 records were attributed to owner # 2 Database Manager</div>";
            }
        }
        else
        {
            $resStatus = false;
            $resMsg .= '<div>';
            if($wrongUser_Add>0){
                $resMsg .= "<h3> $wrongUser_Add records are owned by non-existent users</h3>";
            }
            if($wrongUser_Owner>0){
                $resMsg .= "<h3> $wrongUser_Owner records are owned by non-existent users</h3>";
            }

            $resMsg .= '<br><br><button data-fix="owner_ref">Attribute them to owner # 2 Database Manager</button></div>';
        }
        
        return array('status'=>$resStatus,'message'=>$resMsg);        
    }

    /**
    * Check 
    * 
    * @param array $params 
    */
    public function check_dup_terms($params=null){
        global $TL;
        
        $resStatus = true;
        $resMsg = '';
        
        $mysqli = $this->mysqli;
        
        if(@$params['data']){
            $lists = json_decode($params['data'], true);
        }else{
            $lists = getTermsWithIssues($mysqli);
        }        
        $trmWithWrongParents = prepareIds(@$lists["trm_missed_parents"]);
        $trmWithWrongInverse = prepareIds(@$lists["trm_missed_inverse"]);
        $trmDuplicates = @$lists["trm_dupes"];

        $wasassigned1 = 0;
        $wasassigned2 = 0;
        if(@$params['fix']=="1")
        {
                $mysqli->query('SET SQL_SAFE_UPDATES=0');
                
                if(is_array($trmWithWrongParents) && count($trmWithWrongParents)>0){
                
                    $trash_group_id = mysql__select_value($mysqli, 'select vcg_ID from defVocabularyGroups where vcg_Name="Trash"');

                    if($trash_group_id>0){
                    
                        $query = 'UPDATE defTerms set trm_ParentTermID=NULL, trm_VocabularyGroupID='.intval($trash_group_id)
                        .' WHERE trm_ID in ('.implode(',',$trmWithWrongParents).')';
                        
                        $wasassigned1 = mysql__exec_param_query($this->mysqli, $query, null, true );
                        if(is_string($wasassigned1))
                        {
                            $resMsg = '<div class="error">Cannot delete invalid pointers to parent terms. Error:'.$wasassigned1.'</div>';
                            $resStatus = false;
                        }else{
                            $trmWithWrongParents = array();//reset
                        }
                    
                    }else{
                            $resMsg = '<div class="error">Cannot find "Trash" group to fix invalid pointers to parent terms.</div>';
                            $resStatus = false;
                    }
                }

                if(is_array($trmWithWrongInverse) && count($trmWithWrongInverse)>0){
                    $query = 'UPDATE defTerms set trm_InverseTermID=NULL '
                    .' WHERE trm_ID in ('.implode(',',$trmWithWrongInverse).')';

                    $wasassigned2 = mysql__exec_param_query($this->mysqli, $query, null, true );
                    if(is_string($wasassigned2))
                    {
                        $resMsg = '<div class="error">Cannot clear missing inverse terms ids. Error:'.$wasassigned2.'</div>';
                        $resStatus = false;
                    }else{
                        $trmWithWrongInverse = array();//reset
                    }

                }

                $mysqli->query('SET SQL_SAFE_UPDATES=1');
        }

        if(count($trmWithWrongParents)==0 && count($trmWithWrongInverse)==0){
                $resMsg .= '<div><h3 class="res-valid">OK: All terms have valid inverse and parent term references</h3></div>';
                
                if($wasassigned1>0){
                    $resMsg .= "<div>$wasassigned1 terms with wrong parent terms moved to 'Trash' group as vocabularies</div>";
                }
                if($wasassigned2>0){
                    $resMsg .= "<div>$wasassigned2 wrong inverse term references are cleared</div>";
                }
            }
            else
            {

                $resMsg .= '<div>';
                if(count($trmWithWrongParents)>0){
                    
                    $resMsg .= '<h3>'.count($trmWithWrongParents).' terms have wrong parent term references</h3>';
                    $cnt = 0;
                    foreach ($trmWithWrongParents as $trm_ID) {
                        $resMsg .= ('<br>'.$trm_ID.'  '.$TL[$trm_ID]['trm_Label']);
                        if($cnt>30){
                          $resMsg .= ('<br>'.count($trmWithWrongParents)-$cnt.' more...');      
                          break;  
                        } 
                        $cnt++;
                    }
                    $resStatus = false;
                }
                if(count($trmWithWrongInverse)>0){
                    
                    $resMsg .= '<h3>'.count($trmWithWrongInverse).' terms have wrong inverse term references</h3>';
                    $cnt = 0;
                    foreach ($trmWithWrongInverse as $trm_ID) {
                        $resMsg .= '<br>'.$trm_ID.'  '.$TL[$trm_ID]['trm_Label'];
                        if($cnt>30){
                          $resMsg .= '<br>'.count($trmWithWrongInverse)-$cnt.' more...';      
                          break;  
                        } 
                        $cnt++;
                    }
                    $resStatus = false;    
                }
                
                $resMsg .= '<br><br><button data-fix="dup_terms">Correct wrong parent and inverse term references</button></div>';
        }
        
        if(count($trmDuplicates)>0){                                                 
            $resStatus = false;

            $resMsg .= '<h3>Terms are duplicated or ending in a number: these may be the result of automatic duplicate-avoidance. '
            .'If so, we suggest deleting the numbered term or using Design > Vocabularies to merge it with the un-numbered version.</h3>';
            foreach ($trmDuplicates as $parent_ID=>$dupes) {
                $resMsg .= '<div style="padding-top:10px;font-style:italic">parent '.intval($parent_ID).'  '
                    .htmlspecialchars($TL[$parent_ID]['trm_Label']).'</div>';
                foreach ($dupes as $trm_ID) {
                    $resMsg .= '<div style="padding-left:60px">'.intval($trm_ID).'  '
                    .htmlspecialchars($TL[$trm_ID]['trm_Label']).'</div>';
                }
            }
        }
        
        return array('status'=>$resStatus,'message'=>$resMsg);        
    }
    
    /**
    * Check 
    * 
    * @param array $params 
    */
    public function check_field_type($params=null){
        global $TL;
        
        $resStatus = true;
        $resMsg = '';
        
        $mysqli = $this->mysqli;
        
        if(@$params['data']){
            $lists = json_decode($params['data'], true);
        }else{
            
            $lists = getInvalidFieldTypes($mysqli, intval(@$params['rt'])); //in getFieldTypeDefinitionErrors.php
            if(!@$params['show']){
                if(count($lists["terms"])==0 && count($lists["terms_nonselectable"])==0
                && count($lists["rt_contraints"])==0){
                    $lists = array();
                }
            }
        }        
        
        //see getInvalidFieldTypes in verifyFieldTypes.php
        $dtysWithInvalidTerms = @$lists["terms"];
        $dtysWithInvalidNonSelectableTerms = @$lists["terms_nonselectable"];
        $dtysWithInvalidRectypeConstraint = @$lists["rt_contraints"];

        $rtysWithInvalidDefaultValues = @$lists["rt_defvalues"];
        
        if (($dtysWithInvalidTerms && is_array($dtysWithInvalidTerms) && count($dtysWithInvalidTerms)>0) || 
            ($dtysWithInvalidNonSelectableTerms && is_array($dtysWithInvalidNonSelectableTerms) && count($dtysWithInvalidNonSelectableTerms)>0) || 
            ($dtysWithInvalidRectypeConstraint && is_array($dtysWithInvalidRectypeConstraint) && count($dtysWithInvalidRectypeConstraint)>0)){
        
                if(@$params['fix']==1){
                             
                    $k = 0;
                    $err = null;
                    if(is_array($dtysWithInvalidTerms))
                    foreach ($dtysWithInvalidTerms as $row) {
                        $query='UPDATE defDetailTypes SET dty_JsonTermIDTree=? WHERE dty_ID='.intval($row['dty_ID']);
                        $res = mysql__exec_param_query($this->mysqli, $query, array('s',$row['validTermsString']), true );
                        if(is_string($res)){
                            $err = $row['dty_ID'].'. Error: '.$res;
                            break;
                        }
                        $k++;
                    }
                    if($err==null && is_array($dtysWithInvalidNonSelectableTerms))
                    foreach ($dtysWithInvalidNonSelectableTerms as $row) {
                        $query='UPDATE defDetailTypes SET dty_TermIDTreeNonSelectableIDs=? WHERE dty_ID='.intval($row['dty_ID']);
                        $res = mysql__exec_param_query($this->mysqli, $query, array('s',$row['validNonSelTermsString']), true );
                        if(is_string($res)){
                            $err = $row['dty_ID'].'. Error: '.$res;
                            break;
                        }
                        $k++;
                    }
                    if($err==null && is_array($dtysWithInvalidRectypeConstraint))
                    foreach ($dtysWithInvalidRectypeConstraint as $row) {
                        $query='UPDATE defDetailTypes SET dty_PtrTargetRectypeIDs=? WHERE dty_ID='.intval($row['dty_ID']);
                        $res = mysql__exec_param_query($this->mysqli, $query, array('s',$row['validRectypeConstraint']), true );
                        if(is_string($res)){
                            $err = $row['dty_ID'].'. Error: '.$res;
                            break;
                        }
                        $k++;
                    }
                    
                    if($err!=null){
                        $resMsg = '<div class="error">SQL error updating field type '.$err.'</div>';
                        $resStatus = false;
                    }else{
                        $resMsg = $k.' field'.($k>1?'s have':' has')
.' been repaired. If you have unsaved data in an edit form, save your changes and reload the page to apply revised/corrected field definitions';
                    }

                }else{
            
            $contact_us = CONTACT_HEURIST_TEAM;
            $resMsg = <<<HEADER
            <br><h3>Warning: Inconsistent field definitions</h3><br>&nbsp;<br>
            The following field definitions have inconsistent data (unknown codes for terms and/or record types). This is nothing to be concerned about, unless it reoccurs, in which case please $contact_us<br><br>
            To fix the inconsistencies, please click here: <button data-fix="field_type">Auto Repair</button>  <br>&nbsp;<br>
            <hr>
            HEADER;
//            You can also look at the individual field definitions by clicking on the name in the list below<br>&nbsp;<br>
            
            foreach ($dtysWithInvalidTerms as $row) {
                $resMsg .=                                                                      
                '<div class="msgline"><b>'
                    .htmlspecialchars( $row['dty_Name']). '</b> field (code ' . intval($row['dty_ID']) .' ) has '
                    .count($row['invalidTermIDs']).' invalid term ID'.(count($row['invalidTermIDs'])>1?"s":"")
                    .'(code: '. htmlspecialchars(implode(",",$row['invalidTermIDs'])).')</div>';

            }//for
            foreach ($dtysWithInvalidNonSelectableTerms as $row) {
                //<a href="#invalid_terms2" onclick="{ onEditFieldType('. intval($row['dty_ID']) .'); return false}">'
                $resMsg .=                                                                      
                '<div class="msgline"><b>'
                    .htmlspecialchars( $row['dty_Name']). '</b> field (code ' . intval($row['dty_ID']) .' ) has '
                    .count($row['invalidNonSelectableTermIDs']).' invalid non selectable term ID'.(count($row['invalidNonSelectableTermIDs'])>1?"s":"")
                    .'(code: '. htmlspecialchars(implode(",",$row['invalidNonSelectableTermIDs'])).')</div>';
            }
            foreach ($dtysWithInvalidRectypeConstraint as $row) {
                $resMsg .=                                                                      
                '<div class="msgline"><b>'
                    .htmlspecialchars( $row['dty_Name']). '</b> field (code ' . intval($row['dty_ID']) .' ) has '
                    .count($row['invalidRectypeConstraint']).' invalid record type constraint'.(count($row['invalidRectypeConstraint'])>1?"s":"")
                    .'(code: '. htmlspecialchars(implode(",",$row['invalidRectypeConstraint'])).')</div>';
            }
            $resStatus = false;
            
                }
            
        }
        
        if($resStatus){
            $resMsg = '<div>'.$resMsg.'<h3 class="res-valid">OK: All field type definitions are valid</h3></div>';
        }
               
        return array('status'=>$resStatus,'message'=>$resMsg);        
    }    
        
        
    /**
    * Check 
    * 
    * @param array $params 
    */
    public function check_default_values($params=null){
        
        $resStatus = true;
        $resMsg = '';
        
        $mysqli = $this->mysqli;
        
        if(@$params['data']){
            $lists = json_decode($params['data'], true);
        }else{
            $lists = getInvalidDefaultValues($mysqli, intval(@$params['rt'])); //in getFieldTypeDefinitionErrors.php
        }        
        
        $rstWithInvalidDefaultValues = @$lists["rt_defvalues"];
        
        if (($rstWithInvalidDefaultValues && is_array($rstWithInvalidDefaultValues) && count($rstWithInvalidDefaultValues)>0)){


            $resMsg = <<<'HEADER'
            <br><h3>Warning: Wrong field default values for record type structures</h3><br>&nbsp;<br>
            The following fields use unknown terms as default values. <u>These default values have been removed</u>.
            <br>&nbsp;<br>
            HEADER;
// <br>You can define new default value by clicking on the name in the list below and editing the field definition.<br>&nbsp;<br>
        
            foreach ($rstWithInvalidDefaultValues as $row) {
                $resMsg .= '<div class="msgline"><b>'
                    .htmlspecialchars($row['rst_DisplayName'])
                    .'</b> field (code '.intval($row['dty_ID']).' ) in record type '
                    .htmlspecialchars($row['rty_Name'])
                    .' had invalid default value ('.($row['dty_Type']=='resource'?'record ID ':'term ID ')
                    .htmlspecialchars($row['rst_DefaultValue'])
                    .'<span style="font-style:italic">'.htmlspecialchars($row['reason']).'</span></div>';
            }//for
            
        }else{
            $resMsg = '<h3 class="res-valid">OK: All default values in record type structures are valid</h3>';
        }
               
        return array('status'=>$resStatus,'message'=>$resMsg);        
    }            
    
    
    /**
    * Check 
    * 
    * @param array $params 
    */
    public function check_pointer_targets($params=null){
        
        $resStatus = true;
        $resMsg = '';
        $wasdeleted = 0;
        $mysqli = $this->mysqli;
        
        if(is_array($params) && @$params['fix']==1){
            
            $query = 'DELETE d from recDetails d
            left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
            left join Records b on b.rec_ID = d.dtl_Value and b.rec_FlagTemporary!=1
            where dt.dty_Type = "resource"
            and b.rec_ID is null';
            $res = $mysqli->query( $query );
            if(! $res )
            {
                $resMsg = '<div class="error">Cannot delete invalid pointers from Records.</div>';
                $resStatus = false;
            }else{
                $wasdeleted = $mysqli->affected_rows;
            }
        }

        $res = $mysqli->query('select dtl_RecID, dty_Name, a.rec_Title, a.rec_RecTypeID, dtl_Value
            from recDetails
            left join defDetailTypes on dty_ID = dtl_DetailTypeID
            left join Records a on a.rec_ID = dtl_RecID and a.rec_FlagTemporary!=1
            left join Records b on b.rec_ID = dtl_Value and b.rec_FlagTemporary!=1
            where dty_Type = "resource"
            and a.rec_ID is not null
        and b.rec_ID is null');
        $bibs = array();
        $ids = array();
        while ($row = $res->fetch_assoc()) {
            array_push($bibs, $row);
            $ids[$row['dtl_RecID']] = 1;
        }

        if(count($bibs)==0){
            $resMsg = '<div><h3 class="res-valid">OK: All record pointers point to a valid record</h3></div>';
            
            if($wasdeleted>0){
                $resMsg .= "<div>$wasdeleted invalid pointer(s) were removed from database</div>";
            }
        }
        
        else
        {
            $resStatus = false;
            
            $url_all = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&w=all&q=ids:'.implode(',', array_keys($ids));
            
            $resMsg .= <<<HEADER
            <div>
                <h3>Records with record pointers to non-existent records</h3>
                <div>To fix the inconsistencies, please click here: <button data-fix="pointer_targets">Delete ALL faulty pointers</button><br><br></div>
                <span>
                    <a target=_new href="$url_all">(show results as search)</a>
                    <a target=_new href="#selected_link" data-show-selected="recCB">(show selected as search)</a>
                </span>
            </div>
            <table role="presentation">
                <tr>
                    <td colspan="6">
                        <label><input type="checkbox" data-mark-all="recCB">Mark all</label>
                    </td>
                </tr>
            HEADER;

                $url_icon_placeholder = HEURIST_BASE_URL.'hclient/assets/16x16.gif';
                $url_icon_extlink = HEURIST_BASE_URL.'hclient/assets/external_link_16x16.gif';
                
                foreach ($bibs as $row) {

                    $url_icon = HEURIST_RTY_ICON.$row['rec_RecTypeID'];
                    $url_rec =  HEURIST_BASE_URL.'?fmt=edit&db='.$this->system->dbname().'&recID='.$row['dtl_RecID'];
                    $rec_title = strip_tags($row['rec_Title']);
                    
                    $resMsg .= <<<EOT
                    <tr>
                        <td><input type=checkbox name="recCB" value={$row['dtl_RecID']}></td>
                        <td><img alt class="rft" style="background-image:url($url_icon)" src="$url_icon_placeholder"></td>
                        <td style="white-space: nowrap;"><a target=_new href="$url_rec">
                                {$row['dtl_RecID']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title</td>
                        <td>{$row['dtl_Value']}</td>
                        <td>{$row['dty_Name']}</td>
                    </tr>
                    EOT;
                    
                }//foreach
                $resMsg .= "</table>\n";
        }
        
        return array('status'=>$resStatus,'message'=>$resMsg);        
    }

    
    /**
    * Check 
    * 
    * @param array $params 
    */
    public function check_target_types($params=null){
        
        $resStatus = true;
        $resMsg = '';
        $mysqli = $this->mysqli;
        
        $res = $mysqli->query('select dtl_RecID, dty_Name, dty_PtrTargetRectypeIDs, rec_ID, rec_Title, rty_Name, rec_RecTypeID
            from defDetailTypes
            left join recDetails on dty_ID = dtl_DetailTypeID
            left join Records on rec_ID = dtl_Value and rec_FlagTemporary!=1
            left join defRecTypes on rty_ID = rec_RecTypeID
            where dty_Type = "resource"
            and dty_PtrTargetRectypeIDs > 0
        and (INSTR(concat(dty_PtrTargetRectypeIDs,\',\'), concat(rec_RecTypeID,\',\')) = 0)');

        $bibs = array();
        while ($row = $res->fetch_assoc()){
            $bibs[$row['dtl_RecID']] = $row;
        }

        if(count($bibs)==0){
            $resMsg = '<div><h3 class="res-valid">OK: All record pointers point to the correct record type</h3></div>';
        }
        else
        {
            $resStatus = false;
            
            $url_all = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&w=all&q=ids:'.implode(',', array_keys($bibs));
            
            $resMsg .= <<<HEADER
            <div>
                <h3>Records with record pointers to the wrong record type</h3>
                <br>
                <span>
                    <a target=_new href="$url_all">(show results as search)</a>
                    <a target=_new href="#selected_link" data-show-selected="recCB2">(show selected as search)</a>
                </span>
            </div>
            <table role="presentation">
                <tr>
                    <td colspan="6">
                        <label><input type="checkbox" data-mark-all="recCB2">Mark all</label>
                    </td>
                </tr>
            HEADER;

                $url_icon_placeholder = HEURIST_BASE_URL.'hclient/assets/16x16.gif';
                $url_icon_extlink = HEURIST_BASE_URL.'hclient/assets/external_link_16x16.gif';
                
                foreach ($bibs as $row) {

                    $url_icon = HEURIST_RTY_ICON.$row['rec_RecTypeID'];
                    $url_rec =  HEURIST_BASE_URL.'?fmt=edit&db='.$this->system->dbname().'&recID='.$row['dtl_RecID'];
                    $rec_title = (substr(strip_tags($row['rec_Title']), 0, 50));
                    
                    $resMsg .= <<<EOT
                    <tr>
                        <td><input type=checkbox name="recCB2" value={$row['dtl_RecID']}></td>
                        <td><img alt class="rft" style="background-image:url($url_icon)" src="$url_icon_placeholder"></td>
                        <td style="white-space: nowrap;"><a target=_new href="$url_rec">
                                {$row['dtl_RecID']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td>{$row['dty_Name']}</td>
                        <td>points to</td>
                        <td>{$row['rec_ID']} ({$row['rty_Name']}) - $rec_title</td>
                    </tr>
                    EOT;
                    
                }//foreach
                $resMsg .= "</table>\n";
        }
        
        return array('status'=>$resStatus,'message'=>$resMsg);        
    }    
     
     
    /**
    * Check 
    * 
    * @param array $params 
    */
    public function check_target_parent($params=null){
        
        $resStatus = true;
        $resMsg = '';
        $wasdeleted = 0;
        $wasadded1 = 0;
        
        $mysqli = $this->mysqli;
        $this->system->defineConstant('DT_PARENT_ENTITY');
        
        if(false && is_array($params) && @$params['fix']==1){ //OLW WAY DISABLED

            //remove pointer field in parent records that does not have reverse in children
            $query = 'DELETE parent FROM Records parentrec, defRecStructure, recDetails parent '
            .'LEFT JOIN recDetails child ON child.dtl_DetailTypeID='.DT_PARENT_ENTITY.' AND child.dtl_Value=parent.dtl_RecID '
            .'LEFT JOIN Records childrec ON parent.dtl_Value=childrec.rec_ID '
            .'WHERE '
            .'parentrec.rec_ID=parent.dtl_RecID AND rst_CreateChildIfRecPtr=1 '
            .'AND rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=parent.dtl_DetailTypeID '
            .'AND child.dtl_RecID is NULL'; 

            $res = $mysqli->query( $query );
            if(! $res )
            {
                $resMsg = '<div class="error">Cannot delete invalid pointers from Records.</div>';
                $resStatus = false;
            }else{
                $wasdeleted1 = $mysqli->affected_rows;
            }
        }

        //find parents with pointer field rst_CreateChildIfRecPtr=1) without reverse pointer in child record               
        $query1 = 'SELECT parentrec.rec_ID, parentrec.rec_RecTypeID, parentrec.rec_Title as p_title, '  //'parent.dtl_DetailTypeID, rst_DisplayName, '
            .'parent.dtl_Value, childrec.rec_Title as c_title, child.dtl_RecID as f247 '
            .'FROM Records parentrec, defRecStructure, recDetails parent '
            .'LEFT JOIN recDetails child ON child.dtl_DetailTypeID='.DT_PARENT_ENTITY.' AND child.dtl_Value=parent.dtl_RecID '
            .'LEFT JOIN Records childrec ON parent.dtl_Value=childrec.rec_ID '
            .'WHERE '
            .'parentrec.rec_ID=parent.dtl_RecID AND rst_CreateChildIfRecPtr=1 AND parentrec.rec_FlagTemporary!=1 '
            .'AND rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=parent.dtl_DetailTypeID '
            .'AND child.dtl_RecID is NULL ORDER BY parentrec.rec_ID'; 


        $res = $mysqli->query( $query1 );
        $bibs1 = array();
        $prec_ids1 = array();
        while ($row = $res->fetch_assoc()){

            $child_ID = intval($row['dtl_Value']);

            if(@$params['fix']==1){ 
                //new way: add missed reverse link to alleged children
                $query2 = 'INSERT into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) VALUES(' 
                .$child_ID . ','. DT_PARENT_ENTITY  .', '.intval($row['rec_ID']) . ')';
                $mysqli->query($query2);
                $wasadded1++;
            }else{
                $bibs1[] = $row;
                $prec_ids1[] = $row['rec_ID'];
            }

        }
        
        //find children without reverse pointer in parent record               
        $query2 = 'SELECT child.dtl_ID as child_d_id, child.dtl_RecID as child_id, childrec.rec_Title as c_title, child.dtl_Value, '
            .'parentrec.rec_Title as p_title, parent.dtl_ID as parent_d_id, parent.dtl_Value rev, dty_ID, rst_CreateChildIfRecPtr, '
            .'childrec.rec_FlagTemporary '
            .' FROM recDetails child '
            .'LEFT JOIN Records parentrec ON child.dtl_Value=parentrec.rec_ID '
            .'LEFT JOIN Records childrec ON childrec.rec_ID=child.dtl_RecID  '
            .'LEFT JOIN recDetails parent ON parent.dtl_RecID=parentrec.rec_ID AND parent.dtl_Value=childrec.rec_ID '
            .'LEFT JOIN defDetailTypes ON parent.dtl_DetailTypeID=dty_ID AND dty_Type="resource" ' //'AND dty_PtrTargetRectypeIDs=childrec.rec_RecTypeID '
            .'LEFT JOIN defRecStructure ON rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=dty_ID AND rst_CreateChildIfRecPtr=1 '
            .'WHERE child.dtl_DetailTypeID='.DT_PARENT_ENTITY .' AND parent.dtl_DetailTypeID!='.DT_PARENT_ENTITY
            .' AND dty_Type="resource" AND (rst_CreateChildIfRecPtr IS NULL OR rst_CreateChildIfRecPtr!=1) ORDER BY child.dtl_RecID';

        $res = $mysqli->query( $query2 );
        
        $bibs2 = array();
        $prec_ids2 = array();
        $det_ids = array();
        if($res){
            while ($row = $res->fetch_assoc()){
                if($row['rec_FlagTemporary']==1) continue;
                if(in_array( $row['child_d_id'], $det_ids)) continue;
                $bibs2[] = $row;
                $prec_ids2[] = $row['dtl_Value'];  //remove DT_PARENT_ENTITY from orphaned children
                array_push($det_ids, intval($row['child_d_id'])); 
                /*
                if($row['parent_d_id']>0){
                // keep dtl_ID of pointer field in parent record 
                // to remove this 'fake' pointer in parent - that's wrong - need to remove DT_PARENT_ENTITY in child
                $det_ids[] = $row['parent_d_id']; 
                }
                */
            }//while
        }else{
             $resMsg .= '<div class="error">Cannot execute query "find children without reverse pointer in parent record".</div>';
             $resStatus = false;
        }        

        $wasdeleted2 = 0;
        if(@$params['fix']==2 && count($det_ids)>0){

            $query = 'DELETE FROM recDetails WHERE dtl_ID in ('.implode(',',$det_ids).')';
            $res = $mysqli->query( $query );
            if(! $res )
            {
                $resMsg .= '<div class="error">Cannot delete invalid pointers from Records.</div>';
                $resStatus = false;
            }else{
                $wasdeleted2 = $mysqli->affected_rows;
                $bibs2 = array();
            }
        }
        
        $url_icon_placeholder = HEURIST_BASE_URL.'hclient/assets/16x16.gif';
        $url_icon_extlink = HEURIST_BASE_URL.'hclient/assets/external_link_16x16.gif';
        
        if(count($bibs1)==0){
            $resMsg .= '<div><h3>OK: All parent records are correctly referenced by their child records</h3></div>';

            if($wasdeleted1>1){
                $resMsg .= "<div>$wasdeleted1 invalid pointer(s) were removed from database</div>";
            }
            if($wasadded1>0){
                $resMsg .= "<div>$wasadded1 reverse pointers were added to child records</div>";
            }
        }
        
        else
        {
            $resStatus = false;
            
            $url_all = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&w=all&q=ids:'.implode(',', array_keys($prec_ids1));
            
            $resMsg .= <<<HEADER
                <br><h3>Parent records which are not correctly referenced by their child records (missing pointer to parent)</h3>

                <div>To fix the inconsistencies, please click here:
                    <button data-fix="target_parent">Add missing parent record pointers to child records</button>
                </div>

                <a target=_new href="$url_all">(show results as search)</a>
                <a target=_new href="#selected_link" data-show-selected="recCB3">(show selected as search)</a>
                <table role="presentation">
                <tr>
                    <td colspan="7">
                        <label><input type="checkbox" data-mark-all="recCB">Mark all</label>
                    </td>
                </tr>
            HEADER;

                foreach ($bibs1 as $row) {

                    $url_icon_parent = HEURIST_RTY_ICON.$row['rec_RecTypeID'];
                    $url_rec_parent =  HEURIST_BASE_URL.'?fmt=edit&db='.$this->system->dbname().'&recID='.$row['rec_ID'];
                    $url_rec_child =  HEURIST_BASE_URL.'?fmt=edit&db='.$this->system->dbname().'&recID='.$row['dtl_Value'];
                    $rec_title_parent = substr(strip_tags($row['p_title']),0,50);
                    $rec_title_child = substr(strip_tags($row['c_title']),0,50);
                    
                    $resMsg .= <<<EOT
                    <tr>
                        <td><input type=checkbox name="recCB3" value={$row['rec_ID']}></td>
                        <td><img alt class="rft" style="background-image:url($url_icon_parent)" src="$url_icon_placeholder"></td>
                        <td style="white-space: nowrap;"><a target=_new href="$url_rec_parent">
                                {$row['rec_ID']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title_parent</td>

                        <td>points to</td>

                        <td style="white-space: nowrap;"><a target=_new href="$url_rec_child">
                                {$row['dtl_Value']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title_child</td>
                    </tr>
                    EOT;
                    
                }//foreach
                $resMsg .= "</table>\n";
        }
        
        
        if (count($bibs2) == 0) {
            $resMsg .= '<br><h3 class="res-valid">OK: All parent records correctly reference records which believe they are their children</h3><br>';
            
            if($wasdeleted2>1){
                $resMsg .= "<div>$wasdeleted2 invalid pointer(s) were removed from database</div>";
            }
        }
        else
        {
            $resStatus = false;
            
            $url_all = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&w=all&q=ids:'.implode(',', array_keys($prec_ids2));
            
            $resMsg .= <<<HEADER
            <br><h3>Child records indicate a parent which does not identify them as their child. </h3>
                
                <a target=_new href="$url_all">(show results as search)</a>
                <a target=_new href="#selected_link" data-show-selected="recCB4">(show selected as search)</a>
                
            <table role="presentation">
                <tr>
                    <td colspan="6">
                        <label><input type="checkbox" data-mark-all="recCB4">Mark all</label>
                    </td>
                </tr>
            HEADER;
            /*
            IJ: For the moment I suggest you omit a FIX button for this case
                fix=2
                <div>To fix the inconsistencies, please click here:
                    <button data-fix="target_parent">Delete broken parent-child fields in alleged children records</button>
                </div>
            */

            foreach ($bibs2 as $row) {
                
                    $url_rec_parent =  HEURIST_BASE_URL.'?fmt=edit&db='.$this->system->dbname().'&recID='.$row['dtl_Value'];
                    $url_rec_child =  HEURIST_BASE_URL.'?fmt=edit&db='.$this->system->dbname().'&recID='.$row['child_id'];
                    $rec_title_parent = substr(strip_tags($row['p_title']),0,50);
                    $rec_title_child = substr(strip_tags($row['c_title']),0,50);
                    
                    $resMsg .= <<<EOT
                    <tr>
                        <td><input type=checkbox name="recCB4" value={$row['dtl_Value']}></td>
                        <td style="white-space: nowrap;"><a target=_new href="$url_rec_parent">
                                {$row['dtl_Value']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title_parent</td>

                        <td>has reverse 'pointer to parent' in </td>

                        <td style="white-space: nowrap;"><a target=_new href="$url_rec_child">
                                {$row['child_id']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title_child</td>
                    </tr>
                    EOT;
            }
            $resMsg .= "</table>\n";    

        }
        
        return array('status'=>$resStatus,'message'=>$resMsg);        
    }       
}


?>