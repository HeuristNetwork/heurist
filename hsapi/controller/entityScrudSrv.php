<?php

    /**
    * SCRUD controller
    * search, create, read, update and delete
    * 
    * Application interface. See hRecordMgr in hapi.js
    * Add/replace/delete details in batch
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
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
    function entityRefreshDefs( $system, $entities, $need_config ){
        
        if($entities=='all' || $entities==null){
            $entities = array('rty','dty','rst','trm','rtg','dtg','vcg','swf');  //
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

            $classname = 'Db'.ucfirst($entity_name);
            $entity = new $classname($system, array('entity'=>$entity_name,'details'=>$details));
            
            $res[$entity_name] = $entity->search();
            if($need_config!==false && $res[$entity_name]!==false){
                $need_config[$entity_name]['config'] = $entity->config();    
            }
            if($entity_name == 'defTerms'){
                $res[$entity_name]['trm_Links'] = $entity->getTermLinks();
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
            else if($entity_name=='swf') $entity_name = 'sysWorkflowRules';   
            
            return $entity_name;
    }

?>