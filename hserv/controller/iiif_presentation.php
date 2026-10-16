<?php
/**
* iiif_presentation.php - Handler to produce IIIF presentation for registered Heurist file
* 
* It uses IiifPresentationService to produce JSON output as IIIF Presentation API v3 resources 
* .(see https://iiif.io/api/presentation/3.0/)
* 
* parameters
* db - The target Heurist database name.
* resource - The IIIF Presentation resource to generate: manifest, canvas, page, annotation, or annotations.
* id - Unique identifier of the object, typically the obfuscated ID of a registered Heurist file.
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

        $params['resource'] = 'manifest';
    }

    $omitAnnotationPages = !empty($params['omit_annotation_pages']) && intval($params['omit_annotation_pages']) === 1;
    $service = new hserv\iiif\IiifPresentationService($system);
    $res = $service->getResourceJson($params['resource'], $params['id'], array(
        'omit_annotation_pages' => $omitAnnotationPages
    ));

    $system->dbclose();

    if($res) {
        header(HEADER_CORS_POLICY);
        $system->setResponseHeader();
        print $res;
    }else{
        $system->errorExitApi();
    }
?>