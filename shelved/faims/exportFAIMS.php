<?php
/* 
THIS IS Heurist v.3. 
It is not used anywhere. This code either should be removed or re-implemented wiht new libraries
*/

/**
*
* exportFAIMS.php: write the FAIMS tablet app configuration files for selected record types 
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.5
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');
require_once(dirname(__FILE__)."/../../records/files/fileUtils.php");

$rt_toexport = @$_REQUEST['frt'];
$rt_toexport_toplevel = @$_REQUEST['crt'];
$maprec_toexport = @$_REQUEST['mt'];
$projname = @$_REQUEST['projname'];
$step = @$_REQUEST['step']; // 1 - define record type  ,   2 - download result

$arc_filename = HEURIST_FILESTORE_DIR."faims/new/".$projname.".zip";

if($step=='2' && $projname && file_exists($arc_filename)){
    downloadFile('application/zip', $arc_filename);
    exit();
}
?>

<html>
    <head>
        <title>Create FAIMS Module files (db schema, ui schema, ui logic and a16n)</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <link rel=stylesheet href="../../common/css/global.css" media="all">
        <link rel=stylesheet href="../../common/css/admin.css" media="all">

        <script type="text/javascript" src="../../common/js/utilsUI.js"></script>
        <script type="text/javascript" src="../../external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="selectRectype.js"></script>

        <style>
            .err_message {
                color:red;
                border-bottom: 1px solid black;
                text-transform:uppercase;
                padding-top:5px;
                padding-bottom:5px;
                margin-bottom: 10px;
            }
            .lbl_form{
                width:180px;
                display:inline-block;
                text-align:right;
                padding:10px;
            }
            div label{
                padding-left: 2px;
            }
        </style>
    </head>

    <body style="padding:22px;" class="popup">
        <script src="../../common/php/loadCommonInfo.php"></script>
        <div class="banner"><h2>Build FAIMS Module</h2></div>
        <div id="page-inner" style="top:15px; padding-left: 30px;padding-right:10px">
         
            <!-- TODO: This takes up too much space, but would be nice to include it:
            <div style='background: url(../../common/images/logo_faims.png) center top no-repeat;height:120px;'>&nbsp;</div> -->


            <div style="width: 900px;">

                <p style="line-height:1em !important;">
                    The FAIMS project ( Field Acquired Information Management System, 
                    <a href="http://fedarch.org" target="_blank">http://fedarch.org</a> ) has built a highly configurable system for data
                    collection using consumer grade Android tablets, funded by the Australian Research Council (ARC) and 
                    National eResearch Collaboration Tools and Resources (NeCTAR). The FAIMS system is suitable for collection of information in libraries, 
                    archives and museums, for social surveys, and for a range of natural science field projects, as well as for archaeological survey 
                    or excavation (the original target audience). The system is geographically aware, connects to internal or external cameras and GPS, 
                    and seamlessly synchronises data collection across multiple tablets for team use.
                </p>
                <p style="line-height:1em !important;">
                    This function allows the configuration of the FAIMS app (a 'module' in FAIMS parlance) directly from the structure 
                    of the Heurist database, allowing Heurist to sct as a module designer for FAIMS. The Heurist database defines the types of entities 
                    and attributes (fields) to be displayed on the tablet app, and the connections between entities. Heurist can also import data from 
                    a FAIMS project, building the Heurist data definitions on the fly, and can export data to the tDAR and Open Context repository systems 
                    (see under Administration).
                </p>

            </div>

            <?php

            $invalid = (!$projname || preg_match('/[^A-Za-z0-9_\$]/', $projname)); //'[\W]'

            //STEP 1 - define record types to be exported
            mysql_connection_select(DATABASE);

            if($step=='1'){

                if (!(isset($rt_toexport) && is_array($rt_toexport) && count($rt_toexport)>0)){
                    print "<div class='err_message'>Select record types for export</div>";
                }
                if($invalid){
                    if($projname){
                        print "<div class='err_message'>Only letters, numbers and underscores (_) are allowed in project name</div>";
                    }else{
                        print "<div class='err_message'>Project name is mandatory</div>";
                    }
                }
            }else{

                $res = mysql_query("select sys_dbRegisteredID, sys_dbName, sys_dbDescription, sys_OwnerGroupID from sysIdentification where 1");
                if (!$res) { // Problem reading current registration ID
                    print "<div class='err_message'>Unable to read database identification record, this database might be incorrectly set up. \n" .
                    "Please ".CONTACT_HEURIST_TEAM." for advice.</div>";
                }
                $row = mysql_fetch_row($res); // Get system information for current database
                $dbID = $row[0];
                if ( !isset($dbID) || ($dbID <1)) { // not registered
                    print "<div class='err_message'>This database has not been registered with the Heurist network.</div> We encourage registration before proceeding to ensure that the record types, fields and terms in your database have globally unique identifiers.
                    To register click Database > Register in the menu on the left<br />&nbsp;<br />";
                }
            }

            //find all records for specific record types: GeoTiff, KML, Shapefile
            $map_records = array();
            if (! @$wg_ids  &&  function_exists('get_user_id')) {

                $mrt = array();
                if(defined('RT_GEOTIFF_SOURCE')){
                    array_push($mrt, RT_GEOTIFF_SOURCE);
                }
                if(defined('RT_SHP_SOURCE')){
                    array_push($mrt, RT_SHP_SOURCE);
                }
                if(defined('RT_KML_SOURCE')){
                    array_push($mrt, RT_KML_SOURCE);
                }

                if(count($mrt)>0){
                    $wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
                        'ugl_UserID='.get_user_id().' and grp.ugr_Type != "User" order by ugl_GroupID');
                    if($wg_ids && count($wg_ids)>0){
                        $swgs = ' or rec_OwnerUGrpID in ('.implode(",",$wg_ids).')';
                    }else{
                        $swgs = '';
                    }


                    $res = mysql_query('SELECT rec_ID, rec_Title, rec_RecTypeID FROM Records where rec_RecTypeID in ('.implode(',',$mrt).') and '
                        .'( rec_OwnerUGrpID='. get_user_id().$swgs.' or not rec_NonOwnerVisibility="hidden") and not rec_FlagTemporary');

                    while (($row = mysql_fetch_assoc($res))){
                        array_push($map_records, $row);
                    }

                }
            }
            ?>
            <div style="width: 900px;">
                This function writes a set of FAIMS project definition files (db schema, ui schema, ui logic, A16N) to a zip file,
                based on the record types selected from the Heurist database, along with any required image or map data files.
                These files can be loaded into the FAIMS server to create a new module.<p/>
            </div>

            <?php

            print "<form id='startform' name='startform' action='exportFAIMS.php' method='get'>";
            print "<input id='rt_selected' name='rt' type='hidden'>";
            print "<input name='step' value='1' type='hidden'>";
            print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
            print "<div><div class='lbl_form' style='margin_left:30px;'>FAIMS module name</div><input name='projname' value='".($projname?$projname:HEURIST_DBNAME)."' size='25'></div>";
            // List of record types for export
            print "<div id='selectedRectypes' style='width:100%;color:black;'></div>";

            $rtStructs = getAllRectypeStructures(false);
            $int_rt_dt_type      = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_Type"];

            $rt_geoenabled = array();
            $rt_invalid_masks = array();
            if($rt_toexport && count($rt_toexport)>0){
                //validate title masks
                $rtIDs = mysql__select_assoc("defRecTypes","rty_ID","rty_Name"," rty_ID in (".implode(",",$rt_toexport).") order by rty_ID");
                foreach ($rtIDs as $rtID => $rtName) {
                    $mask= mysql__select_array("defRecTypes","rty_TitleMask","rty_ID=$rtID");
                    $mask=$mask[0];
                    $res = titlemask_make($mask, $rtID, 2, null, _ERR_REP_MSG); //get human readable
                    if( is_array($res) ){ //invalid mask
                        array_push($rt_invalid_masks, $rtName);
                    }

                    $details = $rtStructs['typedefs'][$rtID]['dtFields'];
                    if(!$details){
                        print "<p style='color:red'>No details defined for record type #".$rtName.". Edit record type structure.</p>";
                        $invalid = true;
                    }else{
                        //check if rectype is geoenabled
                        foreach ($details as $dtid=>$detail) {
                            $dt_type = $detail[$int_rt_dt_type];
                            if($dt_type=="geo"){
                                array_push($rt_geoenabled, $rtID);
                                break;
                            }
                        }
                    }

                }
                if(count($rt_invalid_masks)>0){
                    $invalid = true;
                    $step = '';
                }
            }

            /*
            print "<div id='buttondiv2' style='display:".(($step=='1')?"none":"block")."'><div class='lbl_form'>Record types to include in export:</div>";
            print "<input type='button' value='Select Record Types' id='btnSelRecType1' onClick='onSelectRectype(\"".HEURIST_DBNAME."\")'/></div>";
            */

            if(count($rt_invalid_masks)>0){
                print "<p style='color:red; width:600px'>You have invalid title masks in the following record types: <b>"
                .implode(",",$rt_invalid_masks)."</b>
                FAIMS requires setting of at least one field (attrribute) as an identifier, which is based  on the fields in the title mask.
                Please correct the title mask(s) before proceeding (edit the record type in Database Designer > Essentials > Manage RecordTypes/Fields)</p>";
            }

            // Don't show Start Export button until record types have been selected
            print "<div id='buttondiv' style='padding-bottom:30px;display:".(($rt_toexport && $step!='1')?"block":"none")."'><div class='lbl_form'></div><input type='button' value='Start Export' onclick='validateForm()' /></div>";

            print "</form>";
            /*art 2014-02-24 if($rt_toexport){
            print "<script>showSelectedRecTypes('".$rt_toexport."')</script>";
            }*/

            if( $rt_toexport && count($rt_toexport)>0 && !$invalid ){

                $folder = HEURIST_FILESTORE_DIR."faims/new/".$projname;

                //create export folder
                if(!file_exists($folder)){
                    if (!mkdir($folder, 0777, true)) {
                        die('Failed to create folder for '.$folder.' - check that file permissions are correctly set');
                    }
                }else{ //clear folder
                    delFolderTree($folder, false);
                }

                //load definitions (USE CACHE)
                $dtStructs = getAllDetailTypeStructures(false);
                $dtTerms = getTerms(false);
                $ind_parentid =  $dtTerms['fieldNamesToIndex']['trm_ParentTermID'];
                $ind_label =  $dtTerms['fieldNamesToIndex']['trm_Label'];
                $ind_descr =  $dtTerms['fieldNamesToIndex']['trm_Description'];

                // $unsupported was original specification, but this will stuff up if new types are added
                $supported = array ('freetext', 'blocktext', 'integer', 'date', 'year', 'boolean', 'enum', 'float', 'file', 'resource');
                //$unsupported = array('relmarker','relationtype','separator','calculated','fieldsetmarker','urlinclude','geo');
                // Note: Geos are supported, but FAIMS uses a different methodology for recording them, that is they are an implicit part of every record
                $content_a16n = null;

                //file_put_contents($folder."/project.settings", $projname." and basic information 3");
                file_put_contents($folder."/data_schema.xml", generateSchema($projname, $rt_toexport, $rt_geoenabled));
                file_put_contents($folder."/ui_schema.xml", generate_UI_Schema($projname, $rt_toexport, $rt_geoenabled));
                file_put_contents($folder."/arch16N.properties", arrToProps());
                file_put_contents($folder."/ui_logic.bsh", generate_Logic($projname, $rt_toexport, $rt_toexport_toplevel, $rt_geoenabled));
                file_put_contents($folder."/style.css", getStyling());

                //copy("ui_logic.bsh", $folder."/ui_logic.bsh"); //this is a Java beanshell file which provides the operational  logic for the tablet interface

                //CREATE ZIPFILE
                if(file_exists($folder.".zip")){
                    unlink($folder.".zip");
                }

                $cmdline = "zip -r -j ".$folder.".zip ".$folder;

                $res1 = 0;
                $output1 = exec($cmdline, $output, $res1);
                if ($res1 != 0 ) {
                    echo ("<p class='error'>Exec error code $res1: Unable to create zip file $folder.zip>&nbsp;<br>");
                    echo $output;
                    echo "</p> Directory may be non-writeable or zip function is not installed on server (error code 127) - please consult system adminstrator";
                }else{
                    print "<br><br>".
                    "<a href='exportFAIMS.php/$projname.zip?db=".HEURIST_DBNAME."&step=2&projname=".$projname."' target='_blank' style='color:blue; font-size:1.2em'>Click here to download FAIMS project definitions zip archive</a>";
                }
                ?>
            </div>
            <script>
                $( document ).ready(function() {
                    $("#startform").hide();
                });
            </script>
        </body>
    </html>
    <?php
}else{
    ?>
    </div>
    <script>
        var map_records = <?php echo json_format($map_records);?>;
        $( document ).ready(function() {
            showSelectedRecTypes();
        });
    </script>
    </body>
    </html>
    <?php
}
exit();

