<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * Class ExportRecordsGEPHI
 *
 * Extends `ExportRecords` to provide functionality for exporting Heurist records
 * in GEXF (Gephi XML) format, suitable for network visualization in Gephi.
 * It generates nodes for records and edges for relationships between them.
 *
 * @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

namespace hserv\records\export;
use hserv\records\export\ExportRecords;

/**
*
*  setSession - switch current datbase
*  output - main method
*
*/
class ExportRecordsGEPHI extends ExportRecords {

    /** @var string|false Path to a temporary file used to store edge data before appending to the main GEXF output. */
    private $gephi_links_dest = null;
    /** @var resource|false File descriptor for the temporary links file. */
    private $fd_links = null;
    /** @var int Counter for the number of edges generated. */
    private $links_cnt = 0;

    /** @var array Stores detail type IDs of fields to be included as attributes for edges representing relationship records. */
    private $relmarker_fields = [];

    /** @var array Keeps track of edges already printed to avoid duplicates (e.g., [sourceID, targetID]). */
    private $edges_printed = [];

    /**
     * Prepares the list of fields to be retrieved for GEPHI export.
     *
     * Overrides parent method. Sets detail fields based on `$params['columns']`
     * and restricts header fields to 'rec_ID', 'rec_RecTypeID', 'rec_Title'.
     *
     * @param array $params Parameters that may contain 'columns' to specify detail fields.
     * @return void
     */
protected function _outputPrepareFields($params){

    $this->retrieve_detail_fields = !empty($params['columns']) ? prepareIds($params['columns']) : false;
    $this->retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';

}

