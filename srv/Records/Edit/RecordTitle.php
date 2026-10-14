<?php
/**
* RecordTitle.php - Title of a system record from its name field
*
* Plan 14: until title masks are ported, the title of a record written by the
* new API is its first DT_NAME value without a language prefix (else the first
* value), without HTML, or "Record #NNN" when there is no name.
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

use Heurist\Utilities\LanguageCodes;

/** Builds rec_Title from the name field. */
final class RecordTitle
{
    /** Maximum length of rec_Title (varchar 1023). */
    private const MAX_LENGTH = 1023;

    private LanguageCodes $languages;
    private int $nameFieldId;

    public function __construct(LanguageCodes $languages, int $nameFieldId)
    {
        $this->languages = $languages;
        $this->nameFieldId = $nameFieldId;
    }

    /**
     * Title from prepared detail rows (RecordValidator).
     *
     * @param array<int,array{dty:int,value:?string}> $rows
     */
    public function fromRows(array $rows, int $recordId): string
    {
        $names = array();
        foreach($rows as $row){
            if(intval($row['dty']) === $this->nameFieldId && is_string($row['value'] ?? null)){
                $names[] = $row['value'];
            }
        }
        return $this->fromNames($names, $recordId);
    }

    /**
     * Title from the values of the name field.
     *
     * @param array<int,string> $names
     */
    public function fromNames(array $names, int $recordId): string
    {
        $title = '';
        foreach($names as $name){
            list($language) = $this->languages->splitPrefix($name);
            if($language === null){
                $title = $name;
                break;
            }
            if($title === ''){
                $title = (string)$this->languages->splitPrefix($name)[1];
            }
        }
        $title = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8')));
        if($title === ''){
            return 'Record #'.$recordId;
        }
        return mb_substr($title, 0, self::MAX_LENGTH);
    }
}
