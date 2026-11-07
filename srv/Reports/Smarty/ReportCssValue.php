<?php
/**
* ReportCssValue.php - HTMLPurifier check of a layout value in a report style
*
* Lengths, numbers and functions such as repeat(auto-fill,minmax(120px,1fr)) for flex and grid
* properties. Only these characters pass, and no function that loads or runs anything.
*
* @project     Heurist academic knowledge management system
* @package     Reports
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

/** A flex or grid value of an inline style. */
final class ReportCssValue extends \HTMLPurifier_AttrDef
{
    /**
     * @param string $string Value.
     * @param \HTMLPurifier_Config $config Configuration.
     * @param \HTMLPurifier_Context $context Context.
     * @return string|false The value, or false when it is refused.
     */
    public function validate($string, $config, $context)
    {
        $value = $this->parseCDATA($string);
        if($value === '' || preg_match('/^[a-z0-9 .,%()+\/-]+$/i', $value) !== 1){
            return false;
        }
        return preg_match('/url|expression|script|image|attr|var/i', $value) === 1 ? false : $value;
    }
}