    /**
     * Outputs the header for the GEXF (Gephi XML) file.
     *
     * This includes XML declaration, GEXF root element with schema definitions,
     * metadata (creator, description, last modified date), and attribute declarations
     * for both nodes (records) and edges (relationships).
     * Node attributes include standard Heurist fields and any specified detail fields.
     * Edge attributes include standard relationship details and any additional fields
     * configured for relationship records.
     * Initializes a temporary file for storing edge definitions.
     *
     * @return false|void False if temporary file creation fails, otherwise void.
     */
protected function _outputHeader(){

    $this->gephi_links_dest = tempnam($this->system->getSysDir(DIR_SCRATCH), "links");
    $this->fd_links = fopen($this->gephi_links_dest, 'w');//less than 1MB in memory otherwise as temp file
    if (false === $this->fd_links) {
        $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
        return false;
    }

    $t2 = new \DateTime();
    $dt = $t2->format('Y-m-d');

    //although anyURI is defined it is not recognized by gephi v0.92
    $heurist_url = HEURIST_BASE_URL.'?db='.$this->system->dbname();

    $rec_fields = '';
    if(!empty($this->retrieve_detail_fields)){

        $id_idx = 5;

        foreach ($this->retrieve_detail_fields as $dty_ID) {

            $dty_Name = mysql__select_value($this->mysqli, "SELECT dty_Name FROM defDetailTypes WHERE dty_ID = {$dty_ID}");
            $rec_fields .= "\n\t\t\t\t\t<attribute id=\"{$id_idx}\" title=\"{$dty_Name}\" type=\"string\"/>";

            $id_idx ++;
        }
    }

    // Relationship record values
    $rel_RecTypeID = $this->system->getConstant('RT_RELATION');
    $rel_Source = $this->system->getConstant('DT_PRIMARY_RESOURCE');
    $rel_Target = $this->system->getConstant('DT_TARGET_RESOURCE');
    $rel_Type = $this->system->getConstant('DT_RELATION_TYPE');
    $rel_Start = $this->system->getConstant('DT_START_DATE');
    $rel_End = $this->system->getConstant('DT_END_DATE');

    $rel_fields = '';
    if($rel_RecTypeID && $rel_Source && $rel_Target && $rel_Type && $rel_Start && $rel_End){

        $query = "SELECT rst_DisplayName, rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID = ? AND rst_DetailTypeID NOT IN (?,?,?,?,?)";
        $query_params = ['iiiiii', $rel_RecTypeID, $rel_Source, $rel_Target, $rel_Type, $rel_Start, $rel_End];
        $res = mysql__select_param_query($this->mysqli, $query, $query_params);

        $id_idx = 6;

        if($res){

            while($row = $res->fetch_row()){

                $rel_fields .= "\n\t\t\t\t\t<attribute id=\"{$id_idx}\" title=\"{$row[0]}\" type=\"string\"/>";
                $this->relmarker_fields[] = $row[1];

                $id_idx ++;
            }
        }
    }

    $gephi_header = <<<XML
        <gexf xmlns="http://www.gexf.net/1.2draft" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance"
            xsi:schemaLocation="http://www.gexf.net/1.2draft http://www.gexf.net/1.2draft/gexf.xsd" version="1.2">
            <meta lastmodifieddate="{$dt}">
                <creator>HeuristNetwork.org</creator>
                <description>Visualisation export $heurist_url </description>
            </meta>
            <graph mode="static" defaultedgetype="directed">
                <attributes class="node">
                    <attribute id="0" title="name" type="string"/>
                    <attribute id="1" title="image" type="string"/>
                    <attribute id="2" title="rectype" type="string"/>
                    <attribute id="3" title="count" type="float"/>
                    <attribute id="4" title="url" type="string"/>{$rec_fields}
                </attributes>
                <attributes class="edge">
                    <attribute id="0" title="relation-id" type="float"/>
                    <attribute id="1" title="relation-name" type="string"/>
                    <attribute id="2" title="relation-image" type="string"/>
                    <attribute id="3" title="relation-count" type="float"/>
                    <attribute id="4" title="relation-start" type="string"/>
                    <attribute id="5" title="relation-end" type="string"/>{$rel_fields}
                </attributes>
                <nodes>
XML;

    $gephi_header = XML_HEADER.$gephi_header;

    fwrite($this->fd, $gephi_header);

    $this->links_cnt = 0;
}

//
//
//
    /**
     * Outputs a single Heurist record as a GEXF <node> element and its relationships as <edge> elements.
     *
     * Node attributes include name, image URL (icon), record type, count (default 0), record URL,
     * and values for any specified detail fields.
     * Edges are generated by calling `recordSearchRelated` and then `_composeGephiLinks`.
     * Edge data is written to a temporary file.
     *
     * @param array $record The Heurist record data (header and details).
     * @return bool True on success, false if relationship search fails.
     */
protected function _outputRecord($record){

    $recID = intval($record['rec_ID']);
    $rty_ID = intval($record['rec_RecTypeID']);
    $name   = htmlspecialchars($record['rec_Title']);
    $image  = htmlspecialchars(HEURIST_BASE_URL.'?db='.$this->system->dbname().'&icon='.$rty_ID);
    $recURL = htmlspecialchars(HEURIST_BASE_URL.'recID='.$recID.'&fmt=html&db='.$this->system->dbname());

    $rec_values = '';
    if(!is_array($this->retrieve_detail_fields)){
        $this->retrieve_detail_fields = [];
    }

    $att_id = 4;
    foreach($this->retrieve_detail_fields as $dty_ID){

        $att_id ++;
        $values = array_key_exists($dty_ID, $record['details']) && is_array($record['details'][$dty_ID]) ?
                    $record['details'][$dty_ID] : null;

        if(empty($values)){
            continue;
        }

        $this->_processFieldData($dty_ID, $values);

        if(empty($values)){
            continue;
        }

        $rec_values .= "\n\t\t<attvalue for=\"{$att_id}\" value=\"{$values}\"/>";
    }

            $gephi_node = <<<XML
                    <node id="{$recID}" label="{$name}">
                        <attvalues>
                            <attvalue for="0" value="{$name}"/>
                            <attvalue for="1" value="{$image}"/>
                            <attvalue for="2" value="{$rty_ID}"/>
                            <attvalue for="3" value="0"/>
                            <attvalue for="4" value="{$recURL}"/>{$rec_values}
                        </attvalues>
                    </node>
                    XML;

    fwrite($this->fd, $gephi_node);

    $links = recordSearchRelated($this->system, $recID, 0, false);
    if($links['status']!=HEURIST_OK){
        return false;
    }

    if(!empty(@$links['data']['direct'])){
        fwrite($this->fd_links, $this->_composeGephiLinks($this->records, $links['data']['direct'], $this->links_cnt, 'direct'));
    }
    if(!empty(@$links['data']['reverse'])){
        fwrite($this->fd_links, $this->_composeGephiLinks($this->records, $links['data']['reverse'], $this->links_cnt, 'reverse'));
    }

    return true;

}

//
//
//
    /**
     * Outputs the footer for the GEXF file.
     *
     * Closes the `<nodes>` element, appends all edge data from the temporary links file
     * inside an `<edges>` element, and then closes the `<graph>` and `<gexf>` elements.
     * Finally, closes and deletes the temporary links file.
     *
     * @return void
     */
protected function _outputFooter(){

        fwrite($this->fd, '</nodes>');

        //include links
        fwrite($this->fd, '<edges>'.file_get_contents($this->gephi_links_dest).'</edges>');

        fwrite($this->fd, '</graph></gexf>');

        fclose($this->fd_links);

}


/**
* returns xml string with gephi links
*
* @param array &$records Reference to the array of primary record IDs being exported (used to filter links).
* @param array &$links Array of relationship link objects from `recordSearchRelated`.
* @param int &$links_cnt Reference to a counter for unique edge IDs.
* @param string $direction 'direct' or 'reverse', indicating the relationship direction relative to the primary record.
* @return string An XML string containing GEXF <edge> elements for the provided links.
*/
private function _composeGephiLinks(&$records, &$links, &$links_cnt, $direction){

    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);
    }
    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms($this->system);
        self::$defTerms = new \DbsTerms($this->system, self::$defTerms);
    }

    $idx_dname = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];

    $edges = '';

    if($links){

        foreach ($links as $link){
            if($direction=='direct'){
                $source =  $link->recID;
                $target =  $link->targetID;
            }else{
                $source = $link->sourceID;
                $target = $link->recID;
            }

            if(array_search([$source, $target], $this->edges_printed) !== false){
                continue;
            }

            $dtID = $link->dtID;
            $trmID = $link->trmID;
            $relationName = "Floating relationship";
            $relationID = 0;

            $startDate = empty(@$link->dtl_StartDate) ? '' : $link->dtl_StartDate;
            $endDate = empty(@$link->dtl_EndDate) ? '' : $link->dtl_EndDate;

            if(!in_array($source, $records) || !in_array($target, $records)){
                continue;
            }

            if($dtID > 0) {
                $relationName = self::$defDetailtypes['typedefs'][$dtID]['commonFields'][$idx_dname];
                $relationID = $dtID;
            }elseif($trmID > 0) {
                $relationName = self::$defTerms->getTermLabel($trmID, true);
                $relationID = $trmID;
            }

            $relationName  = htmlspecialchars($relationName);
            $links_cnt++;

            $rel_values = '';
            $att_id = 5;
            if(!empty($this->relmarker_fields) && !empty($link->relationID) && intval($link->relationID) > 0){

                $record = recordSearchByID($this->system, intval($link->relationID), $this->relmarker_fields, 'rec_ID');

                foreach($this->relmarker_fields as $dty_ID){

                    $att_id ++;

                    if(!array_key_exists($dty_ID, $record['details']) || empty($record['details'][$dty_ID])){
                        continue;
                    }

                    $values = $record['details'][$dty_ID];
                    $this->_processFieldData($dty_ID, $values);

                    if(empty($values)){
                        continue;
                    }

                    $rel_values .= "\n\t\t<attvalue for=\"{$att_id}\" value=\"{$values}\"/>";
                }
            }

            $this->edges_printed[] = [$source, $target];

            $edges .= <<<XML
                    <edge id="{$links_cnt}" source="{$source}" target="{$target}" weight="1" name="{$relationName}">
                        <attvalues>
                            <attvalue for="0" value="{$relationID}"/>
                            <attvalue for="1" value="{$relationName}"/>
                            <attvalue for="3" value="1"/>
                            <attvalue for="4" value="{$startDate}"/>
                            <attvalue for="5" value="{$endDate}"/>{$rel_values}
                        </attvalues>
                    </edge>
                    XML;
        }//for
    }
    return $edges;
}

