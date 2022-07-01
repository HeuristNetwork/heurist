<?php

    /**
    * reportActions.php: operations with Smarty template files - save, delete, get, list, serve, convert global <-> local
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
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

require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/conceptCode.php');
    
class ReportActions {
    
    protected $system;
    protected $dir; //smarty folder
    
    function __construct( $_system, $_dir) {
       global $system; 
       
       if($_system){
            $this->system = $_system;    
       }else{
           $this->system = $system;
       }
       
       
       if($_dir){
           $this->dir = $_dir;
       }else if(defined('HEURIST_SMARTY_TEMPLATES_DIR')){
            $this->dir = HEURIST_SMARTY_TEMPLATES_DIR;    
       }
    }    
    

    /**
    * Returns the list of available tempaltes as json array
    * 
    *  1. checks for gpl in template folder
    *  2. convert them to tpl
    *  3. return all tpl names
    */
    public function getList(){

        $files = scandir($this->dir);
        $results = array();

        foreach ($files as $filename){
            //$ext = strtolower(end(explode('.', $filename)));
            //$ext = preg_replace('/^.*\.([^.]+)$/D', '$1', $filename);

            $path_parts = pathinfo($filename);
            if(array_key_exists('extension', $path_parts))
            {
                $ext = strtolower($path_parts['extension']);

                $ind = strpos($filename,"_");
                $isnot_temp = (!(is_numeric($ind) && $ind==0));

                if(file_exists($this->dir.$filename) && $ext=="gpl"){

                    // gpl->tpl
                    $template_body = file_get_contents($this->dir.$filename);
                    $res = $this->convertTemplate($template_body, 1); //to local codes
                    
                    if(is_array($res) && @$res['details_not_found']){
                        //error except Harvard Bibliography (since many databases do not have biblio defs by default)
                        if($filename!='Harvard Bibliography.gpl'){
                            error_log('Cant convert gpl template '.$this->dir.$filename
                                .'. Local details not found '.print_r($res['details_not_found'],true)); 
                        }
                        
                    }else if(is_array($res) && @$res['template']) {
                        $name = substr($filename, 0, -4);
                        $filename_tpl = $name.'.tpl';
                        $template_body = $res['template'];
                        
                        //save tpl
                        $res = $this->saveTemplate($template_body, $this->dir.$filename_tpl);

                        if(@$res['ok']){
                            //remove gpl 
                            fileDelete($this->dir.$filename);
                            array_push($results, array( 'filename'=>$filename_tpl, 'name'=>$name));
                        }else{
                            error_log('Cant save template '.$this->dir.$filename_tpl.' '.print_r($res,true)); 
                        }
                    }else{
                        error_log('Unknow issue on gpl convertation '.$this->dir.$filename.'. '.print_r($res,true)); 
                    }
                       
                    
                }else if(file_exists($this->dir.$filename) && $ext=="tpl" && $isnot_temp)
                {
                    //$path_parts['filename'] )); - does not work for nonlatin names
                    $name = substr($filename, 0, -4);
                    array_push($results, array( 'filename'=>$filename, 'name'=>$name));
                }
            }
        }
        header("Content-type: text/javascript");
        print json_encode( $results, true );
    }


    /**
    * Returns the content of template file as text
    *
    * @param mixed $filename - name of template file
    */
    public function getTemplate($filename){

        if($filename && file_exists($this->dir.$filename)){
            header('Content-type: text/html; charset=utf-8');
            print file_get_contents($this->dir.$filename);
        }else{
            header("Content-type: text/javascript");
            print json_encode(array("error"=>"file not found"));
        }
    }
    
    //
    //
    //
    function getUniqueTemplateName($template_file){

         

        $path_parts = pathinfo($template_file);
        $template_file = $path_parts['filename'];
        $cnt = 0;
        
        $template_file_fullpath = $this->dir.$template_file.'.tpl';
        
        do{
            if(file_exists($template_file_fullpath)){
                if($cnt>0){
                    $cnt = $cnt+1;
                }else{
                    $k = strpos($template_file_fullpath,'(');
                    $k2 = strpos($template_file_fullpath,').tpl');
                    $cnt = intval(substr($template_file_fullpath,$k,$k2-$k));
                    $cnt = ($cnt>1)?$cnt+1:1;
                }
                $template_file_fullpath = $this->dir.$template_file."($cnt).tpl";
            }
        }while (file_exists($template_file_fullpath));
        
        return $template_file_fullpath;
    }

    //
    //
    //
    public function  saveTemplate($template_body, $template_file){
         

        $path_parts = pathinfo($template_file);
        $template_file = $path_parts['filename'];
        $template_file = $template_file.".tpl";
        $template_file_fullpath = $this->dir.$template_file;
        
        /*$ext = (array_key_exists('extension',$path_parts))?strtolower($path_parts['extension']):"";
        if($ext!="tpl"){
            $template_file = $template_file.".tpl";
        }*/

        $file = fopen ($template_file_fullpath, "w");
        if(!$file){
            return array("error"=>"Can't write file. Check permission for smarty template directory");
        }
        fwrite($file, $template_body);
        fclose ($file);
        return array("ok"=>$template_file);
    }

    
    private function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }    

    /**
    * Returns the content of template file as text with local IDs replaced by concept IDs or viseverse
    *
    * @param mixed $filename - name of template file
    * @param mixed $mode - 0 - to concept code, 1 - to local code
    */
    public function convertTemplate($template, $mode){
        
        //1. get template content
        //2. find all texts within {}
        //3. find words within this text
        //4. split by .
        //5. find starting with "f"
        //6. get local DT ID - find Concept Code
        //7. replace

        //1. get template content
        if($template==null || $template==''){
            return array("error"=>"Template is empty");    
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
                        if(strpos($part, 'f')===0){
                            $prefix = 'f';
                        }else if(strpos($part, '$f')===0){
                            $prefix = '$f';
                        }else{
                            $prefix = null;
                        }
        
                        if($prefix){
        //6. get local DT ID - find Concept Code
                            $code = substr($part, strlen($prefix));
                            if(substr($part, -1)=='s'){
                                    $suffix = 's';
                                    $code = substr($code,0,strlen($code)-1);
                            }else if($this->endsWith($part,'_originalvalue')){                                            
                                    $suffix = '_originalvalue';
                                    $code = substr($code,0,strlen($code)-strlen($suffix));
                            }else{
                                    $suffix = "";
                            }
                            
                            if($mode==0){
                                $localID = $code;
                                if(strpos($localID,"_")===false){
                                    $conceptCode = ConceptCode::getDetailTypeConceptID($localID);
                                    $part = $prefix.str_replace("-","_",$conceptCode).$suffix;
                                }
                            }else{
                                $conceptCode = $code;
                                
                                if(strpos($conceptCode,"_")!==false){
                                    $conceptCode = str_replace("_","-",$conceptCode);
                                    
                                    $localID = ConceptCode::getDetailTypeLocalID($conceptCode);
                                    if($localID==null){
                                        //local code not found - it means that this detail is not in this database
                                        array_push($not_found_details, $conceptCode);
                                        $part = $prefix."[[".$conceptCode."]]".$suffix;
                                    }else{
                                        $part = $prefix.$localID.$suffix;
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
                   $new_exp = "{".$this->array_str_replace(array_keys($replacements), array_values($replacements), $exp)."}";
                   if($matches[0][$i] != $new_exp){
                        $replacements_exp[$matches[0][$i]] = $new_exp;
                   }
            }
        }//for expressions
        
        
        if(count($replacements_exp)>0){
             $template = $this->array_str_replace(array_keys($replacements_exp), array_values($replacements_exp), $template);
        }

        if($mode==1){
            return array("template"=>$template, "details_not_found"=>$not_found_details); 
        }else{
            return $template;
        }
        
    }

    /**
    * Returns the content of template file as text with local IDs replaced by concept IDs
    *
    * @param mixed $filename - name of template file
    */
    public function smartyLocalIDsToConceptIDs($filename, $template=null){
        
        $dbID = $this->system->get_system('sys_dbRegisteredID');
        $res = null;
        
        if(!$dbID){
             $res = array("error"=>"Database must be registered to allow translation of local template to global template");    
        }else {

            if($filename){
                if(file_exists($this->dir.$filename)){
                    $template = file_get_contents($this->dir.$filename);    
                }else{
                    $res = array('error'=>"File $filename not found");    
                }
            }
            
            if(!$res){
                if($template && strlen($template)>0){
                    $res = $this->convertTemplate($template, 0);
                }else{
                    $res = array('error'=>'Template is not defined or empty');    
                }
            }
            
        }
        
        if(is_array($res)){
            header("Content-type: text/javascript");
            print json_encode($res);
        }else{
            header('Content-type: html/text');
            if($filename){
                header('Content-Disposition: attachment; filename='.str_replace(".tpl",".gpl",$filename));                
            }
            print $res; //"<hr><br><br><xmp>".$template."</xmp>";
        }
    }
    
    /**
    * Returns the content of global concept IDs stream as text with local IDs
    *
    * @param mixed $instream - source data with global concept IDs
    */
    public function importTemplate($params){
         
        
        if ( !@$params['size'] ) {
            $res = array("error"=>'Error occurred during upload - file had zero size');
            
        }else{
            $filename = $params['tmp_name'];
            $origfilename = $params['name'];
            
            if(strpos($filename,'cms/')===0){
                $path = dirname(__FILE__).'/../../hclient/widgets/cms/templates/snippets/';
                $path = realpath($path);
                $filename = $path.DIRECTORY_SEPARATOR.substr($filename,4);
            }

            //read tempfile
            $template = file_get_contents($filename);
        
            $res = $this->convertTemplate($template, 1);
            
            if(!is_array($res)){
                $res = array('template'=>$res);
            }
            
            if(!@$res['error']){
                  //check if template with such name already exists 
                  /*while (file_exists($this->dir.$origfilename)){
                      $this->dir.$origfilename = $this->dir.$origfilename . "($cnt)";
                  }*/
                  $origfilename = $this->getUniqueTemplateName($origfilename);
                
                  $res2 = $this->saveTemplate($res['template'], $origfilename);
                  if(count(@$res['details_not_found'])>0){
                      $res2['details_not_found'] = $res['details_not_found'];
                  }
                  $res = $res2;
            }
        }
        
        //header("Content-type: text/javascript");
        header('Content-type: application/json');
        print json_encode($res);
        //print json_encode($res);
    }

//if (! function_exists('array_str_replace')) {

    private function array_str_replace($search, $replace, $subject) {
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

//}

}
?>
