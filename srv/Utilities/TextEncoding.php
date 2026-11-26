<?php
/**
* TextEncoding.php - Explicit conversions for legacy text formats
*
* @project     Heurist academic knowledge management system
* @package     Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/
declare(strict_types=1);

namespace Heurist\Utilities;


/** Explicit conversions for legacy text formats. */
final class TextEncoding
{
    /**
     * Compatibility with utf8_encode(): ISO-8859-1 bytes to UTF-8, without detection.
     * In particular, bytes 0x80-0x9F are Latin-1 controls, not Windows-1252 punctuation.
     */
    public static function latin1ToUtf8(?string $value): string
    {
        return mb_convert_encoding($value ?? '', 'UTF-8', 'ISO-8859-1');
    }
}
