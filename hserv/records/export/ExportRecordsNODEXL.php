<?php
/**
* ExportRecordsNODEXL.php - Export record data in CSV formatted for NodeXL.
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace hserv\records\export;

//use Override;
use ZipArchive;

use function count;
use function is_array;
use function intval;
use function array_key_exists;
use function in_array;

class ExportRecordsNODEXL extends ExportRecords{

    /** @var array|string $vertices */
    private $vertices = [];
    /** @var array|string $edges */
    private $edges = [];

    private ?string $recordIconBase;
    private array $detailFieldNames = [];
    private array $relationTermLabels = [];
    private array $skipExpanding = [];

    //#[Override]
    public function setSession($system){
        parent::setSession($system);

        $this->recordIconBase = HEURIST_BASE_URL_PRO . "?db={$this->system->dbname()}&icon=";
    }

    protected function _outputHeader(){}
    protected function _outputRecord($record){
        return true;
    }
    protected function _outputFooter(){}

    public function output($data, $params){

        if(!$this->_outputPrepare($data, $params)){
            return false;
        }

        $this->vertices[] = [
            'Record ID (Vertex)', 'Record Type', 'Record Title', 'Record Icon'
        ];

        $this->edges[] = [
            'Record A', 'Record B', 'Relationship', 'Weight'
        ];

        for($idx = 0; $idx < count($this->records); $idx ++){

            $record = $this->records[$idx];

            $recID = is_array($record) ? (int)($record['rec_ID'] ?? 0) : (int)$record;

            if($recID <= 0 || array_key_exists($recID, $this->vertices)){
                continue;
            }elseif(!is_array($record)){
                $record = recordSearchByID($this->system, $recID, false, 'rec_RecTypeID,rec_Title');
            }

            $this->vertices[$recID] = [
                $recID,
                $record['rec_RecTypeID'],
                $record['rec_Title'],
                "{$this->recordIconBase}{$recID}"
            ];

            if(in_array($recID, $this->skipExpanding)){
                continue;
            }

            $this->addToRelationEdges($recID);
            $this->addFromRelationEdges($recID);

            $this->addPointerEdges($recID);
        }

        return $this->_outputResult($params);
    }

    private function _outputResult(array $params){

        $isDownload = $params['file'] == 1;
        $timestamp = date('YmdHis');

        $scratchDir = $this->system->getSysDir(DIR_SCRATCH);
        $destination = tempnam($scratchDir, $isDownload ? 'zip' : 'csv');

        if(!$destination){
            $this->system->addError(HEURIST_ERROR, 'Unable to create temporary file for NodeXL export');
            return false;
        }

        if($isDownload){

            $filename = "NodeXL_{$this->system->dbname()}_{$timestamp}.zip";
            $vertexFile = tempnam(DIR_SCRATCH, 'nodexl');
            $edgeFile = tempnam(DIR_SCRATCH, 'nodexl');

            $zip = new ZipArchive();
            if(!$zip->open($destination, ZipArchive::OVERWRITE)){
                $this->system->addError(HEURIST_SYSTEM_CONFIG, "Cannot create zip folder {$destination}");
                return false;
            }

            $fdVertices = fopen($vertexFile, 'w');
            foreach($this->vertices as $vertex){
                fputcsv($fdVertices, $vertex);
            }
            fclose($fdVertices);

            $fdEdges = fopen($edgeFile, 'w');
            foreach($this->edges as $edge){
                fputcsv($fdEdges, $edge);
            }
            fclose($fdEdges);

            if(!$zip->addFile($vertexFile, 'vertices.csv')){

                fileDelete($vertexFile);
                fileDelete($edgeFile);

                $this->system->addError(HEURIST_ERROR, 'Cannot add vertices file to NodeXL output');
                return false;
            }
            if(!$zip->addFile($edgeFile, 'edges.csv')){

                fileDelete($vertexFile);
                fileDelete($edgeFile);

                $this->system->addError(HEURIST_ERROR, 'Cannot add edges file to NodeXL output');
                return false;
            }

            $zip->close();

            fileDelete($vertexFile);
            fileDelete($edgeFile);

            header('Content-Type: application/zip');
            header("Content-Disposition: attachment; filename={$filename}");
            header(CONTENT_LENGTH . filesize($destination));

        }else{

            $fd = fopen($destination, 'w');

            fputcsv($fd, ['Vertices']);

            foreach($this->vertices as $vertex){
                fputcsv($fd, $vertex);
            }

            fputcsv($fd, ['Edges']);

            foreach($this->edges as $edge){
                fputcsv($fd, $edge);
            }

            fclose($fd);

            header('Content-Type: text/plain; charset=utf-8');
        }

        fileReadByChunks($destination);
        fileDelete($destination);

        return true;
    }

    private function addToRelationEdges(int $sourceID){

        $mysqli = $this->system->getMysqli();

        $query = "SELECT rl_TargetID, rl_RelationTypeID FROM recLinks WHERE rl_SourceID = {$sourceID} AND rl_RelationID IS NOT NULL";
        $targetRelations = mysql__select_assoc($mysqli, $query, 0);

        foreach($targetRelations as $relation){

            $targetID = intval($relation['rl_TargetID']);
            $relationTermID = intval($relation['rl_RelationTypeID']);

            $relationKey = "{$sourceID}-{$relationTermID}-{$targetID}";

            if(array_key_exists($relationKey, $this->edges)){
                continue;
            }

            $relationTerm = @$this->relationTermLabels[$relationTermID] ?? null;
            if(!$relationTerm){
                $relationTerm = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $relationTermID]);
                $this->relationTermLabels[$relationTermID] = $relationTerm;
            }

            $this->edges[$relationKey] = [
                $sourceID,
                $targetID,
                $relationTerm,
                1
            ];

            if(!array_key_exists($targetID, $this->vertices)){
                $this->records[] = $targetID;
                $this->skipExpanding[] = $targetID;
            }
        }
    }

    private function addFromRelationEdges(int $targetID){

        $mysqli = $this->system->getMysqli();

        $query = "SELECT rl_SourceID, rl_RelationTypeID FROM recLinks WHERE rl_TargetID = {$targetID} AND rl_RelationID IS NOT NULL";
        $sourceRelations = mysql__select_assoc($mysqli, $query, 0);

        foreach($sourceRelations as $relation){

            $sourceID = intval($relation['rl_SourceID']);
            $relationTermID = intval($relation['rl_RelationTypeID']);

            $relationKey = "{$sourceID}-{$relationTermID}-{$targetID}";

            if(array_key_exists($relationKey, $this->edges)){
                continue;
            }

            $relationTerm = @$this->relationTermLabels[$relationTermID] ?? null;
            if(!$relationTerm){
                $relationTerm = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $relationTermID]);
                $this->relationTermLabels[$relationTermID] = $relationTerm;
            }

            $this->edges[$relationKey] = [
                $sourceID,
                $targetID,
                $relationTerm,
                1
            ];

            if(!array_key_exists($sourceID, $this->vertices)){
                $this->records[] = $sourceID;
                $this->skipExpanding[] = $sourceID;
            }
        }
    }

    private function addPointerEdges(int $recID){

        $mysqli = $this->system->getMysqli();

        $query = "SELECT rl_SourceID, rl_TargetID, rl_DetailTypeID FROM recLinks WHERE rl_DetailTypeID > 0 AND (rl_SourceID = {$recID} OR rl_TargetID = {$recID})";
        $recPointers = mysql__select_assoc($mysqli, $query, 0);

        foreach($recPointers as $pointer){

            $sourceID = $pointer['rl_SourceID'];
            $targetID = $pointer['rl_TargetID'];
            $fieldID = $pointer['rl_DetailTypeID'];

            $pointerKey = "{$sourceID}-{$fieldID}-{$targetID}";

            if(array_key_exists($pointerKey, $this->edges)){
                continue;
            }

            $fieldName = @$this->detailFieldNames[$fieldID] ?? null;
            if(!$fieldName){
                $fieldName = mysql__select_value($mysqli, "SELECT dty_Name FROM defDetailTypes WHERE dty_ID = ?", ['i', $fieldID]);
                $this->relationTermLabels[$fieldID] = $fieldName;
            }

            $this->edges[$pointerKey] = [
                $sourceID,
                $targetID,
                $fieldName,
                1
            ];

            if(!array_key_exists($sourceID, $this->vertices)){
                $this->records[] = $sourceID;
                $this->skipExpanding[] = $sourceID;
            }
            if(!array_key_exists($targetID, $this->vertices)){
                $this->records[] = $targetID;
                $this->skipExpanding[] = $targetID;
            }
        }
    }
}