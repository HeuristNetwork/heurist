<?php
    /**
    * smartyInit.php: additional Smarty functions
    * this file should be included after system init
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

    //smarty is already included via composer autoload in showReps.php    
    //define('SMARTY_DIR', HEURIST_DIR.'external/Smarty-3.0.7/libs/'); 
    //require_once(SMARTY_DIR.'Smarty.class.php');
    $smarty = null;
    
    initSmarty();


function initSmarty(){
    global $smarty;
    
    if(defined('HEURIST_SMARTY_TEMPLATES_DIR')){
    
        $smarty = new Smarty();
        
        //check folder existance and create new folders if they are missing
        if(!folderCreate(HEURIST_SMARTY_TEMPLATES_DIR, true)){
            die('Failed to create folder for smarty templates');
        }
        if(!folderCreate(HEURIST_SMARTY_TEMPLATES_DIR.'configs/', true)){
            die('Failed to create configs folder for smarty templates');
        }
        if(!folderCreate(HEURIST_SMARTY_TEMPLATES_DIR.'templates_c/', true)){
            die('Failed to create templates_c folder for smarty templates');
        }
        if(!folderCreate(HEURIST_SMARTY_TEMPLATES_DIR.'cache/', true)){
            die('Failed to create cache folder for smarty templates');
        }

        $smarty->setTemplateDir(HEURIST_SMARTY_TEMPLATES_DIR);
        $smarty->setCompileDir(HEURIST_SMARTY_TEMPLATES_DIR.'templates_c');
        $smarty->setCacheDir(HEURIST_SMARTY_TEMPLATES_DIR.'cache');
        $smarty->setConfigDir(HEURIST_SMARTY_TEMPLATES_DIR.'configs');    
        
        $smarty->registerResource("string", array("str_get_template",
            "str_get_timestamp",
            "str_get_secure",
            "str_get_trusted"));
            
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
    // not used for templates
}


?>