private function _processFieldData($dty_ID, &$values){

    $dty_Type = mysql__select_value($this->mysqli, "SELECT dty_Type FROM defDetailTypes WHERE dty_ID = ?", ['i', $dty_ID]);

    foreach($values as $dtl_ID => $value){

        switch ($dty_Type) {
            case 'file': // get external URL / Heurist URL

                $f_id = $value['file']['ulf_ObfuscatedFileID'];
                $external_url = $value['file']['ulf_ExternalFileReference'];

                $value = empty($external_url) ? HEURIST_BASE_URL_PRO."?db=".$this->system->dbname()."&file={$f_id}" : $external_url;
                break;

            case 'enum': // get term label

                if(is_numeric($value)){
                    $value = intval($value);
                    $value = mysql__select_value($this->mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $value]);
                }

                break;

            case 'resource': // get record title

                if(is_numeric($value)){
                    $value = intval($value);
                    $value = mysql__select_value($this->mysqli, "SELECT rec_Title FROM Records WHERE rec_ID = ?", ['i', $value]);
                }elseif(is_array($value)){
                    $value = $value["title"];
                }

                break;

            default:
                break;
        }

        $values[$dtl_ID] = htmlspecialchars($value);
    }

    $values = is_array($values) ? implode('|', $values) : $values;
}

} //end class
?>