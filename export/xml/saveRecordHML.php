<?php

/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* A simpel service tou store an hml version of a record using xincludes
*
* @author      Stephen White   
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export/xml
*/




header('Content-type: text/xml; charset=utf-8');
/*echo "<?xml version='1.0' encoding='UTF-8'?>\n";
*/
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

if (!is_logged_in()) { // check if the record being retrieved is a single non-protected record
    return;
}

// set parameter defaults
$recID = (@$_REQUEST['recID'] ? $_REQUEST['recID'] : null);
$q = (@$_REQUEST['q'] ? $_REQUEST['q'] : ($recID ? "ids:$recID" : null));
if (!$q) {
    returnXMLErrorMsgPage(" You must specify a record id (recID=#) or a heurist query (q=valid heurst search string)");
}

$outFullName = @$_REQUEST['outputFullname'] ? $_REQUEST['outputFullname'] : null;	// outName returns the hml direct.
if (!$outFullName){
    $outFullName = @$_REQUEST['outputFilename'] ? "".HEURIST_HML_DIR.$_REQUEST['outputFilename'] :
    ($recID ? "".HEURIST_HML_DIR.HEURIST_DBID."-".$recID.".hml" : null);
}
if (!$outFullName) {
    returnXMLErrorMsgPage(" Unable to determine output name either supply record id (recID=#) or outFilename=filenameOnlyHere ");
}


$depth = @$_REQUEST['depth'] ? $_REQUEST['depth'] : 1;
$hinclude = (@$_REQUEST['hinclude'] ? $_REQUEST['hinclude'] : ($recID?0:-1)); //default to 0 will output xincludes all non record id related records, -1 puts out all xinclude


mysql_connection_select(DATABASE);

if ($recID ){ // check access first
    $res = mysql_query("select * from Records where rec_ID = $recID");
    $row = mysql_fetch_assoc($res);
    $ACCESSABLE_OWNER_IDS = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
        'ugl_UserID='.get_user_id().' and grp.ugr_Type != "user" order by ugl_GroupID');
    if (is_logged_in()){
        array_push($ACCESSABLE_OWNER_IDS,get_user_id());
        if (!in_array(0,$ACCESSABLE_OWNER_IDS)){
            array_push($ACCESSABLE_OWNER_IDS,0);// 0 = belong to everyone
        }
    }

    $rec_owner_id = mysql__select_array("Records","rec_OwnerUGrpID","rec_ID=$recID");

    if ( $row['rec_NonOwnerVisibility'] != 'public' && (count($rec_owner_id) < 1 ||
    !in_array($rec_owner_id[0],$ACCESSABLE_OWNER_IDS) ||
    (is_logged_in() &&  $row['rec_NonOwnerVisibility'] == 'hidden'))){
        returnXMLErrorMsgPage(" no access to record id $recID ");
    }
}

saveRecordHML(HEURIST_BASE_URL."export/xml/flathml.php?ver=1&a=1&f=1&pubonly=1&".
    "depth=$depth&hinclude=$hinclude&w=all&q=$q&db=".HEURIST_DBNAME.
    (@$_REQUEST['outputFilename'] ? "&filename=".$_REQUEST['outputFilename'] :"").
    ($outFullName && @$_REQUEST['debug']? "&pathfilename=".$outFullName :""));


//  ---------Helper Functions
function saveRecordHML($filename){
    global $recID, $outFullName;
    $hml = loadRemoteURLContent($filename);
    if($hml)
    {

        $xml = new DOMDocument;
        $xml->loadXML($hml);
        // convert to xml
        if (!$xml){
            returnXMLErrorMsgPage("unable to generate valid hml for $filename");
        }else if($outFullName){
            $text = $xml->saveXML();
            $ret = file_put_contents( $outFullName,$text);
            if (!$ret){
                returnXMLErrorMsgPage("output of $outFullName failed to write");
            }else if ($ret < strlen($text)){
                returnXMLErrorMsgPage("output of $outFullName wrote $ret bytes of ".strlen($text));
            }else{ // success output the contents of the saved file file
                $text = file_get_contents($outFullName);
            }
            echo $text;
        }else{
            echo $xml->saveXML();//should never get here.
        }
    }
}

function returnXMLSuccessMsgPage($msg) {
    die("<html><body><success>$msg</success></body></html>");
}

function returnXMLErrorMsgPage($msg) {
    die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
