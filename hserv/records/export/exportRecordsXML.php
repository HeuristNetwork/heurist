<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecordsXML.php - class to export records as XML
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
class ExportRecordsXML extends ExportRecords {
    
//
//
//  
protected function _outputHeader(){
    
    fwrite($this->fd, '<?xml version="1.0" encoding="UTF-8"?><heurist><records>');
}

//
//
//
protected function _outputRecord($record){

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><record/>');
    self::_array_to_xml($record, $xml);
    fwrite($this->fd, substr($xml->asXML(),38));//remove header

    return true;
    
}

//
//
//
protected function _outputFooter(){
    
    fwrite($this->fd, '</records>');

    $database_info = $this->_getDatabaseInfo();
    
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><database/>');
    self::_array_to_xml($database_info, $xml);
    fwrite($this->fd, substr($xml->asXML(),38));
    fwrite($this->fd, '</heurist>');
}

//------------------------
//
// json to xml
//
private static function _array_to_xml( $data, &$xml_data ) {
    foreach( $data as $key => $value ) {
        if( is_numeric($key) ){
            $key = 'item'.$key; //dealing with <0/>..<n/> issues
        }
        if( is_array($value) ) {
            $subnode = $xml_data->addChild($key);
            self::_array_to_xml($value, $subnode);
        } else {
            $xml_data->addChild("$key",htmlspecialchars("$value"));
        }
     }
}

} //end class
?>