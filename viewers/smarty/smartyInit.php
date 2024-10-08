<?php
    /**
    * smartyInit.php: init Smarty and additional functions
    * this file should be included after system init
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

require_once dirname(__FILE__).'/../../vendor/autoload.php';
require_once dirname(__FILE__).'/../../hserv/utilities/ULocale.php';


use Smarty\Smarty;
use Smarty\Security;
use Smarty\Template;

//require_once dirname(__FILE__).'/../../vendor/smarty/smarty/libs/Smarty.class.php';
class Heurist_Security_Policy extends Security {

  // disable acess to static classes
  public $static_classes = null;

  public $allowed_modifiers = array('isset', 'empty', 'escape',
                    'sizeof', 'in_array', 'is_array', 'intval', 'implode', 'explode',
                    'array_key_exists', 'array_column', 'array_multisort', 
                    'array_diff', 'array_count_values', 'array_unique',
                    'asort', 'array_merge', 'array_slice', 'array_values', 'cat',
                    'capitalize', 
                    'count','count_characters','count_words',
                    'date_format',
                    'floatval','indent','json_encode',
                    'nl2br', 
                    'preg_match_all','print_r', 'printf','replace',
                    'setlocale','sort', 'strstr', 'substr', 'strpos', 'strlen', 
                    'time','translate','truncate',
                    'out','wrap',                    
                    'utf8_encode');


  public $allow_super_globals = false; //default true  

  public $allowed_tags = false;
  
}
    
$smarty = null;

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
        if(!folderCreate($smarty_templates_dir.'compiled/', true)){
            die('Failed to create compiled folder for smarty templates');
        }
        if(!folderCreate($smarty_templates_dir.'cache/', true)){
            die('Failed to create cache folder for smarty templates');
        }

        $smarty->setTemplateDir($smarty_templates_dir);
        $smarty->setCompileDir($smarty_templates_dir.'compiled');
        $smarty->setCacheDir($smarty_templates_dir.'cache');
        $smarty->setConfigDir($smarty_templates_dir.'configs');
        
        // enable security
        $smarty->enableSecurity('Heurist_Security_Policy');
        
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'translate', 'smarty_modifier_translate');

        //allowed php functions
        $php_functions = array( 'constant', 'count', 
                    'sizeof', 'in_array', 'is_array', 'intval', 'implode', 'explode',
                    //'array_key_exists', 'array_column', 
                    'array_count_values', 'array_multisort', 
                    'array_diff', 'array_merge', 'array_slice', 'array_unique',
                    'array_values', 'asort', 
                    'floatval','json_encode',
                    'ksort', 'nl2br', 
                    'preg_match_all','print_r','printf', 'range',
                    'setlocale', 'sort', 'strstr', 'substr', 'strpos', 'strlen', 
                    'time',
                    'utf8_encode');
                    
        foreach($php_functions as $fname){
            $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, $fname, $fname);    
        }
        
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'array_key_exists', 'heurist_modifier_array_key_exists');
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'array_column', 'heurist_modifier_array_column');
    }
}

function heurist_modifier_array_key_exists($key, $arr){
    return is_array($arr) && array_key_exists($key, $arr);
}

function heurist_modifier_array_column($arr, $column){
    if(is_array($arr)){ // && array_key_exists($column, $arr[0])
        return array_column($arr, $column);
    }else{
        return '';
    }
}


function smarty_modifier_translate($input, $lang, $field=null)
{
    return getTranslation($input, $lang, $field);//see uLocale
}
?>