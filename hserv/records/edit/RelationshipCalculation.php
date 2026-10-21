<?php
/**
 * Helpers for calculated pointers derived from outgoing relationships.
 * @project Heurist academic knowledge management system
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
namespace hserv\records\edit;

use hserv\utilities\Temporal;
use InvalidArgumentException;
use RuntimeException;

class RelationshipCalculation
{
    // An explicit opt-in: other formulas retain their database-wide dependencies.
    const REFRESH_MARKER = '{* heurist:refresh-on-relationship-change *}';

    public static function isTargeted($formula)
    {
        return strpos($formula, self::REFRESH_MARKER) !== false;
    }

    /**
     * Dated relationships outrank all undated relationships. Ties use modification
     * time, then relationship ID. Empty input produces an empty calculated field.
     * Invalid dates fail the formula rather than silently choosing a different link.
     */
    public static function selectTarget(array $candidates)
    {
        $best = null;
        $bestRank = null;
        foreach ($candidates as $candidate) {
            $date = trim((string)($candidate['date'] ?? ''));
            $minimum = 0;
            $time = '';
            if ($date !== '') {
                $temporal = new Temporal($date);
                $bounds = $temporal->getMinMax();
                if (!$bounds) {
                    throw new InvalidArgumentException('Invalid relationship date on record '.intval($candidate['id']));
                }
                $minimum = (float)$bounds[0];
                // The date index has day precision. Preserve time-of-day ordering.
                $limits = $temporal->calcMinMax();
                $time = (string)$limits[0];
                if (preg_match('/^\d{4}-\d{2}-\d{2}(?:$|[T ])/D', $time)) {
                    $utc = new \DateTimeZone('UTC');
                    $time = (new \DateTimeImmutable($time, $utc))->setTimezone($utc)->format('Y-m-d H:i:s.u');
                    $minimum = (float)(new Temporal(substr($time, 0, 10)))->getMinMax()[0];
                }
            }
            $rank = array($date !== '' ? 1 : 0, $minimum, $time,
                (string)$candidate['modified'], intval($candidate['id']));
            if ($bestRank === null || $rank > $bestRank) {
                $bestRank = $rank;
                $best = intval($candidate['target']);
            }
        }
        return $best === null ? '' : (string)$best;
    }

    /** IDs are explicit so formulas can distinguish storage from other relationships. */
    public static function latestTarget($system, $recordId, $targetType, $relationTypes, $dateField = 10)
    {
        $recordId = intval($recordId);
        $targetType = intval($targetType);
        $dateField = intval($dateField);
        $types = prepareIds($relationTypes);
        if ($recordId < 1 || $targetType < 1 || $dateField < 1 || empty($types)) {
            throw new InvalidArgumentException('Latest related record requires record, target type, relationship types and date field IDs');
        }
        $mysqli = $system->getMysqli();
        $rows = $mysqli->query('SELECT rel.rec_ID, rel.rec_Modified, target.rec_ID AS target, dates.dtl_Value '
            .'FROM recLinks links JOIN Records rel ON rel.rec_ID=links.rl_RelationID '
            .'JOIN Records target ON target.rec_ID=links.rl_TargetID '
            .'LEFT JOIN recDetails dates ON dates.dtl_RecID=rel.rec_ID AND dates.dtl_DetailTypeID='.$dateField.' '
            .'WHERE links.rl_SourceID='.$recordId.' AND target.rec_RecTypeID='.$targetType
            .' AND links.rl_RelationTypeID IN ('.implode(',', $types).') '
            .'AND NOT rel.rec_FlagTemporary AND NOT target.rec_FlagTemporary');
        if (!$rows) {
            throw new RuntimeException('Cannot read relationships for calculated field');
        }
        $candidates = array();
        while ($row = $rows->fetch_assoc()) {
            $candidates[] = array('id' => $row['rec_ID'], 'modified' => $row['rec_Modified'],
                'target' => $row['target'], 'date' => $row['dtl_Value']);
        }
        $rows->free();
        return self::selectTarget($candidates);
    }

    /** Capture before mutation too, so moving/deleting a relationship refreshes its old source. */
    public static function sources($system, $recordIds, $includeEndpoints = true)
    {
        $ids = prepareIds($recordIds);
        if (empty($ids)) { return array(); }
        $ids = implode(',', $ids);
        $condition = 'rl_RelationID IN ('.$ids.')';
        if ($includeEndpoints) {
            $condition .= ' OR rl_TargetID IN ('.$ids.') OR rl_SourceID IN ('.$ids.')';
        }
        return mysql__select_list2($system->getMysqli(),
            'SELECT DISTINCT rl_SourceID FROM recLinks WHERE rl_RelationID IS NOT NULL AND ('.$condition.')') ?: array();
    }

    /** Only formulas explicitly opting into local relationship dependencies are run. */
    public static function refresh($system, $sourceIds)
    {
        $ids = prepareIds($sourceIds);
        if (empty($ids)) { return array('errors' => array()); }
        $mysqli = $system->getMysqli();
        $marker = $mysqli->real_escape_string(self::REFRESH_MARKER);
        $ids = mysql__select_list2($mysqli, 'SELECT DISTINCT rec_ID FROM Records '
            .'JOIN defRecStructure ON rst_RecTypeID=rec_RecTypeID '
            .'JOIN defCalcFunctions ON cfn_ID=rst_CalcFunctionID '
            .'WHERE rec_ID IN ('.implode(',', $ids).') AND NOT rec_FlagTemporary '
            ."AND LOCATE('$marker', cfn_FunctionSpecification)>0");
        if (empty($ids)) { return array('errors' => array()); }
        $result = recordUpdateCalcFields($system, $ids, null, null, 0, true);
        if (is_array($result)) {
            foreach (array_unique(array_merge($result['updated_ids'] ?? array(), $result['cleared_ids'] ?? array())) as $id) {
                recordUpdateTitle($system, $id, null, null);
                \hserv\records\export\ExportRecordsHTML::clearHtmlOutputCacheForRecord($system->getSysDir('html-output'), $id);
            }
        }
        return $result;
    }
}
