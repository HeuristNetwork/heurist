<?php
/**
* getTitleFromURL.php -  Retrieves the title of a webpage given its URL.
* 
* @project     Heurist academic knowledge management system
* @package  import\hyperlinks
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/
require_once dirname(__FILE__).'/../../autoload.php';

header(CTYPE_JSON);

$title = '';

$url = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);

$rv = array('num'=>$_REQUEST['num']);

$system = new hserv\System();
if(!$system->init(@$_REQUEST['db'])){
    print json_encode( $system->getError() );
}elseif(!$system->hasAccess() ){
    print json_encode( $system->addError(HEURIST_REQUEST_DENIED) );
}elseif ( !$url  ||  (!intval($_REQUEST['num'])  &&  $_REQUEST['num'] != 'popup')) {
    print json_encode( $system->addError(HEURIST_INVALID_REQUEST), 'URL is not defined' );
}else{

    $url = str_replace(' ', '+', $url);

    $data = loadRemoteURLContentWithRange($url, "0-10000");//get title of webpage

    if ($data){

        preg_match('!<\s*title[^>]*>\s*([^<]+?)\s*</title>!is', $data, $matches);
        if ($matches) {
            $title = preg_replace('/\s+/', ' ', $matches[1]);
        }

        if ($title) {
            $rv['title']=$title;
            //type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            //if (preg_match('!^image/!i', $type)) {
            //preg_match('!.*/(.*)!', $_REQUEST['url'], $matches);
            //$title = 'Image - ' . $matches[1];
            //}
        }else{
            $rv['error']='Title is not defined';
        }


    }else{
        $rv['error']='URL could not be retrieved';
    }


    print json_encode(array('status'=>HEURIST_OK, 'data'=>$rv));
}
