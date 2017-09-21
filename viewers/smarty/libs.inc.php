<?php

    /**
    * libs.inc.php: additional Smarty functions
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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

    require_once(dirname(__FILE__).'/../../common/config/initialise.php');

    define('SMARTY_DIR', HEURIST_DIR.'external/Smarty-3.0.7/libs/'); //was dirname(__FILE__)

    require_once(SMARTY_DIR.'Smarty.class.php');

    $smarty = new Smarty();

    //check folder existance and create new folders if they are missing
    if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR)){
        if (!mkdir(HEURIST_SMARTY_TEMPLATES_DIR, 0777, true)) {
            die('Failed to create folder for smarty templates');
        }
    }
    if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR."configs/")){
        if (!mkdir(HEURIST_SMARTY_TEMPLATES_DIR."configs/", 0777, true)) {
            die('Failed to create configs folder for smarty templates');
        }
    }
    if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR."templates_c/")){
        if (!mkdir(HEURIST_SMARTY_TEMPLATES_DIR."templates_c/", 0777, true)) {
            die('Failed to create templates_c folder for smarty templates');
        }
    }
    if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR."cache/")){
        if (!mkdir(HEURIST_SMARTY_TEMPLATES_DIR."cache/", 0777, true)) {
            die('Failed to create cache folder for smarty templates');
        }
    }

    $smarty->template_dir = HEURIST_SMARTY_TEMPLATES_DIR;
    $smarty->config_dir   = HEURIST_SMARTY_TEMPLATES_DIR."configs/";
    $smarty->compile_dir  = HEURIST_SMARTY_TEMPLATES_DIR.'templates_c/';
    $smarty->cache_dir    = HEURIST_SMARTY_TEMPLATES_DIR.'cache/';


    $smarty->registerResource("string", array("str_get_template",
        "str_get_timestamp",
        "str_get_secure",
        "str_get_trusted"));

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