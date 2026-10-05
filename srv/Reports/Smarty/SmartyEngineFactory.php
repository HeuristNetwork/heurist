<?php
/**
* SmartyEngineFactory.php - Smarty instance of the srv report engine
*
* Port of hserv/report/smartyInit.php and of the plugin registration of
* ReportExecute: folders (own compile folder "compiled-srv", so templates
* compiled by the legacy engine are never reused), security policy, PHP
* functions allowed as modifiers, the Heurist modifiers (constant, translate,
* label, file_data, sorting), the {out} and {wrap} functions and the prefilter
* that replaces {123 \fre \eng} by the label of term 123 in the first language
* that has a translation.
*
* @project     Heurist academic knowledge management system
* @package     Reports\Smarty
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

use Heurist\Database\DatabaseInterface;
use RuntimeException;
use Smarty\Smarty;

/** Creates a configured Smarty instance for one report run. */
final class SmartyEngineFactory
{
    /** PHP functions templates may use as modifiers. */
    private const PHP_MODIFIERS = array('count', 'sizeof', 'in_array', 'is_array', 'intval', 'implode', 'explode',
        'array_count_values', 'array_keys', 'array_diff', 'array_merge', 'array_slice', 'array_unique',
        'array_values', 'floatval', 'is_numeric', 'json_encode', 'nl2br', 'preg_match_all', 'print_r', 'printf',
        'range', 'round', 'setlocale', 'strcmp', 'strpos', 'strstr', 'substr', 'strlen', 'time', 'utf8_encode');

    private ReportEnvironment $environment;
    private DatabaseInterface $database;

    /**
     * @param ReportEnvironment $environment Folders and settings.
     * @param DatabaseInterface $database Database (term prefilter).
     */
    public function __construct(ReportEnvironment $environment, DatabaseInterface $database)
    {
        $this->environment = $environment;
        $this->database = $database;
    }

    /**
     * Smarty with the Heurist plugins of a run.
     *
     * @param TemplateApi $api The $heurist object.
     * @param ValueFormatter $formatter {out} and {wrap}.
     * @param TemplateModifiers $modifiers |translate and the array modifiers.
     * @return Smarty
     */
    public function create(TemplateApi $api, ValueFormatter $formatter, TemplateModifiers $modifiers): Smarty
    {
        $templates = $this->environment->templateDir;
        $compiled = $this->environment->compileDir;
        if($templates === '' || !is_dir($templates)){
            throw new RuntimeException('Smarty templates folder does not exist');
        }
        foreach(array($compiled, $templates.'cache/', $templates.'configs/') as $folder){
            if(!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder)){
                throw new RuntimeException('Failed to create the folder '.basename($folder).' for smarty templates');
            }
        }

        $smarty = new Smarty();
        $smarty->setTemplateDir($templates);
        $smarty->setCompileDir($compiled);
        $smarty->setCacheDir($templates.'cache/');
        $smarty->setConfigDir($templates.'configs/');

        $policy = new ReportSecurityPolicy($smarty);
        // the debug console template of this engine
        $policy->secure_dir[] = __DIR__;
        $smarty->enableSecurity($policy);

        foreach(self::PHP_MODIFIERS as $function){
            $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, $function, $function);
        }
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'constant', array($api, 'constant'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'array_key_exists', array(TemplateModifiers::class, 'arrayKeyExists'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'array_column', array(TemplateModifiers::class, 'arrayColumn'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'arraysortby', array(TemplateModifiers::class, 'arraySortBy'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'array_multisort', array(TemplateModifiers::class, 'arrayMultisort'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'sort', array(TemplateModifiers::class, 'sort'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'asort', array(TemplateModifiers::class, 'asort'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'ksort', array(TemplateModifiers::class, 'ksort'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'translate', array($modifiers, 'translate'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'file_data', array($api, 'getFileField'));
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'label', array($api, 'getFieldLabel'));
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'out', array($formatter, 'out'));
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'wrap', array($formatter, 'wrap'));
        // old templates call {progress}; it does nothing
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'progress', static function(){ return ''; });
        $smarty->registerFilter('pre', array($this, 'translateTerms'));
        return $smarty;
    }

    /**
     * Prefilter: {123 \fre \eng} becomes the label of term 123 in the first of
     * these languages with a translation, else its label.
     *
     * @param string $source Template source.
     * @param mixed $template Smarty template.
     * @return string
     */
    public function translateTerms($source, $template = null): string
    {
        $source = (string)$source;
        if(!preg_match_all('/{\d*\s*(?:\\\\\w{3}\s*)+}/', $source, $matches)){
            return $source;
        }
        foreach(array_unique($matches[0]) as $match){
            $parts = explode('\\', trim($match, ' {}'));
            $termId = intval(array_shift($parts));
            if($termId < 1){ continue; }
            $text = '';
            foreach($parts as $lang){
                $text = (string)$this->database->fetchValue(
                    'SELECT trn_Translation FROM defTranslations WHERE trn_Code=? AND trn_Source="trm_Label" '
                    .'AND trn_LanguageCode=? LIMIT 1', array($termId, strtoupper(trim($lang))), ''
                );
                if($text !== ''){ break; }
            }
            if($text === ''){
                $text = (string)$this->database->fetchValue('SELECT trm_Label FROM defTerms WHERE trm_ID=? LIMIT 1', array($termId), '');
            }
            $source = str_replace($match, $text, $source);
        }
        return $source;
    }
}
