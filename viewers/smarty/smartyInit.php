<?php
    /**
    * smartyInit.php: additional Smarty functions
    * this file should be included after system init
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    //smarty is already included via composer autoload in showReps.php    
    //define('SMARTY_DIR', HEURIST_DIR.'external/Smarty-3.0.7/libs/'); 
    //require_once(SMARTY_DIR.'Smarty.class.php');
    $smarty = null;
    
    initSmarty();


function initSmarty($smarty_templates_dir=null){
    global $smarty;
    
    if($smarty_templates_dir==null && defined('HEURIST_SMARTY_TEMPLATES_DIR')){
        $smarty_templates_dir = HEURIST_SMARTY_TEMPLATES_DIR;
    }
    
    if($smarty_templates_dir){
    
        $smarty = new Smarty();
        
        //check folder existance and create new folders if they are missing
        if(!folderCreate($smarty_templates_dir, true)){
            die('Failed to create folder for smarty templates');
        }
        if(!folderCreate($smarty_templates_dir.'configs/', true)){
            die('Failed to create configs folder for smarty templates');
        }
        if(!folderCreate($smarty_templates_dir.'templates_c/', true)){
            die('Failed to create templates_c folder for smarty templates');
        }
        if(!folderCreate($smarty_templates_dir.'cache/', true)){
            die('Failed to create cache folder for smarty templates');
        }

        $smarty->setTemplateDir($smarty_templates_dir);
        $smarty->setCompileDir($smarty_templates_dir.'templates_c');
        $smarty->setCacheDir($smarty_templates_dir.'cache');
        $smarty->setConfigDir($smarty_templates_dir.'configs');    
        
        $smarty->registerResource("string", array("str_get_template",
            "str_get_timestamp",
            "str_get_secure",
            "str_get_trusted"));

        //$smarty->register_modifier('translate', 'getTranslation'); it does not work for standarad smarty distribution
        $smarty_plugin_dir = HEURIST_DIR.'/vendor/smarty/smarty/libs/plugins/';
        $modifier_translate = 'modifier.translate.php';
        //add new modifier - if it does not exist
        if(!file_exists($smarty_plugin_dir.$modifier_translate) && file_exists($smarty_plugin_dir)){
            copy($modifier_translate,$smarty_plugin_dir.$modifier_translate);
        }
            
    }
}
        
function str_get_template ($tpl_name, &$tpl_source, &$smarty_obj)
{
    $tpl_source = $tpl_name;
    $tpl_name = "from_str_test";
    // return true on success, false to generate failure notification
    return true;
}

function str_get_timestamp($tpl_name, &$tpl_timestamp, &$smarty_obj)
{
    // do database call here to populate $tpl_timestamp
    // with unix epoch time value of last template modification.
    // This is used to determine if recompile is necessary.
    $tpl_timestamp = time(); // this example will always recompile!
    // return true on success, false to generate failure notification
    return true;
}

function str_get_secure($tpl_name, &$smarty_obj)
{
    // assume all templates are secure
    return true;
}

function str_get_trusted($tpl_name, &$smarty_obj)
{
}
?>