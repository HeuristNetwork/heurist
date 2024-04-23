<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecordsGEPHI.php - class to export records as GEPHI XML
* 
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

require_once 'exportRecords.php';

/**
* 
*  setSession - switch current datbase
*  output - main method
* 
*/
class ExportRecordsGEPHI extends ExportRecords {
    
    //to store gephi links
    private $gephi_links_dest = null;
    private $fd_links = null;
    private $links_cnt = 0;
    


//
//
//
protected function _outputPrepareFields($params){
    
    $this->retrieve_detail_fields = !empty($params['columns']) ? prepareIds($params['columns']) : false;
    $this->retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';
    
}
    
//
//
//  
protected function _outputHeader(){
    
    $this->gephi_links_dest = tempnam(HEURIST_SCRATCHSPACE_DIR, "links");    
    $this->fd_links = fopen($this->gephi_links_dest, 'w');  //less than 1MB in memory otherwise as temp file 
    if (false === $this->fd_links) {
        $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
        return false;
    }   

    $t2 = new DateTime();
    $dt = $t2->format('Y-m-d');

    //although anyURI is defined it is not recognized by gephi v0.92
    $heurist_url = HEURIST_BASE_URL.'?db='.$this->system->dbname();

    $rec_fields = '<attribute id="0" title="name" type="string"/>
                <attribute id="1" title="image" type="string"/>
                <attribute id="2" title="rectype" type="string"/>
                <attribute id="3" title="count" type="float"/>
                <attribute id="4" title="url" type="string"/>';

    if(!empty($this->retrieve_detail_fields)){

        $id_idx = 5;

        foreach ($this->retrieve_detail_fields as $dty_ID) {

            $dty_Name = mysql__select_value(self::$mysqli, "SELECT dty_Name FROM defDetailTypes WHERE dty_ID = {$dty_ID}");
            $rec_fields .= "\n\t\t\t\t<attribute id=\"{$id_idx}\" title=\"{$dty_Name}\" type=\"string\"/>";

            $id_idx ++;
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
                    {$rec_fields}
                </attributes>
                <attributes class="edge">
                    <attribute id="0" title="relation-id" type="float"/>
                    <attribute id="1" title="relation-name" type="string"/>
                    <attribute id="2" title="relation-image" type="string"/>
                    <attribute id="3" title="relation-count" type="float"/>
                </attributes>
                <nodes>
XML;

    $gephi_header = '<?xml version="1.0" encoding="UTF-8"?>'.$gephi_header;

    fwrite($this->fd, $gephi_header);      
    
    $this->links_cnt = 0;
}

//
//
//
protected function _outputRecord($record){

    $recID = intval($record['rec_ID']);
    $rty_ID = intval($record['rec_RecTypeID']);
    $name   = htmlspecialchars($record['rec_Title']);
    $image  = htmlspecialchars(HEURIST_RTY_ICON.$rty_ID);
    $recURL = htmlspecialchars(HEURIST_BASE_URL.'recID='.$recID.'&fmt=html&db='.$this->system->dbname());

    $rec_values = "<attvalue for=\"0\" value=\"{$name}\"/>
            <attvalue for=\"1\" value=\"{$image}\"/>
            <attvalue for=\"2\" value=\"{$rty_ID}\"/>
            <attvalue for=\"3\" value=\"0\"/>
            <attvalue for=\"4\" value=\"{$recURL}\"/>";

    if(is_array($this->retrieve_detail_fields)){

        $att_id = 4;
        foreach($this->retrieve_detail_fields as $dty_ID){

            $att_id ++;
            $values = array_key_exists($dty_ID, $record['details']) && is_array($record['details'][$dty_ID]) ? 
                        $record['details'][$dty_ID] : null;

            if(empty($values)) { continue; }

            $dty_Type = mysql__select_value(self::$mysqli, "SELECT dty_Type FROM defDetailTypes WHERE dty_ID = {$dty_ID}");

            foreach($values as $dtl_ID => $value){

                if($dty_Type == 'file'){ // get external URL / Heurist URL

                    $f_id = $value['file']['ulf_ObfuscatedFileID'];
                    $external_url = $value['file']['ulf_ExternalFileReference'];

                    $value = empty($external_url) ? HEURIST_BASE_URL_PRO."?db=".HEURIST_DBNAME."&file={$f_id}" : $external_url;

                }else if($dty_Type == 'enum'){ // get term label
                    $value = mysql__select_value(self::$mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = $value");
                }

                if(strpos($value, '"') !== false){ // add slashes, to avoid double quote issues
                    $values[$dtl_ID] = addslashes($value);
                }

                $values[$dtl_ID] = $value;
            }

            $values = is_array($values) ? implode('|', $values) : $values;

            if(empty($values)) { continue; }

            $rec_values .= "\n\t\t\t<attvalue for=\"{$att_id}\" value=\"{$values}\"/>";
        }
    }
            $gephi_node = <<<XML
<node id="{$recID}" label="{$name}">                               
    <attvalues>
        {$rec_values}
    </attvalues>
</node>
XML;
  
    fwrite($this->fd, $gephi_node);
    
    $links = recordSearchRelated($this->system, $recID, 0, false);
    if($links['status']==HEURIST_OK){
        if(@$links['data']['direct'])
            fwrite($this->fd_links, $this->_composeGephiLinks($this->records, $links['data']['direct'], $this->links_cnt, 'direct'));
        if(@$links['data']['reverse'])
            fwrite($this->fd_links, $this->_composeGephiLinks($this->records, $links['data']['reverse'], $this->links_cnt, 'reverse'));
    }else{
        return false;
    }

    return true;
    
}

//
//
//
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
* @param mixed $this->records - array of record ids to limit output only for links in this array
* @param mixed $links - array of relations produced by recordSearchRelated
*/
private function _composeGephiLinks(&$records, &$links, &$links_cnt, $direction){

    if(self::$defDetailtypes==null) self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);
    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms($this->system);
        self::$defTerms = new DbsTerms($this->system, self::$defTerms);
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

            $dtID = $link->dtID;
            $trmID = $link->trmID;
            $relationName = "Floating relationship";
            $relationID = 0;

            if(in_array($source, $records) && in_array($target, $records)){

                if($dtID > 0) {
                    $relationName = self::$defDetailtypes['typedefs'][$dtID]['commonFields'][$idx_dname];
                    $relationID = $dtID;
                }else if($trmID > 0) {
                    $relationName = self::$defTerms->getTermLabel($trmID, true);
                    $relationID = $trmID;
                }

                $relationName  = htmlspecialchars($relationName);                               
                $links_cnt++; 

                $edges = $edges.<<<XML
<edge id="{$links_cnt}" source="{$source}" target="{$target}" weight="1">                               
    <attvalues>
    <attvalue for="0" value="{$relationID}"/>
    <attvalue for="1" value="{$relationName}"/>
    <attvalue for="3" value="1"/>
    </attvalues>
</edge>
XML;


            }   
        }//for
    }
    return $edges;         
}

} //end class
?>