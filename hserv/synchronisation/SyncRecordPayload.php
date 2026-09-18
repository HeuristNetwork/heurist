<?php

namespace hserv\synchronisation;

use hserv\structure\ConceptCode;

/** Builds the portable record-content payload used after permanent IDs are allocated. */
final class SyncRecordPayload
{
    public static function export(\hserv\System $system, array $recordIDs): array
    {
        $mysqli = $system->getMysqli();
        $recordIDs = array_values(array_unique(array_filter(array_map('intval', $recordIDs))));
        if (!$recordIDs) return ['format' => 'heurist-hml-sync-1', 'records' => []];
        ConceptCode::setSystem($system);
        $records = [];
        foreach ($recordIDs as $recordID) {
            $recordResult = mysql__select_param_query($mysqli,
                'SELECT rec_ID,rec_URL,rec_Added,rec_Modified,rec_Title,rec_ScratchPad,rec_RecTypeID,'
                .'rec_AddedByImport,rec_NonOwnerVisibility FROM Records WHERE rec_ID=?', ['i', $recordID]);
            $record = $recordResult ? $recordResult->fetch_assoc() : null;
            if ($recordResult) $recordResult->close();
            if (!$record) continue;
            $record['recordTypeConceptID'] = ConceptCode::getRecTypeConceptID((int)$record['rec_RecTypeID']);
            $record['recordTypeName'] = (string)mysql__select_value($mysqli,
                'SELECT rty_Name FROM defRecTypes WHERE rty_ID=?', ['i', (int)$record['rec_RecTypeID']]);
            $record['details'] = [];
            $res = mysql__select_param_query($mysqli,
                'SELECT d.dtl_DetailTypeID,d.dtl_Value,d.dtl_UploadedFileID,ST_AsText(d.dtl_Geo) AS dtl_Geo,'
                .'d.dtl_Certainty,d.dtl_Annotation,d.dtl_HideFromPublic,t.dty_Type,t.dty_Name '
                .'FROM recDetails d JOIN defDetailTypes t ON t.dty_ID=d.dtl_DetailTypeID '
                .'WHERE d.dtl_RecID=? ORDER BY d.dtl_ID', ['i', $recordID]);
            while ($res && ($detail = $res->fetch_assoc())) {
                if (!empty($detail['dtl_UploadedFileID']) || $detail['dty_Type'] === 'file') {
                    throw new \RuntimeException('Record '.$recordID.' contains an uploaded file. File transfer is the next synchronisation stage.');
                }
                $detail['detailTypeConceptID'] = ConceptCode::getDetailTypeConceptID((int)$detail['dtl_DetailTypeID']);
                if (in_array($detail['dty_Type'], ['enum', 'relationtype'], true) && (int)$detail['dtl_Value'] > 0) {
                    $detail['termConceptID'] = ConceptCode::getTermConceptID((int)$detail['dtl_Value']);
                    $detail['termLabel'] = (string)mysql__select_value($mysqli,
                        'SELECT trm_Label FROM defTerms WHERE trm_ID=?', ['i', (int)$detail['dtl_Value']]);
                }
                $record['details'][] = $detail;
            }
            if ($res) $res->close();
            $records[] = $record;
        }
        return ['format' => 'heurist-hml-sync-1', 'records' => $records];
    }
}