/*
Since PHP 5.3
$phar = new PharData('project.tar');
// add all files in the project
$phar->buildFromDirectory(dirname(__FILE__) . '/project');

Or use pear install Archive_Tar
http://stackoverflow.com/questions/333130/build-tar-file-from-directory-in-php-without-exec-passthru
*/

function getResStrA16n($str, $val=null){
    $str = trim($str);
    add16n($str, $val);
    return "{".str_replace(' ','_',$str)."}";
}

function getResStrNoA16n($str, $val=null){
    $str = trim($str);
    add16n($str, $val);
    return str_replace(' ','_',$str);
}

// add an entry into the a16n (localisation) file
function add16n($line, $val=null){
    global $content_a16n;
    if(!isset($content_a16n)){
        $content_a16n = array();
    }
    if(isset($line) && !@$content_a16n[$line]){   //!in_array($line, $content_a16n)){
        $content_a16n[$line] = trim($val?$val:$line);
    }
}
//generate a16n (localisation) file, providing translation of all terms and labels
function arrToProps(){
    global $content_a16n;

    $res = file_get_contents('templates/arch16N.properties');
    $res = $res."\n";

    foreach ($content_a16n as $prop=>$value){
        $res = $res.str_replace(' ','_',$prop).'='.$value."\n";
    }
    return $res;
}

// insert a comment into the XML (converted to comment on output by prepareXML)
function addComment($ele, $text, $ns=null){
    if($ns){
        $ele->addChild('CommentMarker', $text, $ns);
    }else{
        $ele->addChild('CommentMarker', $text);
    }
}

// convert comment elements added with addComment into proper XML comments with <!-- and -->
function prepareXml($root){
    $out = $root->asXML();
    $out = str_replace('<CommentMarker>','<!--',$out);
    $out = str_replace('</CommentMarker>','-->',$out);
    // Special handling for ui schema, where comemnts at top are prepended with h:
    $out = str_replace('<h:CommentMarker>','<!--',$out);
    $out = str_replace('</h:CommentMarker>','-->',$out);
    //return utf8_encode(formatXML2($out));
    return formatXML2($out);
}

function writeUTF8File($filename, $content) {
    $f=fopen($filename,"w");
    # Now UTF-8 - Add byte order mark
    //fwrite($f, pack("CCC",0xef,0xbb,0xbf));
    fwrite($f, $content);
    fclose($f);
}

