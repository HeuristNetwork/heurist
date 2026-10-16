<?php
/**
* WritableRecordTypes.php - Record types the new write API may change
*
* Plan 14: until title masks, calculated fields and the other legacy save steps
* are ported, the new API writes only system record types (reports, map documents,
* layers and sources, query sources, websites). Their titles come from DT_NAME.
* The client keeps the same list (plan 14, Phase 2) to choose the new editor.
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

use Heurist\Runtime\SystemCode;

/** Hardcoded list of writable (system) record types. */
final class WritableRecordTypes
{
    /** Code names of the writable record types. */
    public const CODES = array(
        'RT_CUSTOM_REPORT', 'RT_REPORT_SCHEDULE',
        'RT_MAP_DOCUMENT', 'RT_MAP_LAYER',
        'RT_KML_SOURCE', 'RT_FILE_SOURCE', 'RT_SHP_SOURCE', 'RT_IMAGE_SOURCE', 'RT_TILED_IMAGE_SOURCE',
        'RT_QUERY_SOURCE',
        'RT_CMS_HOME', 'RT_CMS_PAGE'
    );

    /** @var array<int,string> rty id => code name */
    private array $ids = array();

    public function __construct(SystemCode $codes)
    {
        foreach(self::CODES as $name){
            $id = $codes->id($name);
            if($id > 0){ $this->ids[$id] = $name; }
        }
    }

    /** True when the new API may write records of this type. */
    public function contains(int $rectypeId): bool
    {
        return isset($this->ids[$rectypeId]);
    }

    /** @return array<int,int> Local ids of the writable record types. */
    public function ids(): array
    {
        return array_keys($this->ids);
    }
}
