<?php

    /**
    * Localization utilities
    * 
    * getLangCode3 - validates lang code and returns upper case 3 letters code
    * extractLangPrefix - splits and extract language code and value from string code:value
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

    //
    // get 3 letters ISO code
    //
    function getLangCode3($lang){
        global $glb_lang_codes, $glb_lang_codes_index;

        $res = null;
        
        if ($lang) { 

            if(!isset($glb_lang_codes)){
                $glb_lang_codes = json_decode(file_get_contents('../../hclient/assets/language-codes-3b2.json'),true);
                foreach($glb_lang_codes as $codes){
                    $glb_lang_codes_index[strtoupper($codes['a3'])] = strtoupper($codes['a2']);
                }
            }
            
            $lang = strtoupper($lang);
            if(strlen($lang)==3){
                $lang = strtoupper($lang);
                if(@$glb_lang_codes_index[$lang]!=null){
                    $res = $lang;
                }
            }else{
                $res = array_search($lang, $glb_lang_codes_index);
            }

            /*            
            $key = (strlen($lang)==2)?'a2':'a3';
            foreach($glb_lang_codes as $codes){
                if(strcasecmp($codes[$key], $lang)===0){
                    $res = $codes['a3'];
                    break;
                }
            }*/
            
        }
        
        return $res;
    }
    
    //
    //  splits and extract language code and value from string code:value
    //  if $val is 2 chars code ISO639-1 - it will be converted to 3 chars ISO639-2
    //    
    function extractLangPrefix($val){
        
        //global $glb_lang_codes, $common_languages_for_translation;
    
        if(is_string($val) && mb_strlen($val)>4){
            
            
            if(substr($val,0,2)=='*:'){
                $lang = 'ALL';
                $pos = 2;
            }else{
            
                $lang = null;
                
                if($val[2]==':'){
                    $lang = substr($val,0,2);
                    $pos = 3;
                    
                    /*find 3 chars code - move to utlities
                    if(!isset($glb_lang_codes)){
                        $glb_lang_codes = json_decode(file_get_contents('../../hclient/assets/language-codes-3b2.json'),true);
                    }
                    foreach($glb_lang_codes as $codes){
                        if(strcasecmp($codes['a2'],$lang)===0){
                            $lang = $codes['a3'];
                            break;
                        }
                    }*/
                    
                }else if($val[3]==':'){
                    $lang = substr($val,0,3);
                    $pos = 4;
                }
                
                if($lang){
                    $lang = getLangCode3($lang); //validate
                }
            }

            if($lang){
                
                //if (strcasecmp($lang,'ALL')===0 || in_array($lang, $common_languages_for_translation)){
                return array($lang, substr($val, $pos));
            }
        } 
        
        return array(null, $val);    
    }    
    
    //
    // For smarty modifier "translate"
    //
    function getTranslation($input, $lang, $field=null){
        global $smarty;
        
        $res = null;
        $lang = getLangCode3($lang);
        
        //detect if it is usual record or term
        if(is_array($input) && (@$input['term'] || (is_array(@$input[0]) && @$input[0]['term']))){
            
            if($field==null) $field = 'label';
            
            $trm = @$input[0]?$input[0]:$input;
            
            if(isset($smarty)){
                
                //$heuristRec = @$smarty['tpl_vars']['heurist']['value'];
                
                $heuristRec = $smarty->getTemplateVars('heurist');       
                if($heuristRec){
                    return $heuristRec->getTranslation('trm', $trm['id'], $field, $lang);
                }
            }
            return $trm[$field];
        }
        
        
        // this is record detail field;
        if(is_array($input)){
            
            $def = null;
            foreach($input as $val){
                if(is_string($val)){
                    list($lang_, $val) = extractLangPrefix($val);

                    if ($lang_==$lang){
                        $res = $val;
                        break;
                    }else if($lang_==null){
                        $def = $val;
                    }
                }
            }
            if($res==null){
                $res = $def;
            }
            
        }else if(is_string($input)) {
            list($lang_, $res) = extractLangPrefix($input); //there is no localization
        }
        
        
        return ($res==null)?$input:$res;
    }
    
    //
    // For smarty records - it returns translated value for multivalue field 
    // if all values have language prefix (except default one)
    //
    function getCurrentTranslation($input, $lang){
    
        $res = null;
        
        if(is_array($input)){
            
            $lang = getLangCode3($lang);
            $def = null;
            $fnd = null;
            $cnt = 0;
            //all values except one must be with lang: prefix
            foreach($input as $val){
                
                list($lang_, $val) = extractLangPrefix($val);

                if ($lang_==$lang){
                    $cnt++;
                    $fnd = $val;
                }else if($lang_==null){
                    $def = $val;
                }
            }
            if($cnt==count($input)-1){
                $res = $fnd?$fnd:$def;                
            }
        }
        
        return $res;
    }
?>
