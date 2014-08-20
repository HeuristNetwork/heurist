<?php

    /**
    * templateOperations.php: operations with Smarty template files - save, delete, get, list, serve, convert global <-> local
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    define('ISSERVICE',1);

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');


    $mode = $_REQUEST['mode'];

    if (! is_logged_in() && ! $mode=='serve') { // OK to serve tempalte files without login
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        //header('Content-type: text/html; charset=utf-8');
        return;
    }


    $dir = HEURIST_SMARTY_TEMPLATES_DIR;

    if($mode){ //operations with template files

        //get name of template file
        $template_file = (array_key_exists('template',$_REQUEST)?  urldecode($_REQUEST['template'])  :null);

        try{

            switch ($mode) {

                case 'list':
                    getList();
                    break;

                case 'get':
                    getTemplate($template_file);
                    break;

                case 'save':
                    //get template body from request (for execution from editor)
                    $template_body = (array_key_exists('template_body',$_REQUEST)?$_REQUEST['template_body']:null);
                    //add extension and save in default template directory

                    $template_file = $dir.$template_file;
                    $path_parts = pathinfo($template_file);
                    $ext = (array_key_exists('extension',$path_parts))?strtolower($path_parts['extension']):"";
                    if($ext!="tpl"){
                        $template_file = $template_file.".tpl";
                    }

                    $file = fopen ($template_file, "w");
                    if(!$file){
                        print json_format(array("error"=>"Can't write file. Check permission for smarty template directory"));
                        exit();
                    }
                    fwrite($file, $template_body);
                    fclose ($file);

                    print json_format(array("ok"=>$mode));

                    break;

                case 'delete':

                    $template_file = $dir.$template_file;
                    if(file_exists($template_file)){
                        unlink($template_file);
                    }else{
                        throw new Exception("Template file does not exist");
                    }

                    header("Content-type: text/javascript");
                    print json_format(array("ok"=>$mode));

                    break;

                case 'serve':
                // TODO: convert template file to global concept IDs and serve up to caller

                    smartyLocalIDsToConceptIDs($template_file);
                    break;

            }

        }
        catch(Exception $e)
        {
            header("Content-type: text/javascript");
            print json_format(array("error"=>$e->getMessage()));
        }

    }

    exit();


    /**
    * Returns the list of available tempaltes as json array
    */
    function getList(){

        global $dir;

        $files = scandir($dir);
        $results = array();

        foreach ($files as $filename){
            //$ext = strtolower(end(explode('.', $filename)));
            //$ext = preg_replace('/^.*\.([^.]+)$/D', '$1', $filename);

            $path_parts = pathinfo($filename);
            if(array_key_exists('extension', $path_parts))
            {
                $ext = strtolower($path_parts['extension']);
                /*****DEBUG****///error_log(">>>>".$path_parts['filename']."<<<<<");//."    ".$filename.indexOf("_")."<<<<");

                $ind = strpos($filename,"_");
                $isnot_temp = (!(is_numeric($ind) && $ind==0));

                if(file_exists($dir.$filename) && $ext=="tpl" && $isnot_temp)
                {
                    //$path_parts['filename'] )); - does not work for nonlatin names
                    $name = substr($filename, 0, -4);
                    array_push($results, array( 'filename'=>$filename, 'name'=>$name));
                }
            }
        }
        header("Content-type: text/javascript");
        //header('Content-type: text/html; charset=utf-8');
        /*****DEBUG****/// error_log(">>>>>>>>>>>>>".print_r($results, true));

        print json_format( $results, true );
    }


    /**
    * Returns the content of template file as text
    *
    * @param mixed $filename - name of template file
    */
    function getTemplate($filename){
        global $dir;

        if($filename && file_exists($dir.$filename)){
            header('Content-type: text/html; charset=utf-8');
            print file_get_contents($dir.$filename);
        }else{
            header("Content-type: text/javascript");
            print json_format(array("error"=>"file not found"));
        }
    }


    /**
    * Returns the content of template file as text with local IDs replaced by concept IDs or viseverse
    *
    * @param mixed $filename - name of template file
    * @param mixed $mode - 0 - to concept code, 1 - to local code
    */
    function convertTemplate($filename, $mode){
        global $dir;
        
        //1. get template content
        //2. find all texts within {}
        //3. find words within this text
        //4. split by .
        //5. find starting with "f"
        //6. get local DT ID - find Concept Code
        //7. replace

        //1. get template content
        if($filename && file_exists($dir.$filename)){
            $template = file_get_contents($dir.$filename);
        }else{
            return array("error"=>"File $filename not found");
        }
        
        //2. find all texts within {} - expressions
        if (! preg_match_all('/\{([^}]+)\}/s', $template, $matches)){
            return $template;    // nothing to do -- no substitutions
        }

        $not_found_details = array();
        $replacements_exp = array();
        
        $len = count($matches[1]);
        for ($i=0; $i < $len; ++$i) {

            $exp = $matches[1][$i];
            if(!trim($exp)) continue; //empty{}
            if(substr($exp,0,1)=="*" && substr($exp,-1)=="*") continue; //this is comment
        
        //3. find words within this text
            if (! preg_match_all('/(\\$([a-zA-Z_0-9.])+)/', $exp, $matches2) ){
                continue;
            }
            
            $replacements = array();
        
            foreach ($matches2[1] as $var) {
        //4. split by "."
                    $parts = explode(".", $var);
                    $parts2 = array();
                    foreach ($parts as $part) {
        //5. find starting with "f"
                        if(strpos($part, "f")===0){
        //6. get local DT ID - find Concept Code
                            if($mode==0){
                                $localID = substr($part,1);
                                if(strpos($localID,"-")===false){
                                    $conceptCode = getDetailTypeConceptID($localID);
                                    $part = "f".$conceptCode;
                                }
                            }else{
                                $conceptCode = substr($part,1) ;
                                if(strpos($conceptCode,"-")!==false){
                                    $localID = getDetailTypeLocalID($conceptCode);
                                    if($localID==0){
                                        //local code not found - it means that this detail is not in this database
                                        array_push($not_found_details, $conceptCode);
                                    }else{
                                        $part = "f".$localID;
                                    }
                                }
                            }
                        }
                        array_push($parts2, $part);
                    }
                    $new_var = implode(".", $parts2);        
                    
                    if($var!=$new_var){
                        $replacements[$var] = $new_var;  
                    }
            }//for vars
            
            if(count($replacements)>0){
                   $new_exp = "{".array_str_replace(array_keys($replacements), array_values($replacements), $exp)."}";
                   if($matches[0][$i] != $new_exp){
                        $replacements_exp[$matches[0][$i]] = $new_exp;
                   }
            }
        }//for expressions
        
        if(count($not_found_details)>0){
            
            return array("error"=>count($not_found_details)." of concept codes cannot be translated to local codes", "details"=>$not_found_details); 
            
        }else{
        
            if(count($replacements_exp)>0){
                $template = array_str_replace(array_keys($replacements_exp), array_values($replacements_exp), $template);
            }
            
            if($mode==0){
                    header('Content-type: html/text');
                    header('Content-disposition: attachment; filename='.str_replace(".tpl",".gpl",$filename));                
            }
            
            print $template; //"<hr><br><br><xmp>".$template."</xmp>";
        }
    }

    /**
    * Returns the content of template file as text with local IDs replaced by concept IDs
    *
    * @param mixed $filename - name of template file
    */
    function smartyLocalIDsToConceptIDs($filename){
        global $dbID;
        
        if(!$dbID){
            return array("error"=>"Database must be registered to allow translation of local template to global template");    
        }
        
        return convertTemplate($filename, 0);
    }

    /**
    * Returns the content of global concept IDs stream as text with local IDs
    *
    * @param mixed $instream - source data with global concept IDs
    */
    function smartyConceptIDsToLocalIDs($instream){
        return convertTemplate($filename, 1);
    }

    if (! function_exists('array_str_replace')) {

    function array_str_replace($search, $replace, $subject) {
        /*
         * PHP's built-in str_replace is broken when $search is an array:
         * it goes through the whole string replacing $search[0],
         * then starts again at the beginning replacing $search[1], &c.
         * array_str_replace instead looks for non-overlapping instances of each $search string,
         * favouring lower-indexed $search terms.
         *
         * Whereas str_replace(array("a","b"), array("b", "x"), "abcd") returns "xxcd",
         * array_str_replace returns "bxcd" so that the user values aren't interfered with.
         */

        $val = '';

        while ($subject) {
            $match_idx = -1;
            $match_offset = -1;
            for ($i=0; $i < count($search); ++$i) {
                if($search[$i]==null || $search[$i]=='') continue;
                $offset = strpos($subject, $search[$i]);
                if ($offset === FALSE) continue;
                if ($match_offset == -1  ||  $offset < $match_offset) {
                    $match_idx = $i;
                    $match_offset = $offset;
                }
            }

            if ($match_idx != -1) {
                $val .= substr($subject, 0, $match_offset) . $replace[$match_idx];
                $subject = substr($subject, $match_offset + strlen($search[$match_idx]));
            } else {    // no matches for any of the strings
                $val .= $subject;
                $subject = '';
                break;
            }
        }

        return $val;
    }

    }
?>
