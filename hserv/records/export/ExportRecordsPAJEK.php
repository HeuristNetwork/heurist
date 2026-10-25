<?php
/**
* ExportRecordsPajek.php - Export record data in formatted for Pajek, just the .net file.
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

declare(strict_types=1);

namespace hserv\records\export;

use function count;
use function is_array;
use function intval;
use function array_key_exists;
use function in_array;

class ExportRecordsPAJEK extends ExportRecords{

    private array $vertices = [];
    private array $verticesMap = [];
    private array $arcs = [];
    private array $edges = [];

    private array $skipExpanding = [];

    protected function _outputHeader(){}
    protected function _outputRecord($record){
        return true;
    }
    protected function _outputFooter(){}

    public function output($data, $params){

        if(!$this->_outputPrepare($data, $params)){
            return false;
        }

        foreach($this->records as $record){

            $recID = is_array($record) ? (int)($record['rec_ID'] ?? 0) : (int)$record;

            if($recID <= 0 || array_key_exists($recID, $this->vertices)){
                continue;
            }elseif(!is_array($record)){
                $record = recordSearchByID($this->system, $recID, false, 'rec_RecTypeID,rec_Title');
            }

            $recTitle = addslashes($record['rec_Title']);

            $vertexIdx = count($this->verticesMap) + 1;
            $this->vertices[$recID] = "{$vertexIdx} \"#{$recID} {$recTitle}\"";
            $this->verticesMap[$recID] = $vertexIdx;
        }

        $this->getEdges(); // relationship markers
        $this->getArcs(); // record pointers

        return $this->_outputResult($params);
    }

    private function _outputResult(array $params){

        $isDownload = $params['file'] == 1;
        $timestamp = date('YmdHis');

        $scratchDir = $this->system->getSysDir(DIR_SCRATCH);
        $destination = tempnam($scratchDir, 'net');

        if(!$destination){
            $this->system->addError(HEURIST_ERROR, 'Unable to create temporary file for Pajek export');
            return false;
        }

        $nodeCount = count($this->vertices);
        file_put_contents($destination, "*Vertices {$nodeCount} % Records\n");
        foreach($this->vertices as $vertex){
            file_put_contents($destination, "{$vertex}\n", FILE_APPEND);
        }

        file_put_contents($destination, "*Arcs % Relationship Markers\n", FILE_APPEND);
        foreach($this->arcs as $arc){
            file_put_contents($destination, "{$arc}\n", FILE_APPEND);
        }

        file_put_contents($destination, "*Edges % Record Pointers\n", FILE_APPEND);
        foreach($this->edges as $edge){
            file_put_contents($destination, "{$edge}\n", FILE_APPEND);
        }

        header('Content-Type: text/plain; charset=utf-8');

        if($isDownload){

            $filename = "Pajek_{$this->system->dbname()}_{$timestamp}.net";

            header("Content-Disposition: attachment; filename={$filename}");
            header(CONTENT_LENGTH . filesize($destination));
        }

        fileReadByChunks($destination);
        fileDelete($destination);

        return true;
    }

    private function getEdges(){

        $mysqli = $this->system->getMysqli();

        foreach($this->vertices as $recID => $vertex){

            $query = "SELECT rl_SourceID, rl_TargetID, rl_RelationTypeID FROM recLinks WHERE rl_RelationID IS NOT NULL AND (rl_SourceID = {$recID} OR rl_TargetID = {$recID})";
            $relations = mysql__select_assoc($mysqli, $query, 0);

            foreach($relations as $relation){

                $sourceID = intval($relation['rl_SourceID']);
                $targetID = intval($relation['rl_TargetID']);
                $relationTermID = intval($relation['rl_RelationTypeID']);

                $relationKey = "{$sourceID}-{$relationTermID}-{$targetID}";

                if(array_key_exists($relationKey, $this->edges) || !array_key_exists($sourceID, $this->vertices) || !array_key_exists($targetID, $this->vertices)){
                    continue;
                }

                $sourceIdx = $this->verticesMap[$sourceID];
                $targetIdx = $this->verticesMap[$targetID];
                $this->edges[$relationKey] = "{$sourceIdx} {$targetIdx} 1";
            }
        }
    }

    private function getArcs(){

        $mysqli = $this->system->getMysqli();

        foreach($this->vertices as $recID => $vertex){

            $query = "SELECT rl_SourceID, rl_TargetID, rl_DetailTypeID FROM recLinks WHERE rl_DetailTypeID > 0 AND (rl_SourceID = {$recID} OR rl_TargetID = {$recID})";
            $pointers = mysql__select_assoc($mysqli, $query, 0);

            foreach($pointers as $pointer){

                $sourceID = intval($pointer['rl_SourceID']);
                $targetID = intval($pointer['rl_TargetID']);
                $fieldID = intval($pointer['rl_DetailTypeID']);

                $pointerKey = "{$sourceID}-{$fieldID}-{$targetID}";

                if(array_key_exists($pointerKey, $this->arcs) || !array_key_exists($sourceID, $this->vertices) || !array_key_exists($targetID, $this->vertices)){
                    continue;
                }

                $sourceIdx = $this->verticesMap[$sourceID];
                $targetIdx = $this->verticesMap[$targetID];
                $this->arcs[$pointerKey] = "{$sourceIdx} {$targetIdx} 1";
            }
        }
    }
}