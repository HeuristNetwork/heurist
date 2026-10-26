<?php
/**
* WebsiteDefinitionsInterface.php - Installs the definitions of the website format v4
*
* srv code does not write definitions; the implementation is in hserv
* (hserv/web/WebsiteDefinitions.php, as reports: hserv/report/ReportRecordWriter.php).
*
* @project     Heurist academic knowledge management system
* @package     Website
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website;

/** Imports missing fields and adds them to the structures of the CMS record types. */
interface WebsiteDefinitionsInterface
{
    /**
     * Make the fields exist (import from the Heurist reference database when missing)
     * and add them to the structures of the record types where they are missing.
     *
     * @param array<int,string> $fieldCodes Concept codes of fields ("2-1202").
     * @param array<int,string> $rectypeCodes Concept codes of record types ("99-52").
     * @return array<int,string> Report lines.
     */
    public function install(array $fieldCodes, array $rectypeCodes): array;
}