//
// Generate the database schema file (db_schema.xml)
//
function generateSchema($projname, $rt_toexport, $rt_geoenabled){

    global $rtStructs, $dtStructs, $dtTerms, $ind_descr, $supported;

    $hasMapTab = @$_REQUEST['mainmt']=="1";
    $hasControlTracklog = @$_REQUEST['ct4']=="1";

    $root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?> <dataSchema />');
    $root->addAttribute('name', DATABASE);
    $root->addAttribute('preparer', "Heurist user: ".prepareText(get_user_name()));

    addComment($root, ' ****** ');
    addComment($root, 'FAIMS Database Schema generated by Heurist Vsn '.HEURIST_VERSION.', '. date('l jS \of F Y h:i:s A'));
    addComment($root, ' ****** ');

    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $ind_rt_titlemask = $rtStructs['typedefs']['commonNamesToIndex']['rty_TitleMask']; //names
    $ind_rt_titlemask_canonical = $rtStructs['typedefs']['commonNamesToIndex']['rty_CanonicalTitleMask'];// codes

    $int_rt_termtree     = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    $int_rt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];

    $int_rt_dt_type      = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_Type"];
    $int_rt_disp_name    = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DisplayName"];

    $int_rt_dt_descrip   = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayHelpText'];
    $int_dt_ccode        = $dtStructs['typedefs']['fieldNamesToIndex']['dty_ConceptID'];
    $idx_rst_pointers = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_PtrFilteredIDs"];

    $rectyps = is_array($rt_toexport)?$rt_toexport:explode(",", $rt_toexport);

    ///create RelationshipElement if there are 1 or more record types to export
    if(count($rectyps)>0){ //ARTEM remove noise

        $reltypes = array();
        //find relmarker fields
        foreach ($rectyps as $rt) {
            $rst_fields = $rtStructs['typedefs'][$rt]['dtFields'];
            if(!$rst_fields){
                continue;
            }

            foreach ($rst_fields as $dtid=>$detail) {
                $rst_dt_type = $detail[$int_rt_dt_type];
                if($rst_dt_type=="relmarker"){
                    //find all
                    $termTree = $detail[$int_rt_termtree];
                    $disableTerms = $detail[$int_rt_termtree_dis];
                    $temp = preg_replace("/[\[\]\"]/","",$disableTerms);
                    $disableTerms = explode(",",$temp);

                    //convert to tree
                    $list = getTermsPlainList($rst_dt_type, $termTree, $disableTerms);
                    $reltypes = array_merge($reltypes, $list);

                }else if($rst_dt_type=="resource"){

                    $dt_pointers = $detail[$idx_rst_pointers];
                    $dt_pointers = explode(",", $dt_pointers);
                    if(count($dt_pointers)>0){   //ignore unconstrained

                        $dtdisplayname = $detail[$int_rt_disp_name]; // the display name for this field
                        $dtdisplaynamex = getProperName($dtdisplayname, $rt, $dtid);

                        if(!@$reltypes["has".$dtdisplaynamex]){
                            array_push($reltypes, "has".$dtdisplaynamex);
                        }
                    }
                }

            }//for detals

        }//for rectypes

        if(count($reltypes)>0){
            $reltypes = array_unique($reltypes);

            $termLookup = $dtTerms['termsByDomainLookup']['relation']; // only select relationship terms
            $ind_ccode = $dtTerms['fieldNamesToIndex']['trm_ConceptID'];
            $ind_tcode = $dtTerms['fieldNamesToIndex']['trm_Code'];
            $ind_tdescr = $dtTerms['fieldNamesToIndex']['trm_Description'];

            addComment($root, 'In Heurist, relationships are stored in a single relationship record type and '+
                'different types of relationship are distinguished by the relationship type field (attribute) whose '+
                'values can be organised hierarchichally. In FAIMS, each type of relationship is a separate record type, '+
                'organised into three classes - bidirectional, container and hierarchy');
            foreach ($reltypes as $termid) {
                if(is_numeric($termid)){ //reltype

                    $term = @$termLookup[$termid];
                    if(!$term){
                        continue;
                    }
                    $termCode = $term[$ind_tcode];
                    if(!$termCode){ $termCode = ""; }

                    $termCcode = $term[$ind_ccode]; //concept code
                    if(!$termCcode || strpos($termCcode,'-')===false) {
                        $termCcode = intval(HEURIST_DBID).'-'.$termid;
                    }
                    $termName = getResStrNoA16n(getFullTermName($term, 'relation'));
                    $termDesc = prepareText( $term[$ind_descr]?$term[$ind_descr]:"");

                }else{    //resource-pointer

                    $termCode = "";
                    $termCcode = "";
                    $termName = $termid;
                    $termDesc = prepareText("Heurist pointer field: Directional relationship between the entity being described and a target entity");

                }

                $rel = $root->addChild('RelationshipElement');
                $rel->addAttribute('name', $termName);  // required
                $rel->addAttribute('type', 'bidirectional');
                $rel->addAttribute('HeuristID', $termCcode);   // ignored by FAIMS oct 2013
                $rel->addAttribute('StandardCode', $termCode); // ignored by FAIMS oct 2013
                // getFullTermName gets a dash-separate hieerachy
                //$rel->addAttribute('pictureURL', '');
                //$rel->addAttribute('semanticMapURL', '');
                $rel->description = $termDesc;
            }


        }

        /*
        //
        // old version - all reltypes
        //
        $termLookup = $dtTerms['termsByDomainLookup']['relation']; // only select relationship terms
        $ind_ccode = $dtTerms['fieldNamesToIndex']['trm_ConceptID'];
        $ind_tcode = $dtTerms['fieldNamesToIndex']['trm_Code'];

        addComment($root, 'In Heurist, relationships are stored in a single relationship record type and
        different types of relationship are distinguished by the relationship type field (attribute) whose values
        can be organised hierarchichally. In FAIMS, each type of relationship is a separate record type, organised
        into three classes - bidirectional, container and hierarchy');

        foreach ($termLookup as $termid=>$term) {

        if($term[$ind_parentid]>0){

        $termCcode = @$termLookup[$termid][$ind_ccode]; //concept code
        $termCode = @$termLookup[$termid][$ind_tcode];
        if(!$termCode){
        $termCode = "";
        }
        if(strpos($termCcode,'-')===false) { // check to see if registered database, insert 0 if not
        $termCcode = '0-'.$termid;       // TODO: Artem, this shoudl be using original concept code NOT the internal code
        }

        $rel = $root->addChild('RelationshipElement');
        $rel->addAttribute('name',getResStrNoA16n(getFullTermName($term, 'relation')));  // required
        $rel->addAttribute('type', 'bidirectional');
        $rel->addAttribute('HeuristID', $termCcode);   // ignored by FAIMS oct 2013
        $rel->addAttribute('StandardCode', $termCode); // ignored by FAIMS oct 2013
        // getFullTermName gets a dash-separate hieerachy
        //$rel->addAttribute('pictureURL', '');
        //$rel->addAttribute('semanticMapURL', '');

        // Artem, why are you adding a dash at the start of the descriptions if they exist, and leaving them null if they don't??
        // I think you meant to do it the other way round (I've changed it) but this is horrible!
        $rel->description = prepareText( $term[$ind_descr]?$term[$ind_descr]:"-" );
        //$rel->description = prepareText($termid."-".$term[$ind_label].($term[$ind_descr]?"-".$term[$ind_descr]:""));

        }
        }
        */
        addComment($root, 'Archaeological elements correspond with Heurist record types');
        addComment($root, 'Properties correspond with Heurist base field types for this record type');
    }

    // Output specifications for each ArchEnt (Record type in Heurist)

    foreach ($rectyps as $rt) {

        $rt_descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];

        //ARTEM print $rtStructs['names'][$rt]." desc=".$rt_descr;

        addComment($root, mb_strtoupper($rtStructs['names'][$rt]));
        $arch_element = $root->addChild('ArchaeologicalElement');

        $arch_element->addAttribute('name', prepareText($rtStructs['names'][$rt]) );
        $arch_element->description = prepareText($rt_descr);
        $titlemask = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_titlemask];
        $titlemask_canonical = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_titlemask_canonical];

        //backward capability - make sure that titlemask is canonical ie. uses internal codes not field names
        $titlemask = titlemask_make($titlemask, $rt, 1, null, _ERR_REP_SILENT);

        $has_identifier = false;

        $details =  $rtStructs['typedefs'][$rt]['dtFields'];
        if(!$details){
            continue;
        }


        //important! to form proper record title we need to order properties in scheme in order they apper in title mask
        $dt_order = array();

        $titlemask_rest = $titlemask;
        $offset = 0;
        $data = preg_match_all('/\[([^\"]*?)\]/', $titlemask, $matches);
        //foreach $matches as 
        if(count($matches)>0){
            for ($idx=0; $idx<count($matches[1]); $idx++ ){
                $entry = $matches[1][$idx];
                //find detail in structure by concept code
                foreach ($details as $dtid=>$detail) {
                    $dt_type = $detail[$int_rt_dt_type];
                    if(!in_array($dt_type, $supported)){
                        continue;
                    }
                    $det = $dtStructs['typedefs'][$dtid]['commonFields'];
                    $ccode = $det[$int_dt_ccode];

                    if($ccode!='' && ($ccode==$entry || strpos($entry, $ccode.'.')!==false)){
                        $detail['isIdentifier'] = true;

                        //get piece to next entry
                        if($idx+1<count($matches[0])){
                            $pos = strpos($titlemask, $matches[0][$idx+1], $offset);
                            $formatStr = substr($titlemask, $offset, $pos-$offset );
                            $offset = $pos;
                        }else{
                            $formatStr = substr($titlemask, $offset );
                        }
                        if($dt_type=='enum' || $dt_type=='relationtype'){
                            $faimsmagic = '{{if $1 then "$1" }}';
                        }else{
                            $faimsmagic = '{{if $2 then "$2" }}';
                        }
                        //replace entry to faims magic
                        $formatStr = str_replace($matches[0][$idx], $faimsmagic, $formatStr);

                        $detail['formatString'] = $formatStr;
                        $rtStructs['typedefs'][$rt]['dtFields'][$dtid]['isIdentifier'] = true; //to use later in ui_scheme
                        $dt_order[$dtid] = $detail;
                        break;
                    }
                }
            }
        }

        // Each ArchEnt in FAIMS has to have at least one attribute (field) marked as an identifier
        if(count($dt_order)==0){
            addComment($root, 'ERROR!!!!! This ArchaeologicalEntity does not have identifiers!!! Verify TitleMask in Heurist recordtype!');
            continue;                 
        }

        //add rest details
        foreach ($details as $dtid=>$detail) {
            if(!@$dt_order[$dtid]){
                $dt_order[$dtid] = $detail;
            }
        }

        // Loop through the fields (details)

        foreach ($dt_order as $dtid=>$detail) {

            $dt_type = $detail[$int_rt_dt_type];
            if(!in_array($dt_type, $supported)){
                continue;
            }

            if($dt_type=="resource"){
                $dt_pointers = $detail[$idx_rst_pointers];
                $dt_pointers = explode(",", $dt_pointers);
                if(count($dt_pointers)<1){ //ignore unconstrained
                    continue;
                }
            }

            $property = $arch_element->addChild('property');

            $property->addAttribute('name', prepareText($detail[$int_rt_disp_name]));
            $property->addAttribute('type', $dt_type);
            if($detail[$int_rt_dt_descrip]){
                $property->addChild('description', prepareText($detail[$int_rt_dt_descrip]));
            }
            if($dt_type=="file"){
                $property->addAttribute('file', 'true');

                $dtdisplayname = $detail[$int_rt_disp_name];
                $ftype = detectFileType($dtdisplayname);
                $filetype = $ftype[1];

                if($filetype=='camera'){ //sync for camera only
                    $property->addAttribute('thumbnail', 'true');
                    $property->addAttribute('sync', 'true');
                }
            }

            /*$det = $dtStructs['typedefs'][$dtid]['commonFields'];
            $ccode = $det[$int_dt_ccode];
            $is_in_titlemask = (!( strpos($titlemask, ".".$ccode."]")===false && strpos($titlemask, "[".$ccode."]")===false )) ;
            if($is_in_titlemask){*/
            if(@$detail['isIdentifier']){    
                $property->addAttribute('isIdentifier', 'true');
                if(@$detail['formatString']){
                    addCdata('formatString', $detail['formatString'], $property);
                }
            }

            if($dt_type=='enum' || $dt_type=='relationtype'){
                $terms = $detail[$int_rt_termtree];
                //convert to tree
                $cnt = getTermsTree($property, $dt_type, $terms);
                $is_hierarchy = isTermsHierarchical($dt_type, $terms);

                $rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'] = $cnt;
                $rtStructs['typedefs'][$rt]['dtFields'][$dtid]['is_hierarchy'] = $is_hierarchy;
            }
        } //for

        if( in_array($rt, $rt_geoenabled) ){
            $property = $arch_element->addChild('property');
            $property->addAttribute('name', 'Latitude');
            $property->addAttribute('type', 'float');
            $property = $arch_element->addChild('property');
            $property->addAttribute('name', 'Longitude');
            $property->addAttribute('type', 'float');
            $property = $arch_element->addChild('property');
            $property->addAttribute('name', 'Northing');
            $property->addAttribute('type', 'float');
            $property = $arch_element->addChild('property');
            $property->addAttribute('name', 'Easting');
            $property->addAttribute('type', 'float');
        }

    }//foreach recordtype

    //specila entity for tracklog
    if(false && $hasControlTracklog){
        addComment($root, mb_strtoupper("GPS TRACK"));
        $gps_track = new SimpleXMLElement('
            <ArchaeologicalElement name="gps_track">
            <description>
            A unit is a portion of trench described by this record..
            </description>
            <property type="integer" name="gps_user" minCardinality="1" maxCardinality="1" isIdentifier="true">
            </property>
            <property type="time" name="gps_timestamp" minCardinality="1" maxCardinality="1" isIdentifier="true">
            </property>
            <property type="text" name="gps_longitude" minCardinality="1" maxCardinality="1" isIdentifier="true">
            </property>
            <property type="text" name="gps_latitude" minCardinality="1" maxCardinality="1" isIdentifier="true">
            </property>
            <property type="float" name="gps_heading" minCardinality="1" maxCardinality="1" isIdentifier="true">
            </property>
            <property type="float" name="gps_accuracy" minCardinality="1" maxCardinality="1" isIdentifier="true">
            </property>
            <property type="text" name="gps_type" minCardinality="1" maxCardinality="1" isIdentifier="true">
            </property>
        </ArchaeologicalElement>');
        xml_adopt($root, $gps_track);
    }



    $out = prepareXml($root);

    return $out;

} // generation of DB schema


