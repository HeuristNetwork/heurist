<?php
/**
* ReportSecurityPolicy.php - Smarty security policy of report templates
*
* The same policy as the legacy HeuristSecurityPolicy (hserv/report/smartyInit.php):
* no static classes, PHP tags not allowed, a list of allowed modifiers, super
* globals and constants allowed.
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

use Smarty\Security;

/** Security policy of report templates. */
class ReportSecurityPolicy extends Security
{
    public $static_classes = null;

    public $allowed_modifiers = array('isset', 'empty', 'escape', 'constant',
        'sizeof', 'in_array', 'is_array', 'intval', 'implode', 'explode', 'split',
        'array_key_exists', 'array_column', 'array_keys', 'array_multisort',
        'array_diff', 'array_count_values', 'array_unique',
        'asort', 'array_merge', 'array_slice', 'array_values', 'cat',
        'capitalize', 'count', 'count_characters', 'count_words', 'count_paragraphs',
        'date_format', 'floatval', 'indent', 'is_numeric', 'json_encode',
        'lower', 'nl2br', 'preg_match_all', 'print_r', 'printf', 'replace',
        'range', 'regex_replace', 'round', 'ksort', 'sort',
        'setlocale', 'spacify', 'strcmp', 'strip', 'strstr', 'substr', 'strpos', 'string_format',
        'strlen', 'strip_tags', 'arraysortby', 'time', 'truncate',
        'translate', 'label', 'file_data', 'out', 'wrap',
        'upper', 'utf8_encode', 'wordwrap');

    public $allow_super_globals = true;

    public $allowed_tags = false;

    public $allow_constants = true;
}
