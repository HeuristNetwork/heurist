<?php

    /**
    * SCRUD controller
    * search, create, read, update and delete
    * 
    * Application interface. See hRecordMgr in hapi.js
    * Add/replace/delete details in batch
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

    require_once (dirname(__FILE__).'/../System.php');

    require_once (dirname(__FILE__).'/../entity/dbUsrTags.php');
    require_once (dirname(__FILE__).'/../entity/dbSysDatabases.php');
    require_once (dirname(__FILE__).'/../entity/dbSysIdentification.php');
    require_once (dirname(__FILE__).'/../entity/dbSysGroups.php');
    require_once (dirname(__FILE__).'/../entity/dbSysUsers.php');
    require_once (dirname(__FILE__).'/../entity/dbDefDetailTypeGroups.php');
    require_once (dirname(__FILE__).'/../entity/dbDefFileExtToMimetype.php');
    require_once (dirname(__FILE__).'/../entity/dbDefTerms.php');
    require_once (dirname(__FILE__).'/../entity/dbDefVocabularyGroups.php');
    require_once (dirname(__FILE__).'/../entity/dbDefRecTypeGroups.php');
    require_once (dirname(__FILE__).'/../entity/dbDefDetailTypes.php');
    require_once (dirname(__FILE__).'/../entity/dbDefRecTypes.php');
    require_once (dirname(__FILE__).'/../entity/dbDefRecStructure.php');
    require_once (dirname(__FILE__).'/../entity/dbDefCalcFunctions.php');
    require_once (dirname(__FILE__).'/../entity/dbSysArchive.php');
    require_once (dirname(__FILE__).'/../entity/dbSysBugreport.php');
    require_once (dirname(__FILE__).'/../entity/dbSysDashboard.php');
    require_once (dirname(__FILE__).'/../entity/dbSysImportFiles.php');
    require_once (dirname(__FILE__).'/../entity/dbSysWorkflowRules.php');
    require_once (dirname(__FILE__).'/../entity/dbRecThreadedComments.php');
    require_once (dirname(__FILE__).'/../entity/dbRecUploadedFiles.php');
    require_once (dirname(__FILE__).'/../entity/dbRecords.php');
    require_once (dirname(__FILE__).'/../entity/dbUsrBookmarks.php');
    require_once (dirname(__FILE__).'/../entity/dbUsrReminders.php');
    require_once (dirname(__FILE__).'/../entity/dbUsrSavedSearches.php');
    require_once (dirname(__FILE__).'/../entity/dbAnnotations.php');

    require_once (dirname(__FILE__).'/../dbaccess/utils_db.php');

    
    //    
    // $params
    //  entity
    //  a - acrion 
    //  details
    //
    function entityExecute($system, $params){
        
        $entity_name = entityResolveName(@$params['entity']);
        
        $classname = 'Db'.ucfirst($entity_name);
        $entity = new $classname($system, $params);
        
        if(!$entity){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong entity parameter: $entity_name");
            return false;
        }else{
            return $entity->run();    
        }
    }

    //
    //
    //    
    function entityRefreshDefs( $system, $entities, $need_config, $search_params=null){
        
        $search_criteria = array();
        
        if($search_params!=null){
            
            if(!is_array($search_params) && intval($search_params)>0){
                $search_params = array('recID'=>$search_params);
            }
            
            //load definitions for particular record type only
            $mysqli = $system->get_mysqli();
            if(@$search_params['recID']>0 || @$search_params['rty_ID']){
                $rec_ID = @$search_params['recID'];
                
                if($rec_ID>0){
                    $rty_ID = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$rec_ID); 
                    $search_criteria['defRecTypes'] = array('ID'=>$rty_ID);
                }else{
                    $rty_ID = $search_params['rty_ID'];
                }
                
                if($rty_ID>0){
                    $dty_IDs = mysql__select_list2($mysqli, 'SELECT rst_DetailTypeID FROM defRecStructure where rst_RecTypeID='.$rty_ID);
                    $search_criteria['defRecStructure'] = array('rst_RecTypeID'=>$rty_ID, 'rst_DetailTypeID'=>$dty_IDs);
                    $search_criteria['defDetailTypes'] = array('dty_ID'=>$dty_IDs);

                    $trm_IDs = mysql__select_list2($mysqli, 'SELECT dty_JsonTermIDTree FROM defDetailTypes where dty_ID in ('.implode(',',$dty_IDs).') AND dty_Type="enum"');
                
                    $entities = array('rty','dty','rst','swf');
                }
            }else{
                $entities = array_keys($search_params);
                $search_criteria = $search_params;
            }
            
        }else 
        if($entities=='all' || $entities==null){
            
            //set_time_limit(120);
            $entities = array('rty','dty','rst','trm','rtg','dtg','vcg','swf');  
            
        }else if(!is_array($entities)){
            $entities = explode(',',$entities);
        }
            
        $params = array();
        $res = array();
        if($need_config!==false) $need_config = array();
        
        foreach($entities as $idx=>$entity_name){
            
            $entity_name = entityResolveName($entity_name);
            $details = 'full';
            if($entity_name == 'defRecStructure'){
                $details = 'list';
            }
            $params = array('entity'=>$entity_name,'details'=>$details);
            
            if(@$search_criteria[$entity_name]){
                $params = array_merge($params, $search_criteria[$entity_name]);
            }

            $classname = 'Db'.ucfirst($entity_name);
            $entity = new $classname($system, $params);
            
            $res[$entity_name] = $entity->search();
            if($res[$entity_name]===false){
                return false;   
            }else{
                if($need_config!==false){
                    $need_config[$entity_name]['config'] = $entity->config();    
                }
                if($entity_name == 'defTerms'){
                    $res[$entity_name]['trm_Links'] = $entity->getTermLinks();
                }
            }
        }
        return $res;
    }
    
    //
    //
    //
    function entityResolveName($entity_name)
    {
            if($entity_name=='rtg') $entity_name = 'defRecTypeGroups';
            else if($entity_name=='dtg') $entity_name = 'defDetailTypeGroups';
            else if($entity_name=='rty') $entity_name = 'defRecTypes';
            else if($entity_name=='dty') $entity_name = 'defDetailTypes';
            else if($entity_name=='trm') $entity_name = 'defTerms';
            else if($entity_name=='vcg') $entity_name = 'defVocabularyGroups';
            else if($entity_name=='rst') $entity_name = 'defRecStructure';   
            else if($entity_name=='rem') $entity_name = 'dbUsrReminders';   
            else if($entity_name=='swf') $entity_name = 'sysWorkflowRules';   
            
            return $entity_name;
    }
    
    //
    // Returns full path, content type and url by entity name, view version (icon,thumb) and entity id;
    //
    function resolveEntityFilename($entity_name, $rec_id, $version, $db_name=null, $extension=null){
        global $defaultRootFileUploadURL;
        
        $entity_name = entityResolveName($entity_name); 

        if($entity_name=='sysDatabases' && $rec_id){
            
            $db_name = $rec_id;
            if(strpos($rec_id, 'hdb_')===0){
                $db_name = substr($rec_id,4);    
            }
            $rec_id = 1;    
            $path = '/entity/sysIdentification/';    

        }else{
            if($entity_name=='term' || $entity_name=='trm'){
                $entity_name = 'defTerms';
            }

            if($db_name==null){
                if(defined('HEURIST_DBNAME')){
                    $db_name = HEURIST_DBNAME;
                }else{
                    return array(null,null,null);
                }
            }
            $path = '/entity/'.$entity_name.'/';    
        } 

        if(!$version){
            $version = ($entity_name=='defRecTypes')?'icon':'thumbnail';   
        }else if($version=='thumb'){ 
            $version='thumbnail';
        }
        
        if($version!='full' && !($entity_name!='defRecTypes' && $version=='icon'))
        {
            $path = $path.$version.'/';
        }
        
        $filename = null;
        $content_type = null;
        $url = null;

        if($rec_id>0){

            $fname = HEURIST_FILESTORE_ROOT.$db_name.$path.$rec_id;
            
            $exts = $extension?array($extension):array('png','jpg','svg','jpeg','jpe','jfif','gif');
            foreach ($exts as $ext){
                if(file_exists($fname.'.'.$ext)){
                    if($ext=='jpg' || $ext=='jfif' || $ext=='jpe'){
                        $content_type = 'image/jpeg';
                    }else if($ext=='svg'){
                        $content_type = 'image/svg+xml';
                    }else{
                        $content_type = 'image/'.$ext;    
                    }
                    $filename = $fname.'.'.$ext;
                    $url =  $defaultRootFileUploadURL.$db_name.$path.$rec_id.'.'.$ext;
                    break;
                }
            }
        }
        
        return array($filename, $content_type, $url);
    }
    

?>