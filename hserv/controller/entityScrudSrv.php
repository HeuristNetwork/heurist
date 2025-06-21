<?php
/**
* entityScrudSrv.php - Library of function that inits instance of hserv/entity class and runs the requested action.
*
* @package     Heurist academic knowledge management system
* @subpackage  controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/**
* Executes an action on a specified entity.
*
* Resolves the entity name, instantiates the corresponding entity class,
*
* @param \hserv\System $system The system object.
* @param array $params Parameters for the entity action, including 'entity' and 'a' (action).
* @return mixed|false The result of the entity action, or false on error.
*/
function entityExecute($system, $params){

    $entity = null;

    $entity_name = entityResolveName(@$params['entity']);

    if($entity_name!=null){
        $classname = 'hserv\entity\Db'.ucfirst($entity_name);
        if($classname=='hserv\entity\DbRecords'){
            $system->addError(HEURIST_INVALID_REQUEST, 'Wrong entity parameter: '.htmlspecialchars(@$params['entity']));
            return false;
        }
        $params['entity'] = $entity_name;
        $entity = new $classname($system, $params);
    }

    if(!$entity){
        $system->addError(HEURIST_INVALID_REQUEST, 'Wrong entity parameter: '.htmlspecialchars(@$params['entity']));
        return false;
    }else{
        return $entity->run();
    }
}

/**
* Refreshes entity definitions.
*
* Loads or reloads definitions for specified entities. Can fetch all definitions,
* definitions for a specific set of entities, or definitions related to a particular record.
*
* @param \hserv\System $system The system object.
* @param string|array $entities A comma-separated string or an array of entity names, or 'all'.
* @param bool|array $need_config Whether to include configuration information. If an array, it's populated with config data.
* @param array|null $search_params Optional parameters to filter definitions, e.g., by record ID or type.
* @return array|false An array of entity definitions, or false on error.
*/
function entityRefreshDefs( $system, $entities, $need_config, $search_params=null){

    $search_criteria = array();

    if($search_params!=null){

        if(!is_array($search_params) && intval($search_params)>0){
            $search_params = array('recID'=>$search_params);
        }

        //load definitions for particular record type only
        $mysqli = $system->getMysqli();
        if(@$search_params['recID']>0 || @$search_params['rty_ID']){
            $rec_ID = @$search_params['recID'];

            if($rec_ID>0){
                $rty_ID = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.intval($rec_ID));
                $search_criteria['defRecTypes'] = array('ID'=>$rty_ID);
            }else{
                $rty_ID = $search_params['rty_ID'];
            }

            if($rty_ID>0){
                $dty_IDs = mysql__select_list2($mysqli, 'SELECT rst_DetailTypeID FROM defRecStructure where rst_RecTypeID='.intval($rty_ID));
                $search_criteria['defRecStructure'] = array('rst_RecTypeID'=>$rty_ID, 'rst_DetailTypeID'=>$dty_IDs);
                $search_criteria['defDetailTypes'] = array('dty_ID'=>$dty_IDs);

                $trm_IDs = mysql__select_list2($mysqli, 'SELECT dty_JsonTermIDTree FROM defDetailTypes where dty_ID in ('.implode(',',$dty_IDs).') AND dty_Type="enum"');

                $entities = array('rty','dty','rst','swf');
            }
        }else{
            $entities = array_keys($search_params);
            $search_criteria = $search_params;
        }

    }elseif($entities=='all' || $entities==null){

        $entities = array('rty','dty','rst','trm','rtg','dtg','vcg','swf');

    }elseif(!is_array($entities)){
        $entities = explode(',',$entities);
    }

    $params = array();
    $res = array();
    if($need_config!==false) {$need_config = array();}

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

        $classname = 'hserv\entity\Db'.ucfirst($entity_name);
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
                $res[$entity_name]['trm_Icons'] = $entity->getTermIcons();
            }
        }
    }
    return $res;
}

