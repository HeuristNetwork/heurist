<?php


/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* returns an attached file requested using the obfuscated file identifier
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Util
*/


  // NOTE: THis file updated 18/11/11 to use proper fiel paths and names for uploaded files rather than bare numbers

  require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
  require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
  require_once(dirname(__FILE__)."/uploadFile.php");
  require_once(dirname(__FILE__)."/fileUtils.php");

  if (@$_REQUEST['mobcfg']){
    downloadFile("text/xml", HEURIST_FILESTORE_DIR."settings/mobile-config.xml");
    return;
  }

  mysql_connection_select(DATABASE);

  // May be best to avoid the possibility of somebody harvesting ulf_ID=1, 2, 3, ...
  // so the files are indexed by the SHA-1 hash of the concatenation of the ulf_ID and a random integer.

  if (! @$_REQUEST['ulf_ID']) return; // nothing returned if no ulf_ID parameter

  $recID = null; //need for image annotations
  $filedata = get_uploaded_file_info_internal($_REQUEST['ulf_ID'], false);
  if($filedata==null) return; // nothing returned if parameter does not match one and only one row

  $type_source = $filedata['remoteSource'];
  $type_media = $filedata['mediaType'];

  $isplayer = (array_key_exists('player',$_REQUEST) &&  $_REQUEST['player']=='yes');
  $isannotation_editor = (defined('DT_ANNOTATION_RANGE') && defined('DT_ANNOTATION_RESOURCE') && @$_REQUEST['annedit']=='yes');


  if($isplayer){

    $size = '';
    if (array_key_exists('width',$_REQUEST)){
      $width =  $_REQUEST['width'];
      $size = 'width="'.$width.'" ';
    }else{
      $width =  '100%';
    }
    if (array_key_exists('height',$_REQUEST)){
      $height =  $_REQUEST['height'];
      $size = $size.' height="'.$height.'"';
    }else{
      $height =  '100%';
    }

    
    if($type_source=='youtube' || $filedata['mimeType'] == 'video/youtube' || $filedata['ext'] == 'youtube')
    {
      print linkifyYouTubeURLs($filedata['URL'], $size); //returns iframe
    }
    else if($type_source=='gdrive')
    {
        print linkifyGoogleDriveURLs($filedata['URL'], $size); //returns iframe
    }
    else if($type_media=='image')
    {
      $size = 'width="'.$width.'" height="'.$height.'"';

      $annot_edit = ($isannotation_editor)?'&annedit=yes':'';
      $origin = (!$isannotation_editor && @$_REQUEST['origin'])?'&origin='.$_REQUEST['origin']:'';

      $text = '<iframe '.$size.' src="'.HEURIST_BASE_URL.'records/files/mediaViewer.php?ulf_ID='.$_REQUEST['ulf_ID'].$annot_edit.'&db='.$_REQUEST['db'].$origin.'" frameborder="0"></iframe>';

      print $text;
    }
    else if($type_media=='document' && $filedata['mimeType']){

      print '<embed width="100%" height="80%" name="plugin" src="'.$filedata['URL'].'" type="'.$filedata['mimeType'].'" />';
    }
    else if($type_media=='video')
    {
      $size = ' height="280" width="420" ';
      print createVideoTag2($filedata['URL'], $filedata['mimeType'], $size);
    }
    else if($type_media=='audio')
    {

      print createAudioTag($filedata['URL'], $filedata['mimeType']);
    }

    exit;
  }

  if ($type_source==null || $type_source=='heurist')  //Local/Uploaded resources
  {
    // set the actual filename. Up to 18/11/11 this is jsut a bare nubmer corresponding with ulf_ID
    // from 18/11/11, it is a disambiguated concatenation of 'ulf_' plus ulf_id plus ulfFileName
    if ($filedata['fullpath']) {
      $filename = $filedata['fullpath']; // post 18/11/11 proper file path and name
    } else {
      $filename = HEURIST_FILESTORE_DIR ."/". $filedata['id']; // pre 18/11/11 - bare numbers as names, just use file ID
    }
    
    $filename = str_replace('/../', '/', $filename);  // not sure why this is being taken out, pre 18/11/11, unlikely to be needed any more
    $filename = str_replace('//', '/', $filename);
  }

  if(isset($filename) && file_exists($filename)){ //local resources

    if(false)
    {
      /*
      todo: waht is all this and when was it removed? Could it be useful for the furture. ? check with Artem, may be work related to kmls mid Nov 2011
      artem: THIS IS FOR showMap - to support kmz in timeline, it extracts kmz file and sends as kml to client side

      ($mimeExt=="kmz"){
      $zip=zip_open($filename);
      if(!$zip) {return("Unable to proccess file '{$filename}'");}
      $e='';
      while($zip_entry=zip_read($zip)) {
      $zdir=dirname(zip_entry_name($zip_entry));
      $zname=zip_entry_name($zip_entry);

      if(!zip_entry_open($zip,$zip_entry,"r")) {$e.="Unable to proccess file '{$zname}'";continue;}
      //if(!is_dir($zdir)) mkdirr($zdir,0777);

      #print "{$zdir} | {$zname} \n";

      $zip_fs=zip_entry_filesize($zip_entry);
      if(empty($zip_fs)) continue;

      $zz=zip_entry_read($zip_entry, $zip_fs);

      $zname = HEURIST_FILESTORE_DIR."/".$zname;
      /*	       $z=fopen($zname,"w");
      fwrite($z,$zz);
      fclose($z);
      zip_entry_close($zip_entry);

      }
      zip_close($zip);

      readfile($zname);
      unlink($z);
      */
    }else{

      // set the mime type, set to binary if mime type unknown
      downloadFile($filedata['mimeType'], $filename);

    }

  }else if ($filedata['URL']!=null && (strpos($filedata['URL'],'downloadFile.php')<1)  ){  //Remote resources - just redirect

    if($filedata['ext']=="kml"){
      // use proxy
      downloadViaProxy(HEURIST_FILESTORE_DIR."proxyremote_".$filedata['id'].".kml", $filedata['mimeType'], $filedata['URL']);

    }else{
      /* Redirect browser */
      //header('HTTP/1.1 201 Created', true, 201);
      //if you actually moved something to a new location (forever) use: header("HTTP/1.1 301 Moved Permanently");

      header('Location: '.$filedata['URL']);
    }
    /* Make sure that code below does not get executed when we redirect. */
    exit;
  }

  /**
   * create HTML5 video tag
   *
   * @param mixed $url
   * @param mixed $size
   */
  function createVideoTag($url, $mimeType, $size) {
    // width="320" height="240"
    return '<video '.$size.' controls="controls"><source src="'.$url.'" type="'.$mimeType.'"/>Your browser does not support the video element.</video>';
  }
  function createVideoTag2($url, $mimeType, $size) {
    return '<embed '.$size.' name="plugin" src="'.$url.'" type="'.$mimeType.'" controls="CONSOLE" controller="TRUE" />';
  }

  function createAudioTag($url, $mimeType) {
    // width="320" height="240"
    return '<audio controls="controls"><source src="'.$url.'" type="'.$mimeType.'"/>Your browser does not support the audio element.</audio>';
  }

  /**
   * Linkify youtube URLs which are not already links.
   *
   * @param mixed $text
   * @param mixed $size
   * @return mixed
   */
  function linkifyYouTubeURLs($text, $size) {

    if($size==null || $size==''){
      $size = 'width="420" height="345"';
    }

    $text = preg_replace('~
      # Match non-linked youtube URL in the wild. (Rev:20111012)
      https?://         # Required scheme. Either http or https.
      (?:[0-9A-Z-]+\.)? # Optional subdomain.
      (?:               # Group host alternatives.
      youtu\.be/      # Either youtu.be,
      | youtube\.com    # or youtube.com followed by
      \S*             # Allow anything up to VIDEO_ID,
      [^\w\-\s]       # but char before ID is non-ID char.
      )                 # End host alternatives.
      ([\w\-]{11})      # $1: VIDEO_ID is exactly 11 chars.
      (?=[^\w\-]|$)     # Assert next char is non-ID or EOS.
      (?!               # Assert URL is not pre-linked.
      [?=&+%\w]*      # Allow URL (query) remainder.
      (?:             # Group pre-linked alternatives.
      [\'"][^<>]*>  # Either inside a start tag,
      | </a>          # or inside <a> element text contents.
      )               # End recognized pre-linked alts.
      )                 # End negative lookahead assertion.
      [?=&+%\w]*        # Consume any URL (query) remainder.
      ~ix',
      '<iframe '.$size.' src="http://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>',
      $text);

    return $text;
  }

  function linkifyVimeoURLs($text, $size) {

    if($size==null || $size==''){
      $size = 'width="640" height="360"';
    }
    
    $hash = json_decode(file_get_contents("https://vimeo.com/api/oembed.json?url=".$text), true);
    $video_id = @$hash['video_id'];
    if($video_id>0){
       $res = '<iframe '.$size.' src="https://player.vimeo.com/video/'.$video_id.'" frameborder="0" '
      . ' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
    }else{
       $res = $text;  
    }
    //$res = preg_replace('(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([??0-9]{6,11})[?]?.*', $text);
  
    return $res;
  }  

  function linkifySoundcloudURL($url, $size) {

    if($size==null || $size==''){
      $size = 'height="166"';
    }
  
  return '<iframe width="100%" '.$size.' scrolling="no" frameborder="no" allow="autoplay" '
  .'src="https://w.soundcloud.com/player/?url='.$url.'&amp;auto_play=false&amp;hide_related=false&amp;show_comments=false&amp;show_user=false&amp;show_reposts=false&amp;show_teaser=false&amp;visual=true"></iframe>';
  
  }
  
function linkifyGoogleDriveURLs($text, $size) {
    if($size==null || $size==''){
        $size = 'width="420" height="345"';
    }

    return '<iframe '.$size.' src="'.$text.'" frameborder="0" allowfullscreen></iframe>';
}



  /*
  $file = file_get_contents('some.zip');
  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="some.zip"');
  header('Content-Length: ' . strlen($file));
  echo $file;
  */
?>
