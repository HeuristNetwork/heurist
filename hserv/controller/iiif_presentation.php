<?php
/**
* iiif_presentation.php - Handler to produce IIIF presentation for registered Heurist file
* 
* It uses ExportRecordsIIIF to produce json output as a representations of iiif objects in Presentation API v3 
* .(see https://iiif.io/api/presentation/3.0/)
* 
* parameters
* db - The target Heurist database name.
* resource - The name/type of the IIIF resource to generate (e.g., Canvas, AnnotationPage, Annotation, Image).
* id - Unique identifier of the object, typically the obfuscated ID of a registered image file in Heurist.
*
* @package     Heurist academic knowledge management system
* @subpackage  controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       6.0
*/
require_once dirname(__FILE__).'/../../autoload.php';

    $response = array();

    if(isset($req_params)){
        $params = $req_params; //from api.php
    }else{
        $params = $_REQUEST;
    }


    if(!isset($system) || $system==null){

        $system = new hserv\System();

        if( ! $system->init(@$params['db']) ){
            //get error and response
            $system->errorExitApi();//exit from script
        }
    }

    if(!(array_key_exists('id',$params)
        && $params['id']!='' && $params['id']!=null)){

        $system->errorExitApi('Resource id is not defined');//exit from script
    }

    if(!(array_key_exists('resource',$params)
        && $params['resource']!='' && $params['resource']!=null)){


    }

    $res = hserv\records\export\ExportRecordsIIIF::getIiifResource($system, null, 3, $params['id'], @$params['resource']);

    $system->dbclose();

    if($res) {
        header(HEADER_CORS_POLICY);
        $system->setResponseHeader();
        print $res;
    }else{
        $system->errorExitApi();
    }
?>