/**
* Resolves an entity short name or alias to its full class name component.
*
* For example, 'rty' resolves to 'defRecTypes'.
* Validates that the resolved name contains only alphabetic characters.
*
* @param string $entity_name The short name or alias of the entity.
* @return string|null The resolved entity name component, or null if invalid.
*/
function entityResolveName($entity_name)
{
    if($entity_name=='rtg') {$entity_name = 'defRecTypeGroups';}
    elseif($entity_name=='dtg') {$entity_name = 'defDetailTypeGroups';}
    elseif($entity_name=='rty') {$entity_name = 'defRecTypes';}
    elseif($entity_name=='dty') {$entity_name = 'defDetailTypes';}
    elseif($entity_name=='trm' || $entity_name=='term' || $entity_name=='vocabulary') {$entity_name = 'defTerms';}
    elseif($entity_name=='vcg') {$entity_name = 'defVocabularyGroups';}
    elseif($entity_name=='rst') {$entity_name = 'defRecStructure';}
    elseif($entity_name=='rem') {$entity_name = 'dbUsrReminders';}
    elseif($entity_name=='swf') {$entity_name = 'sysWorkflowRules';}

    if(!preg_match('/^[A-Za-z]+$/', $entity_name)){ //validatate entity name
        return null;
    }

    return $entity_name;
}

/**
* Resolves the filename, content type, and URL for an entity's associated file (e.g., icon, thumbnail).
*
* @global string $defaultRootFileUploadURL The base URL for file uploads.
* @param string $entity_name The name of the entity.
* @param int|string $rec_id The record ID or database name (for sysDatabases).
* @param string|null $version The version of the file (e.g., 'icon', 'thumbnail', 'full'). Defaults to 'icon' for defRecTypes, 'thumbnail' otherwise.
* @param string|null $db_name The database name. Defaults to HEURIST_DBNAME if defined.
* @param string|null $extension The specific file extension to look for. If null, common image extensions are checked.
* @return array An array containing the absolute file path, content type, and URL. Returns [null, null, null] if not found or on error.
*/
function resolveEntityFilename($entity_name, $rec_id, $version, $db_name=null, $extension=null){
    global $defaultRootFileUploadURL;

    $entity_name = entityResolveName($entity_name);

    if($entity_name=='sysDatabases' && $rec_id){

        $db_name = $rec_id;
        if(strpos($rec_id, HEURIST_DB_PREFIX)===0){
            $db_name = substr($rec_id,strlen(HEURIST_DB_PREFIX));
        }
        $rec_id = 1;
        $path = '/entity/sysIdentification/';

    }else{

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
        //if version is not specified default is thumbnail (except for record types)
        $version = ($entity_name=='defRecTypes')?'icon':'thumbnail';
    }elseif($version=='thumb'){
        $version='thumbnail';
    }

    if($version!='full' && !($entity_name!='defRecTypes' && $version=='icon'))
    {
        $path = $path.$version.'/';
    }

    $filename = null;
    $content_type = null;
    $url = null;

    if(intval($rec_id)>0 && mysql__check_dbname($db_name)==null){

        $fname = HEURIST_FILESTORE_ROOT.$db_name.$path.intval($rec_id);

        $exts = $extension?array($extension):array('png','jpg','svg','jpeg','jpe','jfif','gif');
        foreach ($exts as $ext){
            if(file_exists($fname.'.'.$ext)){
                if($ext=='jpg' || $ext=='jfif' || $ext=='jpe'){
                    $content_type = 'image/jpeg';
                }elseif($ext=='svg'){
                    $content_type = 'image/svg+xml';
                }else{
                    $content_type = 'image/'.$ext;
                }
                $filename = $fname.'.'.$ext;
                $url =  $defaultRootFileUploadURL.urlencode($db_name).$path.$rec_id.'.'.$ext;
                break;
            }
        }
    }

    return array($filename, $content_type, $url);
}
?>