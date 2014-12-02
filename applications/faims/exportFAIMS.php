<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
*   Write FAIMS project definition files (db schema, ui schema, a16N, ui logic)
*   based on the Heurist database, allowing a FAIMS project to be built from Heurist
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.5
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

        require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
    require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');
    require_once(dirname(__FILE__)."/../../records/files/fileUtils.php");

    $rt_toexport = @$_REQUEST['frt'];
    $rt_toexport_toplevel = @$_REQUEST['crt'];
    $maprec_toexport = @$_REQUEST['mt'];
    /*
error_log(print_r($rt_toexport, true));
error_log(print_r($rt_toexport_toplevel, true));
error_log(print_r($maprec_toexport, true));
    */
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
  <script type="text/javascript" src="../../external/jquery/jquery.js"></script>
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

<body style="padding:44px;" class="popup">


    <script src="../../common/php/loadCommonInfo.php"></script>

    <div class="banner"><h2>Build FAIMS Project</h2></div>
    <div id="page-inner" style="padding-left: 30px;padding-right:10px">   

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
                    "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice.</div>";
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
    if (! $wg_ids  &&  function_exists('get_user_id')) {

        $mrt = array();
        if(defined('RT_GEOTIFF_LAYER')){
            array_push($mrt, RT_GEOTIFF_LAYER);
        }
        if(defined('RT_SHP_LAYER')){
            array_push($mrt, RT_SHP_LAYER);
        }
        if(defined('RT_KML_LAYER')){
            array_push($mrt, RT_KML_LAYER);
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
        <div style="width: 600px;">
        
            This function writes a set of FAIMS project definition files (db schema, ui schema, ui logic, A16N) to a zip file, 
            based on the record types selected from the Heurist database, along with any required image or map data files. 
            These files can be loaded into the FAIMS server to create a new module.<p/>
        </div>
<?php

        print "<form id='startform' name='startform' action='exportFAIMS.php' method='get'>";
        print "<input id='rt_selected' name='rt' type='hidden'>";
        print "<input name='step' value='1' type='hidden'>";
        print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
        print "<div><div class='lbl_form'>Module name</div><input name='projname' value='".($projname?$projname:HEURIST_DBNAME)."' size='25'></div>";
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
        file_put_contents($folder."/data_schema.xml", generateSchema($projname, $rt_toexport));
        file_put_contents($folder."/ui_schema.xml", generate_UI_Schema($projname, $rt_toexport, $rt_toexport_toplevel, $rt_geoenabled));
        file_put_contents($folder."/arch16N.properties", arrToProps());
        file_put_contents($folder."/ui_logic.bsh", generate_Logic($projname, $rt_toexport, $rt_geoenabled));
        file_put_contents($folder."/style.css", getStyling());
        
        //copy("ui_logic.bsh", $folder."/ui_logic.bsh"); //this is a Java beanshell file which provides the operational  logic for the tablet interface

        //CREATE ZIPFILE
        unlink($folder.".zip");
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
var map_records = <?=json_format($map_records)?>;
$( document ).ready(function() {
    showSelectedRecTypes();
});    
</script>        
</body></html>;
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
    $res = "";
        foreach ($content_a16n as $prop=>$value){
            $res = $res.str_replace(' ','_',$prop)."=".$value."\n";
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
function generateSchema($projname, $rt_toexport){

    global $rtStructs, $dtStructs, $dtTerms, $ind_descr, $supported;
    
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
                error_log("No details defined for RT#".$rt);            
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
                    $dt_pointers = explode(",", dt_pointers);
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
            error_log("1. No details defined for RT#".$rt);            
            continue;
        }

        // Loop through the fields (details)
        
        foreach ($details as $dtid=>$detail) {

            
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

            $det = $dtStructs['typedefs'][$dtid]['commonFields'];
            $ccode = $det[$int_dt_ccode];
            
            $is_in_titlemask = (!( strpos($titlemask, ".".$ccode."]")===false && strpos($titlemask, "[".$ccode."]")===false )) ;
            if($is_in_titlemask){
                $property->addAttribute('isIdentifier', 'true');
                $has_identifier = true;
                $rtStructs['typedefs'][$rt]['dtFields'][$dtid]['isIdentifier'] = true;
            }

            if($dt_type=='enum' || $dt_type=='relationtype'){
                $terms = $detail[$int_rt_termtree];
                //convert to tree
                $cnt = getTermsTree($property, $dt_type, $terms);
                
                $rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'] = $cnt;
            }
        } //for 
        
        // Each ArchEnt in FAIMS has to have at least one attribute (field) marked as an identifier
        if(!$has_identifier){
            addComment($root, 'ERROR!!!!! This ArchaeologicalEntity does not have identifiers!!! Verify TitleMask in Heurist recordtype!'); 
            
        }

    }//foreach recordtype
    
    //specila entity for tracklog
    if(true || $hasControlTracklog){
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
function generate_UI_Schema($projname, $rt_toexport, $rt_toexport_toplevel, $rt_geoenabled){

    global $rtStructs, $dtTerms, $supported;

    $hasControlSync = @$_REQUEST['ct1']=="1"; // This is disabled in the form so will always be on - 
                                              // app hangs while synching so always need to be able to switch it off
    $hasControlInternalGPS = @$_REQUEST['ct2']=="1";
    $hasControlExternalGPS = @$_REQUEST['ct3']=="1";
    $hasControlTracklog = @$_REQUEST['ct4']=="1";
    
    $hasMapTab = @$_REQUEST['mainmt']=="1";
    
    $hasControlGPS = ($hasControlSync ||  $hasControlInternalGPS || $hasControlExternalGPS || $hasControlTracklog);
    
   add16n('Users'); 
   add16n('placeholder');
   add16n('Devices');
   add16n('Start.Recording');
   
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
    $header_user = new SimpleXMLElement('<user><usertab><users/><login/></usertab></user>');
    xml_adopt($faims, $header_user);
    
    //<devices/><login/>
    //$faims->addChild('user')->addChild('usertab')->addChild('users');
    
    
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
                <entityList/>
            </data>');
    xml_adopt($header_control, $header_controldata);
    
    if($hasControlGPS){
        $tab_gps = new SimpleXMLElement('<gps>'
                  .($hasControlInternalGPS?'<connectinternal/>':'')
                  .($hasControlExternalGPS?'<connectexternal/>':'')
                  .($hasControlSync?'<startsync/><stopsync/>':'')
                  .($hasControlTracklog?'<starttrackingtime/><starttrackingdistance/><stoptracking/>':'')
                .'</gps>');
        xml_adopt($header_control, $tab_gps); 
    }


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
    '<group ref="user">
      <label>'.getResStrA16n('User List').'</label>
      <group ref="usertab" faims_scrollable="false">
        <label>'.getResStrA16n('User List').'</label>
        <select1 ref="users">
          <label>{Users}:</label>
          <item>
            <label>placeholder</label>
            <value>placeholder</value>
          </item>
        </select1>
        <trigger ref="login">
          <label>Login</label>
        </trigger>        
      </group>
    </group>');
    xml_adopt($body, $body_user, 'http://www.w3.org/2002/xforms');
//        <select1 appearance="compact" ref="users">

    $body_control = $body->addChild('group','','http://www.w3.org/2002/xforms');
    $body_control->addAttribute('ref', 'control');
    $body_control->addChild('label','');
    
    if($hasMapTab){
    
        foreach ($rt_geoenabled as $rt) {
                   $rtname = $rtStructs['names'][$rt];
                   $rtnamex = getProperName($rtname, $rt);
                   $stypes = $stypes."<item><label>".getResStrA16n(prepareText($rtname))."</label><value>".$rtnamex."</value></item>";
        }
        $map = new SimpleXMLElement('<group faims_scrollable="false" ref="map">
            <label>Map</label>
            <input faims_certainty="false" faims_map="true" ref="map">
              <label/>
            </input>
            <group faims_style="orientation" ref="mapContainer">
              <label/>
              <group faims_style="even" ref="child0">
                <label/>
                <trigger ref="zoomGPS">
                  <label>centerMe</label>
                </trigger>
              </group>          
              <group faims_style="even" ref="child1">
                <label/>
                <trigger ref="clear">
                  <label>Clear</label>
                </trigger>
              </group>
            </group>
            <group faims_style="orientation" ref="mapCreateNew">
                <label/>
                <group faims_style="even" ref="child1">
                <label/>
                <select1 ref="entityTypeList">
                    <label/>'.$stypes.'
                </select1>
                </group>
                <group faims_style="large" ref="child2">
                    <label/>
                    <trigger ref="create">
                        <label>Create</label>
                    </trigger>
                </group>
            </group>            
          </group>');
        xml_adopt($body_control, $map);       
    }
    
    //group for list of rectypes
    $body_controldata = $body_control->addChild('group','','http://www.w3.org/2002/xforms');
    $body_controldata->addAttribute('ref', 'data');
    $body_controldata->addAttribute('faims_scrollable', 'false');
    $body_controldata->addChild('label','All Entities');

     
        //all top level rectyps will be in this list
    $rectypes = $body_controldata->addChild('select1');
    $rectypes->addAttribute('ref', 'entityTypeList');
    $rectypes->addChild('label','Select Entity Type:');
        /*$rectypes = new SimpleXMLElement(
        '<select1 ref="entityTypeList">
          <label>Select record type:</label>
        </select1>');
        xml_adopt($body_controldata, $rectypes); */
        
        // new record button
        $trigger = new SimpleXMLElement('<trigger ref="newEntity"><label>Create New Entity</label></trigger>');
        xml_adopt($body_controldata, $trigger);        
      
        /* @todo search panel
        $searchgroup = new SimpleXMLElement(
        '<group faims_style="orientation" ref="searchpanel">
          <label/>
          <group faims_style="even" ref="child1">
            <label/>
            <input faims_certainty="false" faims_read_only="false" ref="searchInput">
              <label>Search</label>  
            </input>
          </group>
          <group faims_style="large" ref="child2">
            <label/>
            <trigger ref="searchButton">
              <label>Search</label>
            </trigger>
          </group>
        </group>');
        xml_adopt($body_controldata, $searchgroup);        
        */

        $input = new SimpleXMLElement(
        '<select1 appearance="compact" faims_annotation="false" faims_certainty="false" ref="entityList">
          <label>Tap entity in list below to load it</label>
          <item>
            <label>placeholder</label>
            <value>placeholder</value>
          </item>
        </select1>');
        xml_adopt($body_controldata, $input);        
    
    if($hasControlGPS){ // This is the tab labelled Control - not jsut GPS, also includes Sync
        
        $gps = new SimpleXMLElement('<group ref="gps">  
            <label>Control</label>'
            .($hasControlInternalGPS?'
            <trigger ref="connectinternal">
              <label>Connect To Internal GPS</label>
            </trigger>':'')
            .($hasControlExternalGPS?'
            <trigger ref="connectexternal">
              <label>Connect To External GPS</label>
            </trigger>':'')
            .($hasControlSync?'
            <trigger ref="startsync">
              <label>Start Synching</label>
            </trigger>
            <trigger ref="stopsync">
              <label>Stop Synching</label>
            </trigger>':'')
            .($hasControlTracklog?'
            <trigger ref="starttrackingtime">
                <label>Start tracking gps based on time interval</label>
            </trigger>
            <trigger ref="starttrackingdistance">
                <label>Start tracking gps based on distance interval</label>
            </trigger>
            <trigger ref="stoptracking">
                <label>Stop tracking gps</label>
            </trigger>':'')
        .'</group>');        
        xml_adopt($body_control, $gps); 
    }
    

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
            error_log("2. No details defined for RT#".$rt."  ".$rtname);            
            continue;
        }
        
        
//error_log(">>>> ".$rt."  ".in_array($rt, $rt_toexport_toplevel));
        //add to rectypes list on control/data  - this is list of rectypes that can be searched/edit directly from main page
        if( in_array($rt, $rt_toexport_toplevel) ){
                $item = $rectypes->addChild('item');
                $item->label = getResStrA16n(prepareText($rtname));
                $item->value = $rtnamex;
        }
        
        
        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];
        
        $headername = $rtnamex.'_General_Information'; //first tab implied if no header at top of record

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
        $tab_uids->addChild('FAIMS_UID');  
        $group_uids = $body_rectypeform->addChild('group');
        $group_uids->addAttribute('ref', $rtnamex."_uids");
        $group_uids->addAttribute('faims_hidden', 'true');
        $group_uids->addChild('label', 'UIDs');
        
        $input = new SimpleXMLElement(
        '<input ref="FAIMS_UID" faims_attribute_type="freetext" faims_certainty="false" faims_read_only="true">
          <label>UID</label>
        </input>');
        xml_adopt($group_uids, $input);            
        
       
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
                            <label>Save</label>
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
            
            //add field to header
            if($dt_type=='resource'){
                
                $dt_pointers = $detail[$idx_rst_pointers];
                $dt_pointers = explode(",", $dt_pointers);
                if(count($dt_pointers)>0){ //ignore unconstrained
                    
                    //add to uids tab
                    $tab_uids->addChild($dtdisplaynamex.'_UID');
                    $input = new SimpleXMLElement(
                        '<input ref="'.$dtdisplaynamex.'_UID" faims_annotation="false" faims_certainty="false" faims_attribute_name="'.prepareText($dtdisplayname).'" faims_attribute_type="freetext">
                            <label/>
                        </input>');
                    xml_adopt($group_uids, $input);                                        
                
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
                            
                            <select1 ref="'.$dtdisplaynamex.'" faims_annotation="false" faims_certainty="false">
                                <label>'.getResStrA16n( prepareText($detail[$int_rt_disp_name] )).'</label>
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
                
                    /** Browse buttons */                   
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
                
            }else{
                $tab->addChild($dtdisplaynamex); //add to structure
                if($dt_type=='file'){
                       $tab->addChild('attach'.$dtdisplaynamex);
                }
            

                $isvocab = ($dt_type=='enum');
                
                // TO LOOK AT  -  isIdentifier not defined (in logs, 5.20pnm 30/9/13)

                $inputtype = 'input';
                
                if($isvocab){
                    $termsCount = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'];
                    addComment($group, "We would like to see ".($is_repeatable?"Checkbox Group": ($termsCount<4)?"Radiogroup":"Dropdown")  );
                    $inputtype = $is_repeatable?'select':'select1';  //if enum repeatable checkbox list otherwise dropdown
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
                $input->addAttribute('faims_certainty', 'false'); //was before 2014-02-20 $isIdentifier?'false':'true');    
                if($isvocab){
                    $input->addAttribute('faims_annotation','false');
                }
                if($isvocab && !$is_repeatable) {
                    
                    $termsCount = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'];
                    if($termsCount<4){
                        $input->addAttribute('appearance', 'full');
                    }
                    //$input->addAttribute('appearance', ($termsCount>4)?'compact':'full');
                }

                if($isIdentifier){
                    $input->addAttribute('faims_attribute_type', 'freetext');
                }else{
                    $input->addAttribute('faims_attribute_type', $isvocab?'vocab': ($dt_type=='float' || $dt_type=='integer' ?'measure':'freetext'));
                }
                
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
                    $input->addAttribute('faims_annotation', 'false');
                    
                    $trigger = $group->addChild('trigger');
                    $trigger->addAttribute('ref', 'attach'.$dtdisplaynamex);
                    $trigger->addChild('label', $actionlabel);
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

        /** Save, Update, Delete buttons etc. */
        // Trigger group model
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
return 
".button {
    background-color: #1ad;
    color: #fff;
    padding: 0px;
    margin: 2px;
    font-size: 14px;
    text-align: center;
}

.label {
    text-align: left;
    font-size: 18px;
    font-weight: bold;
    padding-top: 15px;
}

.input-field, .text-area {
    background-color: #eee;
    margin: 2px;
    border: 1px solid rgba(0, 0, 0, 0.25);
    text-align: left;
}

.clear-button {
    background-color: #b11; 
    margin: 0px;
    margin-top: 60px;
    padding: 0px; 
    width: 20px;
    height: 20px; 
}

.positive-button {
    background-color: #181;
}

.negative-button {
    background-color: #b11;
}

.neutral-button {
    
}

.browse-button {
    
}


";  
}

function generate_Logic($projname, $rt_toexport, $rt_geoenabled){

    global $rtStructs, $dtTerms, $supported;
    
    $hasControlSync = @$_REQUEST['ct1']=="1";
    $hasControlInternalGPS = @$_REQUEST['ct2']=="1";
    $hasControlExternalGPS = @$_REQUEST['ct3']=="1";
    $hasControlTracklog = @$_REQUEST['ct4']=="1";
    $hasMapTab = @$_REQUEST['mainmt']=="1";
    
    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $int_rt_dt_type = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_Type"];
    
    //not used $int_rt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    //not used $int_rt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];
    $int_dt_repeat = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_MaxValues"];

    $int_rt_disp_name = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DisplayName"];
    
    $idx_rst_pointers = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_PtrFilteredIDs"];    
    $idx_rst_required = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_RequirementType"];
    
    
    $function_section = "";
    $event_section = "";
    $load_selectors = '';
    $load_related = '';
    $rectype_to_entname = '';
    $entname_to_rectype = '';
    $mandatory_check = '';

    // Loop through each of the record types (ArchEntTypes) and output fields
    $rectyps = is_array($rt_toexport) ?$rt_toexport :explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname, $rt);
        
        $details =  $rtStructs['typedefs'][$rt]['dtFields'];
        if(!$details){
            error_log("3. No details defined for RT#".$rt."  ".$rtname);            
            continue;
        }
        
        $rectype_to_entname .=  '
            if("'.$rtnamex.'".equals(rectype)){
                return "'.prepareText($rtname).'";
            }
        ';
        
        $entname_to_rectype .=  '
            if("'.prepareText($rtname).'".equals(entname)){
                return "'.$rtnamex.'";
            }
        ';
        
        
        // Show edit form
        $event_section .= '
onEvent("'.$rtnamex.'", "show", "onShowEditForm(\"'.$rtnamex.'\")");';
        // Enable auto saving
        $event_section .= '
onEvent("'.$rtnamex.'", "show", "saveEntity(\"'.$rtnamex.'\", false, false)");';
        
        $load_related_part = '';
        
        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];
    
        $hasattachfile = false;
        $headername = $rtnamex."/".$rtnamex.'_General_Information'; //first tab implied if no header at top of record
        $headername_uids = $rtnamex."/".$rtnamex.'_uids'; //first tab implied if no header at top of record
        $cnt_pertab = 0;
        $issectab = false;
        $mandatory_attributes = '';
        
        // Output for each field (attribute) in the record type
        foreach ($details as $dtid=>$detail) {

            $is_repeatable = ($detail[$int_dt_repeat]!='1');
            
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
                    
                    $event_section = $event_section.'
onEvent("'.$headername.'/Update", "delayclick", "saveEntity(\"'.$rtnamex.'\", true, false)");';
                    
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
onEvent("'.$headername.'/attach'.$dtdisplaynamex.'", "click", "'.$action.'(\"'.$headername.'/'.$dtdisplaynamex.'\")");';
                
            }else if ($dt_type=='enum') {
                
                $termsCount = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'];
                
                if($is_repeatable){
                        $action = 'populateCheckBoxGroup';
                }else if ($termsCount<4) {
                    $action = 'populateRadioGroup';
                }else{
                    $action = 'populateDropDown';
                }
                
                // makeVocab method
                $makeVocab = 'fetchAll("select vocabid, vocabname from vocabulary join attributekey using (attributeid) where attributename = \'' .prepareText($dtdisplayname). '\'",
                              new FetchCallback() {
                                onFetch(result) {
                                '.
                                    $action.'("'.$headername.'/'.$dtdisplaynamex.'", result);    
                                }
                              });
                              ';
                // Add to load selectors
                $load_selectors .= $makeVocab;
                //$load_selectors .= ''.($action.'("'.$headername.'/'.$dtdisplaynamex.'", makeVocab("'.prepareText($dtdisplayname).'"));'); // OLD
            }else if ($dt_type=='resource') {
                
                $dt_pointers = $detail[$idx_rst_pointers];
                $dt_pointers = explode(",", $dt_pointers);
                if(count($dt_pointers)>0){ //ignore unconstrained
                
                    foreach ($dt_pointers as $rt2) {

                        $rtname2 = $rtStructs['names'][$rt2];
                        $rtnamex2 = getProperName($rtname2, $rt2);
                        
                    $event_section .= ' 
onEvent("'.$headername.'/'.$dtdisplaynamex.'_browse_'.$rtnamex2.'", "click", "startSelectEntity(\"'.$rtnamex2.'\", \"'.$headername.'/'.$dtdisplaynamex.'\")");';
                    }//for

                    $event_section .= ' 
onEvent("'.$headername.'/'.$dtdisplaynamex.'_clearPointer", "click", "clearPointer(\"'.$headername.'/'.$dtdisplaynamex.'\")");';                
                
                    $load_related_part .= '
                    fillPointer("'.$headername.'/'.$dtdisplaynamex.'");';
                    
                    
                    if($detail[$idx_rst_required]=="required"){
                        $mandatory_attributes .= '
                        value = getFieldValue("'.$headername_uids.'/'.$dtdisplaynamex.'_UID");
                        if( null==value || "".equals(value) ){
                            msg = msg + "'.prepareText($dtdisplayname).'\n";
                        }';
                    }
                    
                }
            }
            
            if(!($dt_type=='resource' || $dt_type=='file')){
                    if($detail[$idx_rst_required]=="required"){
                        $mandatory_attributes .= '
                        value = getFieldValue("'.$headername.'/'.$dtdisplaynamex.'");
                        if( null==value || "".equals(value) ){
                            msg = msg + "'.prepareText($dtdisplayname).'\n";
                        }';
                    }
            }
            
            
            
            $cnt_pertab++;
        }//for detail types
        
/* art temp 2014-02-28                   
        if($hasattachfile){  //add events to view attached files
              $eventsection .= '
onEvent("'.$headername.'/viewattached", "click", "viewArchEntAttachedFiles(entityId)");';
        }
*/        

        $mandatory_check .= '
            if("'.$rtnamex.'".equals(rectype)){
                '.$mandatory_attributes.'
            }
        '; 

        if($load_related_part!=''){
                $load_related .= '                
if("'.$rtnamex.'".equals(rectype)){'.$load_related_part.'
    return;
}
';
        }


//event handlers for save/delete
$event_section = $event_section.'
onEvent("'.$headername.'/Update", "delayclick", "saveEntity(\"'.$rtnamex.'\", true, false)");
onEvent("'.$headername.'/UpdateAndClose", "delayclick", "saveEntity(\"'.$rtnamex.'\", true, true)");
onEvent("'.$headername.'/Delete", "delayclick", "deleteEntity(\"'.$rtnamex.'\")");
';
        if( $hasMapTab && in_array($rt, $rt_geoenabled) ){
$event_section = $event_section.'onEvent("'.$headername.'/AttachGeometry", "click", "attachGeometry()");
';
        }

    }//for record types

$function_section  = '
//
// find related entity and fill pointer attrubute with their names
//
loadRelatedEntities(rectype){
    '.$load_related.'
}      

//
// return entity name by tab name
//
getEntityNameByTabname(rectype) {
    '.$rectype_to_entname.
    'return null;
}

//
// get tab name by entity name
//
getTabnameByEntityName(entname) {
    '.$entname_to_rectype.
    'return null;
}

//
//
//
checkMandatoryAttributes(rectype){
    String msg = "";
    '.$mandatory_check.'
    return msg;
}

//
// load all selectors for terms for every rectype 
//
loadAllAttributes(){
    //showToast("load attributes");
    '.$load_selectors.'
}
';
    
//header    
$out = '    
/****** 
 FAIMS Logic File generated by Heurist Vsn '.HEURIST_VERSION.', '. date('l jS \of F Y h:i:s A').'
 Database: '.DATABASE.'   Heurist user:'.get_user_name().'
 ******/

User user;
String userid;

showWarning("Ver 25. Thanks for trying this module!", "This module has been generated from a Heurist database structure. You can customise the module yourself or we can help you. Contact info@fedarch.org for help.");

//stack of tabs in use
ArrayList tabs_edit = new ArrayList(); //list of edit form in use 
ArrayList tabs_select = new ArrayList(); //list of calls to select (see last_invoker)
//keeps rectype to select(browse) and field path to assign the result of selection
// for example Person=DigitalMediaItem/DigitalMediaItem_General_Information/Creators
String last_invoker = null; 
List attachedGeometry = null;

/*** EVENTS ***/
onEvent("control/data", "show", "loadEntityTypeList()");
onEvent("control/data/entityTypeList", "click", "refreshEntities()");
onEvent("user/usertab/login", "click", "login()"); 

onEvent("control/data/entityList", "click", "loadEntity()"); //load entity after tap in the list
onEvent("control/data/newEntity", "click", "newEntity(null)");
//selectEntity tab
onEvent("selectEntity/data", "show", "onShowSelect()");
onEvent("selectEntity/data/entityList", "click", "onSelectEntity()");
onEvent("selectEntity/data/newEntity", "click", "onAddNewEntityWhenSelect()");

/*** START: this section of code depends on the module and entity types selected ***/
'.$event_section.$function_section;    
    
/* misc functions to add */
$out = $out.'
/*** END: this section of code depends on the module and entity types selected ***/

/*** navigation functions ***/
//
// onshow list of entities (to fill pointer field)
//
onShowSelect(){    

    if(tabs_select.size()>0 && last_invoker.equals(tabs_select.get(tabs_select.size()-1))){
        //inital call - open new select
    
    }else{
        //back call - return after new entity addition
        if(tabs_edit.size()>0){
            tabs_edit.remove(tabs_edit.size()-1);
        }
    }
    
    String last_select_callback = tabs_select.get(tabs_select.size()-1);

    String[] parts = last_select_callback.split("=");
    String rectype = parts[0]; //rectype to search
    String fieldpath = parts[1]; //

    //search entity for given entType    
    String entName = getEntityNameByTabname( rectype );
    fetchEntityList(entName, new FetchCallback() {
        onFetch(result) {
            populateList("selectEntity/data/entityList", result);
        }
    });
        
}
//
// onshow edit form for particular entity
//
onShowEditForm(rectype){

    if(tabs_edit.size()>0 && rectype.equals(tabs_edit.get(tabs_edit.size()-1))){
        //return from select
        if(tabs_select.size()>0){
            tabs_select.remove(tabs_select.size()-1);
        }
    }else{
        tabs_edit.add(rectype);
    }
}
/*** selectEntity functions ***/
//
// search for resource for pointer filed - open select tab
// rectype - to search
// fieldpath - field to assign 
//
startSelectEntity(rectype, fieldpath) {

    last_invoker = rectype+"="+fieldpath;

    tabs_select.add(rectype+"="+fieldpath); //keep last call
    showTabGroup("selectEntity");
    
}
//
// create new record from select (in case required resource not found)
//
onAddNewEntityWhenSelect(){
    String last_select_callback = tabs_select.get(tabs_select.size()-1);
    String[] parts = last_select_callback.split("=");
    String rectype = parts[0];
    
    for (String rt : tabs_edit) {
        if (rt.equals(rectype) ) {
            //prevents show edit form in case it is already in use
            showWarning("Already in use", "Can not create new "+rectype+". Save previous");    
            return;
        }
    }    
    
    newTabGroup(rectype); //load and clear fields    
}
//
// user click on record in list - back to edit form and fill pointer field
//
onSelectEntity() {

    String uid = getListItemValue(); //get id from list
    String last_select_callback = tabs_select.get(tabs_select.size()-1);
    
    String[] parts = last_select_callback.split("=");
    String fieldname = parts[1]; //
    parts = fieldname.split("/");
    String rectype = parts[0]; //editing rectype
    String rectype_tab = parts[1];
    String field = parts[2];

    //back to edit form
    cancelTabGroup("selectEntity", false);
    showTab(rectype+"/"+rectype_tab);
    
    //showToast("selected "+uid);
    
    //ArrayList pairs = new ArrayList();
    //pairs.add(new NameValuePair("selected "+uid, uid));

    //fill pointer field    
    populateDropdownByQuery(fieldname, uid);
    
    String fieldname_uid = getPointerFiledName(fieldname);
    setFieldValue(fieldname_uid, uid);    
}
//
// Returns path to UID field on hidden tab for given resource attribute (field)
//
getPointerFiledName(fieldname){
    String[] parts = fieldname.split("/");
    parts[1] = parts[0]+"_uids"; //hidden tab with uid fields
    parts[2] = parts[2]+"_UID";  //pointer field to keep UID of resource record 
    return parts[0]+"/"+parts[1]+"/"+parts[2];
}
//
// Clear resource attribute (field) - dropdown and UID field on hidden tab
//
clearPointer(fieldname){
    ArrayList pairs = new ArrayList();
    pairs.add(new NameValuePair("select with Browse button(s)", ""));
    populateDropDown(fieldname, pairs );
    
    String fieldname_uid = getPointerFiledName(fieldname);
    setFieldValue(fieldname_uid, "");
}
//
// Fill dropdown (name) for pointer field by value of hidden UID field
//
fillPointer(fieldname){
    String fieldname_uid = getPointerFiledName(fieldname);
    String res_id = getFieldValue(fieldname_uid);
    if(null==res_id || "".equals(res_id) || "null".equals(res_id)){
        clearPointer(fieldname);
    }else{
        populateDropdownByQuery(fieldname, res_id);
    }
}

/*** record management function ***/

//
// Create new record for main entity 
// (some entities are not accessible from main tab - they can be added from select tab only)
//
newEntity(rectype){
    
    if (isNull(rectype)){
        entName = getFieldValue("control/data/entityTypeList"); 
        rectype = getTabnameByEntityName(entName);
    }
    
    for (String rt : tabs_edit) {
        if (rt.equals(rectype) ) {
            showWarning("Already in use", "Can not create new "+rectype+". Save previous");    
            return;
        }
    }    
    
    newTabGroup(rectype); //load and clear fields
}
//
// Select record in list - load edit form 
//
loadEntity() {
    String uid = getListItemValue(); //get id from list
    String rectype = getFieldValue("control/data/entityTypeList");
    loadEntityById(uid, rectype);
}
//
// Show edit form by record id and record type
//
loadEntityById(entid, rectype) {
    
    // EntID check
    if (isNull(entid)) return;
    //showToast(entid);
    
    // Show rectype tabgroup
    rectype = getTabnameByEntityName(rectype);
    showTabGroup(rectype, entid);
    setFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID", entid);
    
    // Load related entities
    loadRelatedEntities(rectype); 
    //updateRelns();
}

// Returns the query to load information about a certain entity
getEntityQuery(uid) {
    return  "SELECT uuid, group_concat(coalesce(measure   || \' \'  || vocabname || \'(\'  ||  freetext ||\'; \'|| (certainty * 100.0) || \'% certain)\',  "+
            "                                     measure   || \' (\' || freetext  || \'; \' || (certainty * 100.0) || \'% certain)\',  "+
            "                                     vocabname || \' (\' || freetext  || \'; \' || (certainty * 100.0) || \'% certain)\',  "+
            "                                     measure   || \' \'  || vocabname || \' (\' || (certainty * 100.0) || \'% certain)\',  "+
            "                                       vocabname || \' (\' || freetext  || \')\',  "+
            "                                       measure   || \' (\' || freetext  || \')\',  "+
            "                                     measure   || \' (\' ||(certainty * 100.0) || \'% certain)\',  "+
            "                                     vocabname || \' (\' ||(certainty * 100.0) || \'% certain)\',  "+
            "                                     freetext  || \' (\' ||(certainty * 100.0) || \'% certain)\',  "+
            "                                     measure,  "+
            "                                     vocabname,  "+
            "                                     freetext), \' \') as response  "+

            "FROM (select * from latestNonDeletedArchentIdentifiers order by attributename) "+
            "WHERE uuid = \'"+uid+"\' "+
            "GROUP BY uuid;";
}

// Executes a query for the given uid and then uses the result to populate a dropdown
populateDropdownByQuery(fieldname, uid) {
    fetchAll(getEntityQuery(uid), new FetchCallback() {
        onFetch(result) {
            populateDropDown(fieldname, result);
        }
    });
}

//
// Get entity(record) UID from field on hidden tab
//
getEntityId(rectype){

    String uid = getFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID");
    if("".equals(uid)){
        uid=null;
    }
    return uid;
}

//
// save new entity
//
saveEntity(rectype, checkmandatory, andclose) {
    if(checkmandatory) {
        String msg = checkMandatoryAttributes(rectype);
        if (!(isNull(msg) || "".equals(msg))){ 
            showWarning("Warning", "Please fill in the following mandatory fields:\n"+msg);     
            return;
        }
    }
    
    String uid = getEntityId(rectype);
    if("".equals(uid)){
        uid=null;
    }
    
    data = null;
'.($hasMapTab?'    
    if(!isNull(attachedGeometry)){
         data = attachedGeometry;
         attachedGeometry = null;
         
    }else if (!isNull(uid)) {
        //need for geometry
        fetchArchEnt(uid, new FetchCallback() {
            onFetch(entity) {
                data = entity.getGeometryList();   
            }
        });   
        
    }else if( tabs_edit.size()==1 ){ //add geo for main edit only
        //LIMITATION!!!! map data can be assigned for new entity ONLY!!
        data = getCreatedGeometry(); //map data
    }':'').'
    
    // save record/entity
    saveTabGroup(rectype, uid, data, null, new SaveCallback() {
        onSave(uuid, newRecord) {
            setFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID", uuid);
            onClearMap();
            
            if(checkmandatory) {
                showToast("Successfully saved data!");
            }

            if(andclose){
                goBack();
            }
        }
    }, true);
}

//
// Deleting entities
//
deleteEntity(rectype){
    String uid = getFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID");
    if (!isNull(uid)) {
        showAlert("Confirm Deletion", "Press OK to Delete this entity!", "deleteEntityConfirmed(\""+rectype+"\")", "deleteRecordCanceled()");
    }
}
deleteEntityConfirmed(rectype){
    String uid = getFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID");
    deleteArchEnt(uid);
    cancelTabGroup(rectype, false);
}
deleteEntityCanceled(){
    showToast("Delete Cancelled.");
}       
/*** END record management function ***/

//
// clear edit/select stacks on control/data, control/map show
//
clearStacks(){
   tabs_select.clear();
   tabs_edit.clear();
   last_invoker = null;
}

/*** main list of records on control/data ***/    
//
// loads all available entity types into a dropdown
//
loadEntityTypeList() {
    // Populate entity type dropdown
   fetchAll("select aenttypename, aenttypename from aenttype;", new FetchCallback() {
        onFetch(result) {
            populateDropDown("control/data/entityTypeList", result);  
            refreshEntities();  
        }
   });
}

//
// reload list of records on control/data show or on rectype selection
//
refreshEntities() {

   clearStacks();
   //showToast("Fetching all entities...");

   // Populate list of entity types
   String entName = getFieldValue("control/data/entityTypeList");
   //showToast("Ent name: " + entName);
   
   fetchEntityList(entName, new FetchCallback() {
        onFetch(result) {
            print("Entity list result: " + result);
            populateList("control/data/entityList", result);
        }
   });

}

/*** USER ***/

// Returns a query to select all users
getUserQuery() {
    return "select userid, fname || \' \' || lname from user";
}

// Populates a user dropdown
populateListForUsers(){
    fetchAll(getUserQuery(), new FetchCallback() {
        onFetch(result) {
            populateDropDown("user/usertab/users", result);
        }
    });
}

populateListForUsers();

String username = "";
String device = "";

login(){
    // Fetch user
    fetchOne("select userid,fname,lname,email from user where userid=\'" + getFieldValue("user/usertab/users") + "\';", new FetchCallback() {
        onFetch(result) {
            User user = new User(result.get(0),result.get(1),result.get(2),result.get(3));
            userid = result.get(0);
            setUser(user);
            username = result.get(1) + " " + result.get(2);
            showTabGroup("control");
        }
    });
}

/*** INIT ATTRIBUTES ***/
loadAllAttributes();
';

if($hasControlSync){ // there are start/stop synch controls on the interface
$out = $out.'    
/*** SYNC  ***/
onEvent("control/gps/startsync", "click", "startSync()");
onEvent("control/gps/stopsync", "click", "stopSync()");

setSyncMinInterval(60.0f); // sync every minute
setSyncMaxInterval(120.0f); // max interval 2 minutes
setSyncDelay(10.0f); // increment delay 10 secs each time sync fails up to max

startSync() {
    setSyncEnabled(true);
    setFileSyncEnabled(true);
}

stopSync() {
    setSyncEnabled(false);
    setFileSyncEnabled(false);
}

';    
}else{  // deprecated by Ian 25/3/14: sync hangs the app, so start/stop sync controls will always be enabled
$out = $out.'    
/*** SYNC  ***/
setSyncEnabled(true);
setFileSyncEnabled(true);

';    
}

if($hasControlInternalGPS){
$out = $out.'    
/*** INTERNAL GPS ***/
onEvent("control/gps/connectinternal", "click", "startInternalGPS()");
';
}else{
$out = $out.'    
startInternalGPS();
';
}
 
if($hasControlExternalGPS){
$out = $out.'    
/*** EXTRNAL GPS ***/
onEvent("control/gps/connectexternal", "click", "startExternalGPS()");
';
}

// Feb 2014: Brian says tracklog requires extra logic which is not yet available, so the option
//           to switch this section on has been removed from the export interface
if($hasControlTracklog){
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

if($hasMapTab){
$out = $out.'
/*** MAP ***/

DATA_ENTRY_LAYER = "Data Entry Layer";
DATA_ENTRY_LAYER_ID = 0;

onEvent("control/map", "show", "clearStacks()");

//mapping tool to load entity by selected feature on map
onToolEvent("control/map/map", "load", "onLoadData()");

onLoadData() {
    uuid = getMapGeometryLoaded();
    isEntity = "entity".equals(getMapGeometryLoadedType());
    
    if (isEntity) {
        // Fetch entity
        fetchOne("select aenttypename from aenttype join latestnondeletedarchent using (aenttypeid) where uuid = "+uuid+";", new FetchCallback() {
            onFetch(entity) {
                 String entname = entity.get(0);
                 String rectype = getTabnameByEntityName(entname);
                 
                 loadEntityById(uuid, rectype);
            }
        });
               
    } else {
        //to implement loadUIRelationship(uuid);
    }
}

onEvent("control/map/clear", "click", "onClearMap()");
onEvent("control/map/create", "click", "onCreateMap()");

onClearMap() {
    clearGeometryList("control/map/map", getCreatedGeometry() );
}

onCreateMap() {
    List geomList = getCreatedGeometry();
    if (geomList == null || geomList.size() == 0) {
        showWarning("Logic Error", "No geometry found on data entry layer");
    } else if (geomList.size() > 1) {
        showWarning("Logic Error", "Multiple geometry found on data entry layer. Please clear data entry layer and try again");
    } else {
        Geometry geom = geomList.get(0);
        /*if (geom instanceof Point) {
            showTabGroup("createPoint");
        } else if (geom instanceof Line) {
            showTabGroup("createLine");
        } else if (geom instanceof Polygon) {
            showTabGroup("createPolygon");
        }*/
        
        String rectype = getFieldValue("control/map/entityTypeList");
        newEntity(rectype);        
    }
}

getCreatedGeometry() {
    return getGeometryList("control/map/map", DATA_ENTRY_LAYER_ID);
}

attachGeometry(){
    List geomList = getCreatedGeometry();
    if (geomList == null || geomList.size() == 0) {
        showWarning("Logic Error", "No geometry found on data entry layer");
    } else if (geomList.size() > 1) {
        showWarning("Logic Error", "Multiple geometry found on data entry layer. Please clear data entry layer and try again");
    } else {
        attachedGeometry = geomList;
        showToast("Geometry attached. Now you may save Entity");
    }
}

zoomGPS(){
    Object position = getGPSPositionProjected();
    Object projPosition = getGPSPositionProjected();
    if (projPosition != null ){
        Double latitude = projPosition.getLatitude();
        Double longitude = projPosition.getLongitude();
        showToast("Zooming to "+position.getLongitude()+", "+position.getLatitude());
        setMapFocusPoint("control/map/map", longitude, latitude);
    } else {
        showToast("GPS Not initialized");
    }

}
onEvent("control/map/zoomGPS", "click", "zoomGPS()");

//
// @todo - show map tab and zoom to current record
//
mapRecord(rectype){

    String uid = getEntityId(rectype);
    if (!isNull(uid)) { 
        // Fetch entity
        fetchArchEnt(uid, new FetchCallback() {
            onFetch(entity) {
                data = entity.getGeometryList();    
                //setMapFocusPoint("control/map/map", longitude, latitude);
            }
        }); 
        
    }
}

initMap() {
    setMapZoom("control/map/map", 19.0f);

    //showBaseMap("control/map/map", "Base Layer", "files/data/maps/ZAG-TPan-3857-grass-tiled.tif");
    createCanvasLayer("control/map/map", "Non-saved sketch layer");

    DATA_ENTRY_LAYER_ID = createCanvasLayer("control/map/map", DATA_ENTRY_LAYER);

    isEntity = true;
    queryName = "All entities";
    querySQL = "SELECT uuid, aenttimestamp FROM latestNonDeletedArchEntIdentifiers";
        
    addDatabaseLayerQuery("control/map/map", queryName, querySQL);
'.($hasControlTracklog?'
    addTrackLogLayerQuery("control/map/map", "track log entities", 
        "SELECT uuid, max(aenttimestamp) as aenttimestamp\n" + 
        " FROM archentity join aenttype using (aenttypeid)\n" +
        " where archentity.deleted is null\n" + 
        "   and lower(aenttypename) = lower(\'gps_track\')\n" + 
        " group by uuid\n" + 
        " having max(aenttimestamp)");':'').'
        
    addSelectQueryBuilder("control/map/map", "Select entity by type", createQueryBuilder(
        "select uuid\n" + 
        "  from latestNonDeletedArchent\n" + 
        "  JOIN latestNonDeletedAentValue using (uuid)\n" + 
        "  join aenttype using (aenttypeid)\n" + 
        "  LEFT OUTER JOIN vocabulary using (vocabid, attributeid) \n" + 
        "  where lower(aenttypename) = lower(?) \n" + 
        "   group by uuid").addParameter("Type", "RemoteSensingFeature"));
        
                    
    // define database layer styles for points, lines, polygons and text
    ps = createPointStyle(10, Color.BLUE, 0.2f, 0.5f);
    ls = createLineStyle(10, Color.GREEN, 0.05f, 0.3f, null);
    pos = createPolygonStyle(10, Color.parseColor("#440000FF"), createLineStyle(10, Color.parseColor("#AA000000"), 0.01f, 0.3f, null));
    ts = createTextStyle(10, Color.WHITE, 40, Typeface.SANS_SERIF);

    showDatabaseLayer("control/map/map", "Saved Data Layer", isEntity, queryName, querySQL, ps, ls, pos, ts);
}

initMap();

';
}else{
    
$out = $out.'
/*** MAP STUB ***/ 
onClearMap() {}
';   
}    

/* @todo???
    addDatabaseLayerQuery("control/map/map", "Digital Media Item", querySQL+" where lower(aenttypename) = lower('Digital Media Item')");
    addDatabaseLayerQuery("control/map/map", "Organisation", querySQL+" where lower(aenttypename) = lower('Organisation')");
    addDatabaseLayerQuery("control/map/map", "Person", querySQL+" where lower(aenttypename) = lower('Person')");
*/

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
        return $bettername;

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
    return $str;
}

function prepareText2($str){
//error_log($str);
    $str = prepareText($str);
    $str = mysql_real_escape_string($str);
//error_log(">>>>".$str."<<<<");    
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


//  return count
// for UI_Schema
// 
function getTermsTree($property, $datatype, $terms){

    global $dtTerms;

    if($datatype == "relmarker" || $datatype === "relationtype"){
        $datatype = "relation";
    }else if(!($datatype=="enum" || $datatype=="relation")){
        return 0;
    }

    if(is_numeric($terms)){
        $termTree =  $dtTerms['treesByDomain'][$datatype][$terms];
    }else{
        $termTree = json_decode($terms);
    }
    if(count($termTree)<1){
        return 0;
    }

    $parent = $property->addChild('lookup');

    return createSubTree($parent, $datatype, $termTree, "");
}

function createSubTree($parent, $datatype, $termTree, $parentname){

    global $dtTerms, $ind_label;
    $termLookup = $dtTerms['termsByDomainLookup'][$datatype];
    $ind_label = $dtTerms['fieldNamesToIndex']['trm_Label'];
    $ind_descr = $dtTerms['fieldNamesToIndex']['trm_Description'];
    $ind_ccode = $dtTerms['fieldNamesToIndex']['trm_ConceptID'];
    $ind_tcode = $dtTerms['fieldNamesToIndex']['trm_Code'];
    
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
            
            $cnt = $cnt + createSubTree($term, $datatype, $child_terms, $parentname.$termName."-");
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


?>