/**
* Generate the FAIMS user interface schema file
*
* generate with simplexml
*
* @param mixed $projname
* @param mixed $rt_toexport
*/
function generate_UI_Schema($projname, $rt_toexport, $rt_geoenabled){

    global $rtStructs, $dtTerms, $supported;

    $hasMapTab = @$_REQUEST['mainmt']=="1";
    $certainityForVocab = true; //(@$_REQUEST['ct5']=="1");

    $root = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>
        <h:html xmlns="http://www.w3.org/2002/xforms" xmlns:h="http://www.w3.org/1999/xhtml"
        xmlns:ev="http://www.w3.org/2001/xml-events" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:jr="http://openrosa.org/javarosa" />');

    addComment($root, ' ****** ');
    addComment($root, 'FAIMS UI Schema generated by Heurist Vsn '.HEURIST_VERSION.', '. date('l jS \of F Y h:i:s A'));
    addComment($root, 'Database: '.DATABASE.'   Heurist user:'.get_user_name());
    addComment($root, ' ****** ');

    $head = $root->addChild("head",'','http://www.w3.org/1999/xhtml');
    $body = $root->addChild("body");

    $head->addChild("title", str_replace("_"," ",DATABASE));
    addComment($head, 'model lists user interface tabgroups, tabs and content of tabs', 'http://www.w3.org/2002/xforms');
    $model = $head->addChild("model",'','http://www.w3.org/2002/xforms');
    $instance = $model->addChild("instance");
    addComment($model, 'list of numeric and blocktext(memo) attributes(fields)');

    $faims = $instance->addChild("faims");
    $faims->addAttribute('id', DATABASE);


    // ---------------------------------------------------------------------------------------------------
    // Header part 1

    // style
    $style = new SimpleXMLElement('<style><orientation><orientation/></orientation><even><layout_weight/></even><large><layout_weight/></large></style>');
    xml_adopt($faims, $style);

    // user
    $header_user = new SimpleXMLElement('<User><User><Select_User/><Login/></User></User>');
    xml_adopt($faims, $header_user);

    // ------------------------------------------------------------------------------------------------------------

    // Header part  2

    addComment($faims, 'control tabgroup that contains button to manipulate entities: load, list, create');


    $header_control = $faims->addChild('control');   //$controlgroup in body

    if($hasMapTab){
        $tab_map = new SimpleXMLElement('<map>
            <map/>
            <mapContainer>
            <child0>
            <zoomGPS/>
            </child0>
            <child1>
            <clear/>
            </child1>
            </mapContainer>
            <mapCreateNew>
            <child1>
            <entityTypeList/>
            </child1>
            <child2>
            <create/>
            </child2>
            </mapCreateNew>
        </map>');
        xml_adopt($header_control, $tab_map);
    }

    $header_controldata = new SimpleXMLElement('
        <data>
        <entityTypeList/>
        <newEntity/>
        <Colgroup_0>
        <Col_0>
        <entitySearchTerm/>
        </Col_0>
        <Col_1>
        <entitySearchButton/>
        </Col_1>
        <Col_2>
        <entityAllButton/>
        </Col_2>
        </Colgroup_0>
        <entityList/>
    </data>');
    xml_adopt($header_control, $header_controldata);

    $header_gps = new SimpleXMLElement('<gps><GPS_Diagnostics/></gps>');
    xml_adopt($header_control, $header_gps);


    /* @todo - add to control/data
    <searchpanel>
    <child1>
    <searchInput/>
    </child1>
    <child2>
    <searchButton/>
    </child2>
    </searchpanel>
    */
    addComment($faims, 'select entity for given entity type');
    $header_selectres = new SimpleXMLElement('
        <selectEntity>
        <data>
        <newEntity/>
        <entityList/>
        </data>
    </selectEntity>');
    xml_adopt($faims, $header_selectres);

    // -------------------------------------------------------------------------------------------------------

    // Body section 3

    addComment($body, 'Describes controls for each tabgroup and its nested tabs');

    // Style
    $style = new SimpleXMLElement(
        '<group ref="style">
        <label/>
        <group ref="orientation">
        <label/>
        <input ref="orientation">
        <label>horizontal</label>
        </input>
        </group>
        <group ref="even">
        <label/>
        <input ref="layout_weight">
        <label>1</label>
        </input>
        </group>
        <group ref="large">
        <label/>
        <input ref="layout_weight">
        <label>3</label>
        </input>
        </group>
    </group>');
    xml_adopt($body, $style, 'http://www.w3.org/2002/xforms');

    // User
    $body_user = new SimpleXMLElement(
        '<group ref="User">
        <label>{User_List}</label>
        <group ref="User">
        <label>{User}</label>
        <select1 ref="Select_User">
        <label>{Select_User}</label>
        <item>
        <label>Options not loaded</label>
        <value>0</value>
        </item>
        </select1>
        <trigger ref="Login">
        <label>{Login}</label>
        </trigger>
        </group>
    </group>');
    xml_adopt($body, $body_user, 'http://www.w3.org/2002/xforms');
    //        <select1 appearance="compact" ref="users">

    $body_control = $body->addChild('group','','http://www.w3.org/2002/xforms');
    $body_control->addAttribute('ref', 'control');
    $body_control->addChild('label','{Control}');

    $map = new SimpleXMLElement(
        '<group ref="data" faims_scrollable="false">
        <label>{Entities}</label>
        <select1 ref="entityTypeList">
        <label>{Select_Entity_Type}</label>
        <item>
        <label>placeholder</label> <!-- new -->
        <value>placeholder</value>
        </item>
        </select1>
        <trigger ref="newEntity">
        <label>{Create_New_Entity}</label>
        </trigger>
        <group ref="Colgroup_0" faims_style="orientation">
        <label/>
        <group ref="Col_0" faims_style="even"> 
        <label/>
        <input ref="entitySearchTerm">
        <label/>
        </input>
        </group>
        <group ref="Col_1" faims_style="large">
        <label/>
        <trigger ref="entitySearchButton">
        <label>{Search}</label>
        </trigger>
        </group>
        <group ref="Col_2" faims_style="large">
        <label/>
        <trigger ref="entityAllButton">
        <label>{All}</label>
        </trigger>
        </group>
        </group>
        <select1 appearance="compact" ref="entityList">
        <label>Tap entity in list below to load it</label>
        <item>
        <label>Entities not loaded</label>
        <value>0</value>
        </item>
        </select1>
    </group>');
    xml_adopt($body_control, $map);    

    if($hasMapTab){

        $map = new SimpleXMLElement(
            '<group ref="map" faims_scrollable="false">
            <label>{Map}</label>
            <input ref="map" faims_map="true">
            <label/>
            </input>
            <group ref="mapContainer" faims_style="orientation">
            <label/>
            <group ref="child0" faims_style="even">
            <label/>
            <trigger ref="zoomGPS">
            <label>{centerMe}</label>
            </trigger>
            </group>
            <group ref="child1" faims_style="even">
            <label/>
            <trigger ref="clear">
            <label>{Clear}</label>
            </trigger>
            </group>
            </group>
            <group ref="mapCreateNew" faims_style="orientation">
            <label/>
            <group ref="child1" faims_style="even"> 
            <label/>
            <select1 ref="entityTypeList">
            <label/>
            <item>
            <label>placeholder</label>
            <value>placeholder</value>
            </item>
            </select1>
            </group>
            <group ref="child2" faims_style="large">
            <label/>
            <trigger ref="create">
            <label>{Create}</label>
            </trigger>
            </group>
            </group>
        </group>');
        xml_adopt($body_control, $map);
    }

    $map = new SimpleXMLElement(
        '<group ref="gps">
        <label>GPS</label>
        <input faims_read_only="true" ref="GPS_Diagnostics">
        <label>{GPS_Diagnostics}</label>
        </input>
    </group>');    
    xml_adopt($body_control, $map);    

    $select_resource = new SimpleXMLElement(
        '<group ref="selectEntity">
        <label/>
        <group ref="data" faims_scrollable="false">
        <label>Select entity or create new one</label>
        <trigger ref="newEntity">
        <label>Create New Entity</label>
        </trigger>
        <select1 appearance="compact" faims_annotation="false" faims_certainty="false" ref="entityList">
        <label>Tap entity in list below to select it</label>
        <item>
        <label>placeholder</label>
        <value>placeholder</value>
        </item>
        </select1>
        </group>
    </group>');
    xml_adopt($body, $select_resource, 'http://www.w3.org/2002/xforms');

    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $int_rt_dt_type = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_Type"];
    $int_rst_dt_desc = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayHelpText']; //not used

    $int_rt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    $int_rt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];
    $int_rt_repeat = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_MaxValues"];

    $int_rt_disp_name = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DisplayName"];
    $idx_rst_pointers = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_PtrFilteredIDs"];
    $idx_rst_defaultvalue = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DefaultValue"];
    $idx_rst_required = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_RequirementType"];

    // --------------------------------------------------------------------------------------------------------


    // Output specifications for each ArchEnt (Record type in Heurist)
    addComment($faims, 'list of tabgroups for ArchEntTypes)');

    $uniqcnt = 1;
    // Loop through each of the record types (ArchEntTypes) and output fields
    $rectyps = is_array($rt_toexport)?$rt_toexport:explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname, $rt);

        //
        $details =  $rtStructs['typedefs'][$rt]['dtFields'];
        if(!$details){
            continue;
        }

        getResStrA16n(prepareText($rtname)); //save name of rectype

        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];

        $headername = $rtnamex.'_General_Information'; //first tab implied if no header at top of record
        $headername_uids = $rtnamex.'_uids';

        addComment($faims, 'ArchEntType: '.mb_strtoupper($rtname) );
        $header_rectypeform = $faims->addChild($rtnamex);  //tabgroup for this record type
        $tab = $header_rectypeform->addChild($headername); //first tab for this record type

        addComment($body, mb_strtoupper($rtnamex), 'http://www.w3.org/2002/xforms');
        $body_rectypeform = $body->addChild('group','','http://www.w3.org/2002/xforms');
        $body_rectypeform->addChild('label',  getResStrA16n(prepareText($rtname)) );
        $body_rectypeform->addAttribute('ref', $rtnamex);
        $body_rectypeform->addAttribute('faims_archent_type', prepareText($rtname));

        $group = $body_rectypeform->addChild('group'); //first tab
        $group->addAttribute('ref', $headername);
        $group->addChild('label', 'Gen'); // getResStrA16n(prepareText($descr)) );

        // second hidden tab - contains record UID and pointers UID values
        $tab_uids = $header_rectypeform->addChild($rtnamex."_uids");
        //$tab_uids->addChild('FAIMS_UID');
        $group_uids = $body_rectypeform->addChild('group');
        $group_uids->addAttribute('ref', $rtnamex."_uids");
        $group_uids->addAttribute('faims_hidden', 'true');
        $group_uids->addChild('label', 'UIDs');

        /*        $input = new SimpleXMLElement(
        '<input ref="FAIMS_UID" faims_attribute_type="freetext" faims_certainty="false" faims_read_only="true">
        <label>UID</label>
        </input>');
        xml_adopt($group_uids, $input);
        */


        $hasattachfile = false;

        $container_no = 1; //container for resource field type

        $cnt_pertab = 0;
        $issectab = false;

        // Output for each field (attribute) in the record type
        foreach ($details as $dtid=>$detail) {

            $is_repeatable = ($detail[$int_rt_repeat]!='1');

            $dt_type = $detail[$int_rt_dt_type];


            $dtdisplayname = $detail[$int_rt_disp_name]; // the display name for this field
            $dtdisplaynamex = getProperName($dtdisplayname, $rt, $dtid);

            if($dt_type=='separator'){ // note, separator is classed as unsupported b/c it is not a data value

                /* art temp 2014-02-28
                if($hasattachfile){ //add to previous on
                $tab->addChild('viewattached');
                $trigger = $group->addChild('trigger');
                $trigger->addAttribute('ref', 'viewattached');
                $trigger->addChild('label', 'View Attached Files');
                }
                */

                $hasattachfile = false;

                if($issectab || $cnt_pertab>0){

                    $update_container = new SimpleXMLElement('
                        <container_upd>
                        <child1/>
                        <child2>
                        <Update/>
                        </child2>
                    </container_upd>');
                    xml_adopt($tab, $update_container);

                    $trigger = new SimpleXMLElement('
                        <group faims_style="orientation" ref="container_upd">
                        <label/>
                        <group faims_style="even" ref="child1">
                        <label/>
                        </group>
                        <group faims_style="large" ref="child2">
                        <label/>
                        <trigger ref="Update" faims_style_class="positive-button">
                        <label>{Save}</label>
                        </trigger>
                        </group>
                    </group>');
                    xml_adopt($group, $trigger);

                    /*old version $tab->addChild('Update');
                    $trigger = $group->addChild('trigger');
                    $trigger->addAttribute('ref', 'Update');
                    $trigger->addChild('label', getResStrA16n('Save') );*/

                    //create new tab
                    $headername = $rtnamex.'_'.$dtdisplaynamex;
                    $tab = $header_rectypeform->addChild($headername); //first tab
                    $group = $body_rectypeform->addChild('group'); //first tab
                    $group->addAttribute('ref', $headername);
                    $group->addChild('label', getResStrA16n( prepareText($detail[$int_rt_disp_name] )) );
                    $cnt_pertab=0;
                    $issectab = true;
                }
            }


            if(!in_array($dt_type, $supported)){
                continue;
            }

            $isIdentifier = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['isIdentifier'];
            $isRequired = ($detail[$idx_rst_required]=="required");

            //add field to header
            if($dt_type=='resource'){

                $dt_pointers = $detail[$idx_rst_pointers];
                $dt_pointers = explode(",", $dt_pointers);
                if(count($dt_pointers)>0){ //ignore unconstrained

                    //add to uids tab
                    $tab_uids->addChild($dtdisplaynamex.'_UID');
                    $input = new SimpleXMLElement(
                        '<input ref="'.$dtdisplaynamex.'_UID" faims_annotation="false" faims_certainty="false" faims_attribute_name="'.prepareText($dtdisplayname).'" faims_attribute_type="measure">
                        <label/>
                    </input>');
                    xml_adopt($group_uids, $input);

                    //add to header    
                    $container = new SimpleXMLElement(
                        '<container'.$container_no.'>
                        <child1>
                        <'.$dtdisplaynamex.'/>
                        </child1>
                        <child2>
                        <'.$dtdisplaynamex.'_clearPointer/>
                        </child2>
                        </container'.$container_no.'>');
                    xml_adopt($tab, $container);

                    //add to body/group/tab
                    $container = new SimpleXMLElement(
                        '<group faims_style="orientation" ref="container'.$container_no.'">
                        <label/>

                        <group faims_style="even" ref="child1">
                        <label/>

                        <select1 ref="'.$dtdisplaynamex.'" faims_annotation="false" faims_certainty="false" '
                        .($isRequired?' faims_style_class="required"':'').'>'
                        .'<label>'.getResStrA16n( prepareText($detail[$int_rt_disp_name] )).'</label>
                        <item>
                        <label>browse for resource entity</label>
                        <value>null</value>
                        </item>
                        </select1>
                        </group>

                        <group faims_style="large" ref="child2">
                        <label/>
                        <trigger ref="'.$dtdisplaynamex.'_clearPointer" faims_style_class="clear-button">
                        <label>X</label>
                        </trigger>
                        </group>
                    </group>');
                    xml_adopt($group, $container);
                    $container_no++;

                    // Browse buttons 
                    // Trigger group model
                    $triggers = $tab->addChild('container'.$container_no);

                    // Trigger group body
                    $triggerGroup = $group->addChild('group');
                    $triggerGroup->addAttribute('faims_style', 'orientation');
                    $triggerGroup->addAttribute('ref', 'container'.$container_no);
                    $triggerGroup->addChild('label', '');
                    $container_no++;

                    foreach ($dt_pointers as $rt2) {
                        // Label
                        $rtname2 = $rtStructs['names'][$rt2];
                        $label = 'Browse '.prepareText($rtname2);

                        // Ref
                        $rtnamex2 = getProperName($rtname2, $rt2);
                        $ref = $dtdisplaynamex.'_browse_'.$rtnamex2;

                        addEvenTrigger($triggers, $triggerGroup, $ref, $label, 'browse-button');
                    }//for

                }//if ignore unconstrained

            }else if ($dt_type=='date' && @$detail[$idx_rst_defaultvalue]!='today'){

                $date_pickup = new SimpleXMLElement('
                    <Dategroup'.$dtdisplaynamex.'>
                    <child0>
                    <'.$dtdisplaynamex.'/>
                    </child0>
                    <child1>
                    <attach'.$dtdisplaynamex.'/>
                    </child1>
                    </Dategroup'.$dtdisplaynamex.'>');    
                xml_adopt($tab, $date_pickup);




                //add into body
                $date_pickup = new SimpleXMLElement(
                    '<group ref="Dategroup'.$dtdisplaynamex.'" faims_style="orientation">
                    <label/>
                    <group ref="child0" faims_style="even"> 
                    <label/>
                    <input ref="'.$dtdisplaynamex
                    .'" faims_attribute_name="'
                    .prepareText($dtdisplayname).'" faims_certainty="true" faims_attribute_type="measure" faims_read_only="false"'
                    .($isRequired?' faims_style_class="required"':'').'>
                    <label>'
                    .getResStrA16n( prepareText($detail[$int_rt_disp_name] )).'</label>
                    </input>
                    </group>
                    <group ref="child1" faims_style="large">
                    <label/>
                    <trigger ref="attach'.$dtdisplaynamex.'" faims_style_class="attach-button">
                    <label>{Pick_Date}</label>
                    </trigger>
                    </group>
                </group>');
                xml_adopt($group, $date_pickup);


            }else{
                $tab->addChild($dtdisplaynamex); //add to structure in header
                if($dt_type=='file'){
                    $tab->addChild('attach'.$dtdisplaynamex);
                }


                $isvocab = ($dt_type=='enum');

                // TO LOOK AT  -  isIdentifier not defined (in logs, 5.20pnm 30/9/13)

                $inputtype = 'input';

                if($isvocab){
                    $termsCount = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'];
                    $is_hierarchy = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['is_hierarchy']==true;
                    if($is_hierarchy && !($is_repeatable && $termsCount<13)){
                        $tp = "Hierarchical DropDown";
                        $inputtype = 'select1';
                    }else{
                        $tp = $is_repeatable?"Checkbox Group"
                        :(($termsCount<5)?"Radiogroup":"Dropdown");
                        $inputtype = $is_repeatable?'select':'select1';//if enum repeatable checkbox list otherwise dropdown
                    }

                    addComment($group, "We would like to see ".$tp );

                }else if($dt_type=='file'){
                    $inputtype = 'select';
                    //<select ref="pictures" type="camera" faims_sync="true" faims_attribute_name="picture" faims_attribute_type="freetext" faims_annotation="false" faims_certainty="false">
                    //<select ref="videos" type="video" faims_attribute_name="video" faims_attribute_type="freetext" faims_annotation="false" faims_certainty="false">
                    //<select ref="files" type="file" faims_attribute_name="file" faims_attribute_type="freetext" faims_annotation="false" faims_certainty="false">
                    //<select ref="audios" type="file" faims_attribute_name="audio" faims_attribute_type="freetext" faims_annotation="false"    faims_certainty="false">
                }

                $input = $group->addChild($inputtype);
                $input->addChild('label', getResStrA16n( prepareText($detail[$int_rt_disp_name] )) );
                $input->addAttribute('ref', $dtdisplaynamex);
                $input->addAttribute('faims_attribute_name', prepareText($dtdisplayname)); //was $dtname));

                if($isRequired){
                    $input->addAttribute('faims_style_class', 'required');
                }
                if(!$isvocab || $certainityForVocab){
                    $input->addAttribute('faims_certainty', 'true');
                    $input->addAttribute('faims_annotation','true');
                }else{
                    $input->addAttribute('faims_certainty', 'false');
                    $input->addAttribute('faims_annotation','false');
                }
                if($isvocab && !$is_repeatable) {

                    $termsCount = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'];
                    if($termsCount<5){
                        $input->addAttribute('appearance', 'full');
                    }
                    //$input->addAttribute('appearance', ($termsCount>4)?'compact':'full');
                }

                $input->addAttribute('faims_attribute_type', $isvocab?'vocab':'measure');

                if($dt_type=='file'){

                    $ftype = detectFileType($dtdisplayname);
                    $actionlabel = $ftype[0];
                    $filetype = $ftype[1];

                    if($filetype == 'file'){
                        $hasattachfile = true;
                    }else if($filetype == 'camera'){
                        $input->addAttribute('faims_sync', 'true');
                        // absence of attribute = false, which is appropriate for video and audio
                    }

                    $input->addAttribute('type', $filetype);

                    $trigger = $group->addChild('trigger');
                    $trigger->addAttribute('ref', 'attach'.$dtdisplaynamex);
                    $trigger->addChild('label', $actionlabel);
                } else if ($dt_type=='date' && @$detail[$idx_rst_defaultvalue]='today') {
                    $input->addAttribute('faims_read_only', 'true');
                } else {
                    $input->addAttribute('faims_read_only', 'false');
                }

                if (($isvocab) || ($dt_type=='file')) { //empty value for selection
                    $item = $input->addChild('item');
                    $item->label = 'placeholder';
                    $item->value = 'placeholder';
                }

                $ftype = null;
                if($dt_type=='float' || $dt_type=='integer'){
                    $ftype =  'decimal';
                }else if($dt_type=='blocktext'){
                    $ftype = 'string';
                }

                if($ftype){
                    $path = '/faims/'.$rtnamex.'/'.$headername.'/'.$dtdisplaynamex;
                    $bind = $model->addChild('bind');
                    $bind->addAttribute('nodeset', $path);
                    $bind->addAttribute('type', $ftype);
                }
            }

            $cnt_pertab++;
            /*
            if($dt_type=='enum' || $dt_type=='relationtype'){
            $terms = $detail[$int_rt_termtree];
            //convert to tree
            getTermsTree($property, $dt_type, $terms);
            }
            */
        }//for details

        /* art temp 2014-02-28
        if($hasattachfile){
        $tab->addChild('viewattached');
        $trigger = $group->addChild('trigger');
        $trigger->addAttribute('ref', 'viewattached');
        $trigger->addChild('label', 'View Attached Files');
        }
        */

        //add gps_location  - create new tab
        if( in_array($rt, $rt_geoenabled) ){

            $headername = $rtnamex.'_gps_location';
            $tab = $header_rectypeform->addChild($headername);
            $group = $body_rectypeform->addChild('group');
            $group->addAttribute('ref', $headername);
            $group->addChild('label', '{GPS_Location}' );


            $tabcontent = new SimpleXMLElement(
                '<Colgroup_GPS>
                <Col_0>
                <Latitude/>
                <Northing/>
                </Col_0>
                <Col_1>
                <Longitude/>
                <Easting/>
                </Col_1>
            </Colgroup_GPS>');
            xml_adopt($tab, $tabcontent);
            $tab->addChild('Take_From_GPS');  

            $groupcontent = new SimpleXMLElement(
                '<group ref="Colgroup_GPS" faims_style="orientation">
                <label/>
                <group ref="Col_0" faims_style="even">
                <label/>
                <input ref="Latitude" faims_attribute_name="Latitude" faims_attribute_type="measure" faims_read_only="true">
                <label>{Latitude}</label>
                </input>
                <input ref="Northing" faims_attribute_name="Northing" faims_attribute_type="measure" faims_read_only="true">
                <label>{Northing}</label>
                </input>
                </group>
                <group ref="Col_1" faims_style="even">
                <label/>
                <input ref="Longitude" faims_attribute_name="Longitude" faims_attribute_type="measure" faims_read_only="true">
                <label>{Longitude}</label>
                </input>
                <input ref="Easting" faims_attribute_name="Easting" faims_attribute_type="measure" faims_read_only="true">
                <label>{Easting}</label>
                </input>
                </group>
            </group>');
            xml_adopt($group, $groupcontent);

            $btn_take_gps = $group->addChild('trigger');
            $btn_take_gps->addAttribute('ref', 'Take_From_GPS');
            $btn_take_gps->addChild('label', '{Take_From_GPS}' );

        }


        // Save, Update, Delete buttons etc.
        // Trigger group model (add tp header)
        $triggers = $tab->addChild('container'.$container_no);

        // Trigger group body
        $triggerGroup = $group->addChild('group');
        $triggerGroup->addAttribute('faims_style', 'orientation');
        $triggerGroup->addAttribute('ref', 'container'.$container_no);
        $triggerGroup->addChild('label', '');
        $container_no++;

        // AttachGeometry
        if( $hasMapTab && in_array($rt, $rt_geoenabled) ){
            addEvenTrigger($triggers, $triggerGroup, 'AttachGeometry', 'Attach Geometry from Map', 'neutral-button');
        }

        // Update
        addEvenTrigger($triggers, $triggerGroup, 'Update', 'Save Entity', 'positive-button');
        addEvenTrigger($triggers, $triggerGroup, 'UpdateAndClose', 'Save Entity and Close', 'positive-button');

        // Delete
        addEvenTrigger($triggers, $triggerGroup, 'Delete', 'Delete Entity', 'negative-button');

    }//foreach

    return prepareXml($root);

} // generation of user interface schema

/**
* Adds an event trigger inside a group to the model and body
*
* @param mixed $model XML model
* @param mixed $body  XML model
* @param mixed $ref   Trigger reference name
* @param mixed $label Trigger label name
* @param mixed $class Trigger class name
*/
function addEvenTrigger($model, $body, $ref, $label, $class) {
    // Model
    $container = $model->addChild('container_'.$ref);
    $trigger = $container->addChild($ref);

    // Body
    $group = $body->addChild('group');
    $group->addAttribute('ref', 'container_'.$ref);
    $group->addAttribute('faims_style', 'even');
    $group->addChild('label', '');

    $trigger = $group->addChild('trigger');
    $trigger->addAttribute('ref', $ref);
    $trigger->addAttribute('faims_style_class', $class);
    $trigger->addChild('label', getResStrA16n( $label ) );
}


/** Returns the Heurist styling */
function getStyling() {
    return file_get_contents('templates/style.css');
}

function generate_Logic($projname, $rt_toexport, $rt_toexport_toplevel, $rt_geoenabled){

    global $rtStructs, $dtTerms, $supported;

    //need to create substitutes
    $TEMPLATE_EVENTS = '';              //list of recctypes and events
    $TEMPLATE_DEFAULTVALUES = '';       //default values and reset link fields
    $TEMPLATE_TOVALIDATE = '';          //list of rectypes to validate    
    $TEMPLATE_FIELDS_TOVALIDATE = '';   //list of fields to validate
    $TEMPLATE_RELATED_INIT = '';
    $TEMPLATE_RELATED_SAVE = '';
    $TEMPLATE_SELECTORS = ''; //enums
    $TEMPLATE_MAPCOLORS = '';            //list of mapenabled rectypes and colors
    $TEMPLATE_INIT_SYNC_AND_GPS  = '';

    $startSync = @$_REQUEST['ct1']=="1";
    $startInternalGPS = @$_REQUEST['ct2']=="1";
    $hasControlExternalGPS = @$_REQUEST['ct3']=="1";
    $hasControlTracklog = @$_REQUEST['ct4']=="1";
    $hasMapTab = @$_REQUEST['mainmt']=="1";

    $colors = array('BLUE','DKGRAY','GREEN','MAGENTA','CYAN','LTGRAY','RED','YELLOW');
    $colors_idx = 0;

    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $int_rt_dt_type = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_Type"];

    //not used $int_rt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    //not used $int_rt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];
    $int_dt_repeat = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_MaxValues"];

    $int_rt_disp_name = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DisplayName"];

    $idx_rst_pointers = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_PtrFilteredIDs"];
    $idx_rst_required = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_RequirementType"];
    $idx_rst_defaultvalue = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DefaultValue"];

    $event_section = '';

    // Loop through each of the record types (ArchEntTypes) and output fields
    $rectyps = is_array($rt_toexport) ?$rt_toexport :explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname, $rt);

        $details =  $rtStructs['typedefs'][$rt]['dtFields'];
        if(!$details){
            continue;
        }

        $defaultvalue_section = '';
        $reset_link_section = '';

        $TEMPLATE_EVENTS .= 'rectypeToEntity.add(new NameValuePair("'.$rtnamex.'", "'.$rtname.'"));
        ';
        if( in_array($rt, $rt_geoenabled) ){
            $TEMPLATE_EVENTS .= 'rectypeToEntityGeo.add(new NameValuePair("'.$rtnamex.'", "'.$rtname.'"));
            ';
        }
        if( in_array($rt, $rt_toexport_toplevel) ){
            $TEMPLATE_EVENTS .= 'rectypeToEntityMain.add(new NameValuePair("'.$rtnamex.'", "'.$rtname.'"));
            ';
        }

        $headername_uids = $rtnamex.'_uids';

        //init navigation    
        $event_section .= '
        addOnEvent("'.$rtnamex.'", "show", "addNavigationButtons(\"'.$rtnamex.'\")");';
        // Show edit form
        $event_section .= '
        addOnEvent("'.$rtnamex.'", "show", "onShowEditForm(\"'.$rtnamex.'\")");';
        // Enable auto saving  - disable 2016-03-08
        //        $event_section .= '
        //addOnEvent("'.$rtnamex.'", "show", "saveEntity(\"'.$rtnamex.'\", false, false)");';

        $load_related_part = '';
        $save_related_part = '';

        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];

        $hasattachfile = false;
        $headername = $rtnamex."/".$rtnamex.'_General_Information'; //first tab implied if no header at top of record
        $cnt_pertab = 0;
        $issectab = false;
        $mandatory_attributes = '';

        // Output for each field (attribute) in the record type
        foreach ($details as $dtid=>$detail) {

            $is_repeatable = ($detail[$int_dt_repeat]!='1');

            $default_value = $detail[$idx_rst_defaultvalue];

            $dt_type = $detail[$int_rt_dt_type];

            $dtdisplayname = $detail[$int_rt_disp_name]; // the display name for this record type
            $dtdisplaynamex = getProperName($dtdisplayname, $rt, $dtid);

            if($dt_type=='separator'){ // note, separator is classed as unsupported b/c it is not a data value

                /* art temp 2014-02-28
                if($hasattachfile){  //add events to view attached files
                $eventsection .= '
                onEvent("'.$headername.'/viewattached", "click", "viewArchEntAttachedFiles(entityId)");';
                }
                */
                $hasattachfile = false;
                //new tab
                if($issectab || $cnt_pertab>0){

                    //show save button on every tab    
                    $event_section .= '
                    addOnEvent("'.$headername.'/Update", "delayclick", "saveEntity(\"'.$rtnamex.'\", true, false)");';

                    $headername = $rtnamex."/".$rtnamex.'_'.$dtdisplaynamex;
                    $issectab=true;
                    $cnt_pertab=0;
                }
            }

            if(!in_array($dt_type, $supported)){
                continue;
            }

            if($dt_type=='file'){

                $ftype = detectFileType($dtdisplayname);
                $actionlabel = $ftype[0];
                $filetype = $ftype[1];

                if($filetype == 'video'){
                    $action = 'attachVideoTo';
                }else if($filetype == 'sound'){
                    $action = 'attachAudioTo';
                }else if($filetype == 'camera'){
                    $action = 'attachPictureTo';
                }else {  //if ($filetype == 'file')
                    $action = 'attachFileTo';
                    $hasattachfile = true;
                }



                $event_section .= '
                addOnEvent("'.$headername.'/attach'.$dtdisplaynamex.'", "click", "'.$action.'(\"'.$headername.'/'.$dtdisplaynamex.'\")");';

            }else if ($dt_type=='date') {

                if( $default_value=='today'){
                    $defaultvalue_section .= '
                    setTimestamp( "'.$headername.'/'.$dtdisplaynamex.'" );'; 
                }else{   
                    $event_section .= '
                    addOnEvent("'.$headername.'/attach'.$dtdisplaynamex.'", "click", "pickupDate(\"'.$headername.'/'.$dtdisplaynamex.'\")");';
                }

            }else if ($dt_type=='enum') {

                $is_hierarchy = (@$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['is_hierarchy']==true);
                $termsCount = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'];

                if($is_hierarchy && !($is_repeatable && $termsCount<13)){
                    $makeVocab = '
                    populateHierarchicalDropDown("'.$headername.'/'.$dtdisplaynamex.'", "'.prepareText($dtdisplayname).'");
                    ';
                }else{

                    if($is_repeatable){
                        $action = 'populateCheckBoxGroup';
                    }else if ($termsCount<5) {
                        $action = 'populateRadioGroup';
                    }else{
                        $action = 'populateDropDown';
                    }

                    // makeVocab method
                    $makeVocab = '
                    fetchAll("select vocabid, vocabname from vocabulary join attributekey using (attributeid) where attributename = \'' .prepareText($dtdisplayname). '\' order by vocabcountorder",
                    new FetchCallback() {
                    onFetch(result) {
                    '.
                    $action.'("'.$headername.'/'.$dtdisplaynamex.'", result);
                    }
                    });
                    ';

                }
                // Add to load selectors
                $TEMPLATE_SELECTORS .= $makeVocab;
                //$TEMPLATE_SELECTORS .= ''.($action.'("'.$headername.'/'.$dtdisplaynamex.'", makeVocab("'.prepareText($dtdisplayname).'"));'); // OLD
            }else if ($dt_type=='resource') {

                $dt_pointers = $detail[$idx_rst_pointers];
                $dt_pointers = explode(",", $dt_pointers);
                if(count($dt_pointers)>0){ //ignore unconstrained

                    foreach ($dt_pointers as $rt2) {

                        $rtname2 = $rtStructs['names'][$rt2];
                        $rtnamex2 = getProperName($rtname2, $rt2);

                        $event_section .= '
                        addOnEvent("'.$headername.'/'.$dtdisplaynamex.'_browse_'.$rtnamex2.'", "click", "startSelectEntity(\"'.$rtnamex2.'\", \"'.$headername.'/'.$dtdisplaynamex.'\")");';
                    }//for

                    $event_section .= '
                    addOnEvent("'.$headername.'/'.$dtdisplaynamex.'_clearPointer", "click", "clearPointer(\"'.$headername.'/'.$dtdisplaynamex.'\")");';

                    $load_related_part .= (($load_related_part?'else':'').'
                        if("'.$dtdisplaynamex.'".equals(attrName)){
                        fillPointer("'.$headername.'/'.$dtdisplaynamex.'", attrVal);
                    }');

                    $save_related_part .= '
                    saveRelation("has'.$dtdisplaynamex.'", uuid, "'.$headername.'/'.$dtdisplaynamex.'");
                    ';


                    $reset_link_section .= '
                    clearPointerUI("'.$headername.'/'.$dtdisplaynamex.'");';


                    if($detail[$idx_rst_required]=="required"){
                        $mandatory_attributes .= ' 
                        f.add(fieldPair("'.$headername_uids.'/'.$dtdisplaynamex.'_UID", "'.prepareText($dtdisplayname).'", true, null));';
                    }

                }
            }else  if (($dt_type=='freetext' || $dt_type=='blocktext') && !is_empty($default_value)) {
                $defaultvalue_section .= '
                setFieldValue( "'.$headername.'/'.$dtdisplaynamex.'", "'.mysql_real_escape_string($default_value).'" );'; 
            }else  if (($dt_type=='float' || $dt_type=='integer' || $dt_type=='year') && is_numeric($default_value)) {
                $defaultvalue_section .= '
                setFieldValue( "'.$headername.'/'.$dtdisplaynamex.'", "'.$default_value.'" );'; 
            }

            if((!($dt_type=='resource' || $dt_type=='file')) && ($detail[$idx_rst_required]=="required")){

                $mandatory_attributes .= '
                f.add(fieldPair("'.$headername.'/'.$dtdisplaynamex.'", "'.prepareText($dtdisplayname).'", true, null));';
            }



            $cnt_pertab++;
        }//for detail types

        /* art temp 2014-02-28
        if($hasattachfile){  //add events to view attached files
        $eventsection .= '
        onEvent("'.$headername.'/viewattached", "click", "viewArchEntAttachedFiles(entityId)");';
        }
        */
        $compare_type = '
        if("'.$rtnamex.'".equals(rectypeTab)){
        ';

        if($load_related_part!=''){

            $TEMPLATE_RELATED_INIT .= $compare_type.$load_related_part.'
            }
            ';
            $TEMPLATE_RELATED_SAVE .= $compare_type.$save_related_part.'
            return;
            }
            ';
        }

        if( in_array($rt, $rt_geoenabled) ){

            $headername = $rtnamex."/".$rtnamex.'_gps_location';            

            if( $hasMapTab ) {
                $event_section .= '
                addOnEvent("'.$headername.'/AttachGeometry", "click", "doAttachGeometry(\"'.$rtnamex.'\")");';
            }
            $event_section .= '
            addOnEvent("'.$headername.'/Take_From_GPS", "click", "takePoint(\"'
            .$rtnamex.'\", \"'.$headername.'/\")");';

            $TEMPLATE_MAPCOLORS .=  '
            rectypeToColor.put("'.$rtnamex.'", Color.'.$colors[$colors_idx].');';
            $colors_idx++;
            if($colors_idx>=count($colors)) $colors_idx = 0;
        }

        //show ALL save buttons on the last tab
        $event_section .= '
        addOnEvent("'.$headername.'/Update", "delayclick", "saveEntity(\"'.$rtnamex.'\", true, false)");
        addOnEvent("'.$headername.'/UpdateAndClose", "delayclick", "saveEntity(\"'.$rtnamex.'\", true, true)");
        addOnEvent("'.$headername.'/Delete", "delayclick", "deleteEntity(\"'.$rtnamex.'\")");
        ';

        $compare_type = '
        if("'.$rtnamex.'".equals(rectypeTab)){
        ';

        if($defaultvalue_section!='' || $reset_link_section!=''){
            $TEMPLATE_DEFAULTVALUES .= $compare_type . $reset_link_section . $defaultvalue_section.'  
            return;
            }';
        }
        if($mandatory_attributes!=''){
            $TEMPLATE_FIELDS_TOVALIDATE .= $compare_type . $mandatory_attributes.' }';
            $TEMPLATE_TOVALIDATE .= '
            tabgroupsToValidate.add("'.$rtnamex.'");';
        }


    }//for record types

    if( $hasMapTab ){
        $TEMPLATE_MAPCOLORS .= '
        //        
        mapRefresh(){
        refreshMap("control/map/map");
        onClearMap();
        }
        mapInit();';
    }else{
        $TEMPLATE_MAPCOLORS .= '
        mapRefresh(){ 
        }';
    }

    $TEMPLATE_EVENTS .= $event_section;

    $TEMPLATE_HEADER = '
    /******
    FAIMS Logic File generated by Heurist Vsn '.HEURIST_VERSION.', '. date('l jS \of F Y h:i:s A').'
    Database: '.DATABASE.'   Heurist user:'.get_user_name().'
    ******/
    ';

    $out = file_get_contents('templates/ui_logic.bsh');
    $out = str_replace('{{TEMPLATE_HEADER}}',$TEMPLATE_HEADER,$out);
    $out = str_replace('{{TEMPLATE_EVENTS}}',$TEMPLATE_EVENTS,$out);
    $out = str_replace('{{TEMPLATE_DEFAULTVALUES}}',$TEMPLATE_DEFAULTVALUES,$out);
    $out = str_replace('{{TEMPLATE_FIELDS_TOVALIDATE}}',$TEMPLATE_FIELDS_TOVALIDATE,$out);
    $out = str_replace('{{TEMPLATE_TOVALIDATE}}',$TEMPLATE_TOVALIDATE,$out);
    $out = str_replace('{{TEMPLATE_RELATED_INIT}}',$TEMPLATE_RELATED_INIT,$out);
    $out = str_replace('{{TEMPLATE_RELATED_SAVE}}',$TEMPLATE_RELATED_SAVE,$out);
    $out = str_replace('{{TEMPLATE_SELECTORS}}',$TEMPLATE_SELECTORS,$out);
    $out = str_replace('{{TEMPLATE_MAPCOLORS}}',$TEMPLATE_MAPCOLORS,$out);

    if($startSync){ // there are start/stop synch controls on the interface
        $TEMPLATE_INIT_SYNC_AND_GPS = '
        setFileSyncEnabled(true); 
        setSyncEnabled(true);
        ';
    }

    if($startInternalGPS){
        $TEMPLATE_INIT_SYNC_AND_GPS .= '
        startInternalGPS();
        showToast("{Internal_GPS_Enabled}");
        updateGPSDiagnostics();';
    }

    $out = str_replace('{{HASCONTROL_GPSEXT}}', $hasControlExternalGPS?'true':'false', $out);
    $out = str_replace('{{TEMPLATE_INIT_SYNC_AND_GPS}}',$TEMPLATE_INIT_SYNC_AND_GPS, $out);

    // Feb 2014: Brian says tracklog requires extra logic which is not yet available, so the option
    //           to switch this section on has been removed from the export interface
    if(false && $hasControlTracklog){
        $out = $out.'
        /*** TRACKLOG ***/
        onEvent("control/gps/starttrackingtime", "click", "startTrackingGPS(\"time\", 10, \"saveTimeGPSTrack()\")");
        onEvent("control/gps/starttrackingdistance", "click", "startTrackingGPS(\"distance\", 10, \"saveDistanceGPSTrack()\")");
        onEvent("control/gps/stoptracking", "click", "stopTrackingGPS()");

        saveTimeGPSTrack() {
        List attributes = createAttributeList();
        attributes.add(createEntityAttribute("gps_type", "time", null, null, null));
        saveGPSTrack(attributes);
        }

        saveDistanceGPSTrack() {
        List attributes = createAttributeList();
        attributes.add(createEntityAttribute("gps_type", "distance", null, null, null));
        saveGPSTrack(attributes);
        }

        saveGPSTrack(List attributes) {
        position = getGPSPosition();
        if (position == null) return;

        attributes.add(createEntityAttribute("gps_user", "" + user.getUserId(), null, null, null));
        attributes.add(createEntityAttribute("gps_timestamp", "" + getCurrentTime(), null, null, null));
        attributes.add(createEntityAttribute("gps_longitude", "" + position.getLongitude(), null, null, null));
        attributes.add(createEntityAttribute("gps_latitude", "" + position.getLatitude(), null, null, null));
        attributes.add(createEntityAttribute("gps_heading", "" + getGPSHeading(), null, null, null));
        attributes.add(createEntityAttribute("gps_accuracy", "" + getGPSEstimatedAccuracy(), null, null, null));

        positionProj = getGPSPositionProjected();
        Point p = new Point(new MapPos(positionProj.getLongitude(), positionProj.getLatitude()), null, (PointStyle) null, null);
        ArrayList l = new ArrayList();
        l.add(p);

        saveArchEnt(null, "gps_track", l, attributes);
        }
        ';
    }

    return $out;
}


//sanitize rt, dt, disp names
function getProperName($name, $rt=null, $dt=null){

    $name = str_replace(' ','_',trim(ucwords($name)));

    $goodname = preg_replace('~[^a-z0-9]+~i','', $name); //str_replace('__','_',);
    $bettername = prepareText($goodname);

    if($bettername==""){
        if($rt!=null){
            $bettername ="ArchEntType".$rt;
            if($dt!=null){
                $bettername ="_Attribute".$dt;
            }
        }
    }
    return trim($bettername);

}
// remove last (s), replace <>
function prepareText($str){

    if(substr($str, -3) === "(s)"){
        $str = substr($str,0,-3);
    }

    $str = str_replace('<', 'LT', $str);
    $str = str_replace('>', 'GT', $str);
    $str = str_replace("'", '', $str);
    $str = str_replace('"', '', $str);
    $str = str_replace('`', '', $str);
    $str = str_replace('&', 'and', $str);
    //no entities $str = str_replace("&", "&amp;", $str);
    //no escaping $str = mysql_real_escape_string($str);
    return trim($str);
}

function prepareText2($str){
    $str = prepareText($str);
    $str = mysql_real_escape_string($str);
    return $str;
}

/*
If the field name contains the substring 'photo', 'picture', 'image', 'take' / 'video', 'movie', 'shoot' / 'sound', 'audio', 'record'  (case indifferent),
set the field to the approriate type - take picture, shoot video, record audio (prioritise photo, picture, video, movie, sound and audio
over the verbs
*/
function detectFileType($dtname){
    $name1 = mb_strtolower($dtname);
    //$name2 = mb_strtolower($displayname);
    $actionlabel = 'Attach File';
    $filetype = 'file';

    if( strpos($name1,'photo')!== false ||  strpos($name1,'picture')!== false || strpos($name1,'image')!== false ){
        $filetype = 'camera';
        $actionlabel = 'Take Picture';
    } else if ( strpos($name1,'video')!== false ||  strpos($name1,'movie')!== false || strpos($name1,'image')!== false ){
        $filetype = 'video';
        $actionlabel = 'Shoot Video';
    } else if ( strpos($name1,'sound')!== false ||  strpos($name1,'audio')!== false ){
        $filetype = 'sound';
        $actionlabel = 'Record Sound';
    } else if ( strpos($name1,'take')!== false ){
        $filetype = 'camera';
        $actionlabel = 'Take Picture';
    } else if ( strpos($name1,'shoot')!== false ){
        $filetype = 'video';
        $actionlabel = 'Shoot Video';
    } else if ( strpos($name1,'record')!== false ){
        $filetype = 'sound';
        $actionlabel = 'Record Sound';
    }
    return array($actionlabel, $filetype);
}

/**
* add one simple xml to another
*
* @param mixed $root
* @param mixed $new
*/
function xml_adopt($root, $new, $ns=null) {

    $node = $root->addChild($new->getName(), trim((string) $new), $ns);
    foreach($new->attributes() as $attr => $value) {
        $node->addAttribute($attr, $value);
    }
    foreach($new->children() as $ch) {
        xml_adopt($node, $ch);
    }
}

//
// create a dash-separated string consisting of a term label and all its parents
//
function getFullTermName($term, $datatype){

    global $dtTerms, $ind_parentid, $ind_label;

    $name = "";

    if($term){
        if($term[$ind_parentid]>0){
            $allterms = $dtTerms['termsByDomainLookup'][$datatype];
            $name = getFullTermName( @$allterms[$term[$ind_parentid]], $datatype);
        }
        $name = ($name?$name."-":"").prepareText($term[$ind_label]);
    }
    return $name;
}


$datatype = '';
// return count
// for UI_Schema
//
function getTermsTree($property, $d_type, $terms){

    global $dtTerms, $datatype;

    $datatype = $d_type;

    if($datatype == "relmarker" || $datatype === "relationtype"){
        $datatype = "relation";
    }else if(!($datatype=="enum" || $datatype=="relation")){
        return 0;
    }

    if(is_numeric($terms)){ //vocabulary

        $termTree =  $dtTerms['treesByDomain'][$datatype][$terms];
    }else{

        $termTree = json_decode($terms, true);
    }

    if(count($termTree)<1){
        return 0;
    }

    $parent = $property->addChild('lookup');

    $cnt = createSubTree($parent, $termTree, "");

    return $cnt;
}

//
// if at least one term has children
//
function isTermsHierarchical($d_type, $terms){
    global $dtTerms, $datatype;

    $datatype = $d_type;

    if($datatype == "relmarker" || $datatype === "relationtype"){
        $datatype = "relation";
    }else if(!($datatype=="enum" || $datatype=="relation")){
        return false;
    }
    if(is_numeric($terms)){ //vocabulary
        $termTree =  $dtTerms['treesByDomain'][$datatype][$terms];
    }else{
        //individual selection
        $termTree = json_decode($terms, true);
    }

    if(count($termTree)<1){
        return false;
    }

    foreach ($termTree as $termid=>$child_terms){
        if($child_terms && is_array($child_terms) && count($child_terms)>0){
            return true;
        }
    }
    return false;
}

function sortByName($a, $b){
    global $dtTerms, $ind_label, $datatype;
    $termLookup = $dtTerms['termsByDomainLookup'][$datatype];

    $termName1 = @$termLookup[$a][$ind_label];
    $termName2 = @$termLookup[$b][$ind_label];
    return strcmp ($termName1, $termName2);

}
function sortByCodeNum($a,$b){
    global $dtTerms, $datatype;
    $termLookup = $dtTerms['termsByDomainLookup'][$datatype];
    $ind_tcode = $dtTerms['fieldNamesToIndex']['trm_Code'];

    $termCode1 = @$termLookup[$a][$ind_tcode];
    $termCode2 = @$termLookup[$b][$ind_tcode];
    return ($termCode1<$termCode2)?-1:1;
}
function sortByCodeAlpha($a,$b){
    global $dtTerms, $datatype;
    $termLookup = $dtTerms['termsByDomainLookup'][$datatype];
    $ind_tcode = $dtTerms['fieldNamesToIndex']['trm_Code'];

    $termCode1 = @$termLookup[$a][$ind_tcode];
    $termCode2 = @$termLookup[$b][$ind_tcode];
    return strcmp ($termCode1, $termCode2);
}


function createSubTree($parent, $termTree, $parentname){

    global $dtTerms, $ind_label, $datatype;

    if(!is_array($termTree) || count($termTree)<1){
        return 0;
    }

    $termLookup = $dtTerms['termsByDomainLookup'][$datatype];
    $ind_label = $dtTerms['fieldNamesToIndex']['trm_Label'];
    $ind_descr = $dtTerms['fieldNamesToIndex']['trm_Description'];
    $ind_ccode = $dtTerms['fieldNamesToIndex']['trm_ConceptID'];
    $ind_tcode = $dtTerms['fieldNamesToIndex']['trm_Code'];

    $is_all_codes_numeric = true;
    $cnt_withcodes = 0;

    //first loop need to order by code (numeric or alphanumeric)  or by name (alphanumeric)
    foreach ($termTree as $termid=>$child_terms){
        $termName = @$termLookup[$termid][$ind_label];
        if($termName){
            $termCode = @$termLookup[$termid][$ind_tcode];
            if($termCode){
                $is_all_codes_numeric = $is_all_codes_numeric && is_numeric($termCode);
                $cnt_withcodes++;
            }else{
                $is_all_codes_numeric = false;
            }
        }
    }
    $fsort = 'sortByName';
    if($cnt_withcodes>count($termTree)*0.89){
        if($is_all_codes_numeric){
            $fsort = 'sortByCodeNum';
        }else{
            $fsort = 'sortByCodeAlpha';
        }
    }

    uksort( $termTree, $fsort );

    $cnt = 0;

    foreach ($termTree as $termid=>$child_terms){

        $termName = @$termLookup[$termid][$ind_label];

        if($termName){
            $termDescr = @$termLookup[$termid][$ind_descr];
            $termCcode = @$termLookup[$termid][$ind_ccode]; //concept code
            $termCode = @$termLookup[$termid][$ind_tcode];
            if($termCode){
                //$termCode = "[".$termCode."]";
            }else{
                $termCode = "";
            }

            if(strpos($termCcode,'-')===false) {
                $termCcode = '0-'.$termid;
            }
            $term = $parent->addChild('term', getResStrA16n(prepareText($termName)) );

            $term->addAttribute('HeuristID', $termCcode);
            $term->addAttribute('StandardCode', $termCode);
            //$term->addAttribute('pictureURL', '');
            //$term->addAttribute('semanticMapURL', '');


            $term->addChild('description', ($termDescr?prepareText($termDescr):"") );
            $cnt++;

            $cnt = $cnt + createSubTree($term, $child_terms, $parentname.$termName."-");
        }
    }

    return $cnt;
}

//
//
//
function getTermsPlainList($datatype, $terms, $disableTerms){

    global $dtTerms; //, $ind_label, $ind_descr, $coding_sheet, $ind_tcode;

    $res = array();

    if($datatype == "relmarker" || $datatype === "relationtype"){
        $datatype = "relation";
    }else if(!($datatype=="enum" || $datatype=="relation")){
        return $res;
    }

    $termTree = null;
    if(is_numeric($terms)){
        $termTree =  $dtTerms['treesByDomain'][$datatype][$terms];
    }else if(is_string($terms)){
        $termTree = json_decode($terms);
    }else if(is_array($terms)){
        $termTree = $terms;
    }
    if($termTree && count($termTree)>0){

        //$termLookup = $dtTerms['termsByDomainLookup'][$datatype];

        foreach ($termTree as $termid=>$child_terms){


            if(count($child_terms)>0){
                $res2 = getTermsPlainList($datatype, $child_terms, $disableTerms);
                $res = array_merge($res, $res2);
            }else  if(!@$disableTerms[$termid]){
                array_push($res, $termid);
            }
        }
    }
    return $res;
}

/**
* Adds a CDATA property to an XML document.
*
* @param string $name
*   Name of property that should contain CDATA.
* @param string $value
*   Value that should be inserted into a CDATA child.
* @param object $parent
*   Element that the CDATA child should be attached too.
*/
function addCdata($name, $value, &$parent) {
    $child = $parent->addChild($name);

    if ($child !== NULL) {
        $child_node = dom_import_simplexml($child);
        $child_owner = $child_node->ownerDocument;
        $child_node->appendChild($child_owner->createCDATASection($value));
    }

    return $child;
};

function formatXML($simpleXml){
    $dom = dom_import_simplexml($simpleXml)->ownerDocument;
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    return $dom->saveXML();
}
function formatXML2($sxml){
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($sxml);
    return $dom->saveXML();
}

//isNullOrEmptyString
function is_empty($question){
    return (!isset($question) || trim($question)==='');
}


?>