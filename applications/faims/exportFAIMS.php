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

    $rt_toexport_toplevel = @$_REQUEST['crt'];
    $rt_toexport = @$_REQUEST['frt'];
    
//error_log(print_r($rt_toexport, true));
error_log(print_r($rt_toexport_toplevel, true));
    
    $projname = @$_REQUEST['projname'];
    $step = @$_REQUEST['step'];
    
    $arc_filename = HEURIST_UPLOAD_DIR."faims/new/".$projname.".zip";
    
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
    <div id="page-inner" style="width:80%; margin:0px 5%; padding: 0.5em;">   

<script>
$( document ).ready(function() {
    showSelectedRecTypes();
});    
</script>        
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
        
                $res = mysql_query("select sys_dbRegisteredID, sys_dbName, sys_dbDescription, sys_OwnerGroupID from sysIdentification where `sys_ID`='1'");
                if (!$res) { // Problem reading current registration ID
                    print "<div class='err_message'>Unable to read database identification record, this database might be incorrectly set up. \n" .
                    "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice.</div>";
                }
                $row = mysql_fetch_row($res); // Get system information for current database
                $dbID = $row[0];
                if ( !isset($dbID) || ($dbID <1)) { // not registered
                    print "<div class='err_message'>This database has not been registered with the Heurist network. We encourage registration before proceeding to ensure that the record types, fields and terms in your database have globally unique identifiers.
To register click Database > Register in the menu on the left</div>";
                }        
    }
    
        print "<div>This function writes a set of FAIMS project definition files ".
        "(db schema, ui schema, ui logic, A16N) to a zip file, based on the record types ".
        "selected from the Heurist database. <p/>These files can be loaded into ".
        "the FAIMS server to create a new module.<p/></div>";

        print "<form name='startform' action='exportFAIMS.php' method='get'>";
        print "<input id='rt_selected' name='rt' type='hidden'>";
        print "<input name='step' value='1' type='hidden'>";
        print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
        print "<div><div class='lbl_form'>Project name:</div><input name='projname' value='".($projname?$projname:HEURIST_DBNAME)."' size='25'></div>";

        $rt_invalid_masks = array();
        if($rt_toexport){
            //validate title masks
            $rtIDs = mysql__select_assoc("defRecTypes","rty_ID","rty_Name"," rty_ID in (".implode(",",$rt_toexport).") order by rty_ID");
            foreach ($rtIDs as $rtID => $rtName) {
                $mask= mysql__select_array("defRecTypes","rty_TitleMask","rty_ID=$rtID");
                $mask=$mask[0];
                $res = titlemask_make($mask, $rtID, 2, null, _ERR_REP_MSG); //get human readable
                if(is_array($res)){ //invalid mask
                    array_push($rt_invalid_masks, $rtName);
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
        
        // List of record types for export
        print "<div id='selectedRectypes' style='width:100%;color:black;'></div>";

        if(count($rt_invalid_masks)>0){
            print "<p style='color:red'>You have invalid title masks in the following record types: <b>"
            .implode(",",$rt_invalid_masks)."</b>
 FAIMS requires setting of at least one field (attrribute) as an identifier, which is based  on the fields in the title mask. Please correct the title mask(s) before proceeding (edit the record type in Database Designer > Essentials > Manage RecordTypes/Fields)</p>";
        }
 
        // Don't show Start Export button until record types have been selected       
        print "<div id='buttondiv' style='display:".(($rt_toexport && $step!='1')?"block":"none")."'><div class='lbl_form'></div><input type='submit' value='Start Export' /></div>";

        print "</form>";
        /*art 2014-02-24 if($rt_toexport){
            print "<script>showSelectedRecTypes('".$rt_toexport."')</script>";
        }*/

    if( $rt_toexport && !$invalid ){
        
        $folder = HEURIST_UPLOAD_DIR."faims/new/".$projname;

        //create export folder
        if(!file_exists($folder)){
            if (!mkdir($folder, 0777, true)) {
                    die('Failed to create folder for '.$folder.' - check that file permissions are correctly set');
            }
        }else{ //clear folder
            delFolderTree($folder, false);
        }

        //load definitions (USE CACHE)
        $rtStructs = getAllRectypeStructures(false);
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
        file_put_contents($folder."/ui_schema.xml", generate_UI_Schema($projname, $rt_toexport, $rt_toexport_toplevel));
        file_put_contents($folder."/arch16N.properties", arrToProps());
        file_put_contents($folder."/ui_logic.bsh", generate_Logic($projname, $rt_toexport));
        
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
            print "<br><br><div class='lbl_form'></div>".
            "<a href='exportFAIMS.php/$projname.zip?db=".HEURIST_DBNAME."&step=2&projname=".$projname."' target='_blank' style='color:blue; font-size:1.2em'>Click here to download FAIMS project definitions zip archive</a>";
        }

    }else{
        print "</div></body></html>";
    }

/*
Since PHP 5.3
$phar = new PharData('project.tar');
// add all files in the project
$phar->buildFromDirectory(dirname(__FILE__) . '/project');

Or use pear install Archive_Tar
http://stackoverflow.com/questions/333130/build-tar-file-from-directory-in-php-without-exec-passthru
*/

function getResStrA16n($str, $val=null){
    add16n($str, $val);
    return "{".str_replace(' ','_',$str)."}";
}

function getResStrNoA16n($str, $val=null){
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
        $content_a16n[$line] = $val?$val:$line;
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
                        $dtdisplaynamex = getProperName($dtdisplayname);
                        if($dtdisplaynamex==""){
                               $dtdisplaynamex = "Rectype".$rt."_Field".$dtid;
                        }

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
                    $termDesc = prepareText("Pointer to resource");
                    
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
function generate_UI_Schema($projname, $rt_toexport, $rt_toexport_toplevel){

    global $rtStructs, $dtTerms, $supported;

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
    
    // ARTEM: THIS SECTION WAS BELOW THE BODY SECTION, MOVED UP HERE 6.30PM 30/9/13
         
    addComment($faims, 'control tabgroup that contains button to manipulate entities: load, list, create');

   
    $header_control = $faims->addChild('control');   //$controlgroup in body
    
    $header_controldata = new SimpleXMLElement('
            <data>
                <rectypeList/>
                <newRecord/>
                <recordList/>
            </data>');
    xml_adopt($header_control, $header_controldata);

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
    addComment($faims, 'select record for given record type');
    $header_selectres = new SimpleXMLElement('
          <selectresource>
            <data>
              <newRecord/>
              <recordList/>
            </data>
          </selectresource>');
    xml_adopt($faims, $header_selectres);
 
/* ART temorary removed    
    $tab_map = new SimpleXMLElement(
             '<map>
                  <map/>
                  <mapContainer>
                      <child0>
                        <zoomGPS/>
                      </child0>
                      <child1>
                        <clear/>
                      </child1>
                      <child2>
                        <create/>
                      </child2>
                  </mapContainer>
              </map>');
    xml_adopt($header_control, $tab_map);
     
    
    $tab_gps = new SimpleXMLElement('<gps>
              <connectexternal/>
              <connectinternal/>
              <startsync/>
              <stopsync/>
              <startTimeLog/>
              <startDistanceLog/>
              <stopTrackLog/>
            </gps>');
    xml_adopt($header_control, $tab_gps);
*/
      
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
    
    //group for list of rectypes
    $body_controldata = $body_control->addChild('group','','http://www.w3.org/2002/xforms');
    $body_controldata->addAttribute('ref', 'data');
    $body_controldata->addAttribute('faims_scrollable', 'false');
    $body_controldata->addChild('label','All Entities');

     
        //all top level rectyps will be in this list
    $rectypes = $body_controldata->addChild('select1');
    $rectypes->addAttribute('ref', 'rectypeList');
    $rectypes->addChild('label','Select entity type:');
        /*$rectypes = new SimpleXMLElement(
        '<select1 ref="rectypeList">
          <label>Select record type:</label>
        </select1>');
        xml_adopt($body_controldata, $rectypes); */
        
        // new record button
        $trigger = new SimpleXMLElement('<trigger ref="newRecord"><label>Create New Entity</label></trigger>');
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
        '<select1 appearance="compact" faims_annotation="false" faims_certainty="false" ref="recordList">
          <label>Tap entity in list below to load it</label>
          <item>
            <label>placeholder</label>
            <value>placeholder</value>
          </item>
        </select1>');
        xml_adopt($body_controldata, $input);        
    

        $select_resource = new SimpleXMLElement(
        '<group ref="selectresource">
              <label/>
              <group ref="data" faims_scrollable="false">
                <label>Select entity or create new one</label>
                <trigger ref="newRecord">
                  <label>Create New Entity</label>
                </trigger>
                <select1 appearance="compact" faims_annotation="false" faims_certainty="false" ref="recordList">
                  <label>Tap entity in list below to select it</label>
                  <item>
                    <label>placeholder</label>
                    <value>placeholder</value>
                  </item>
                </select1>
              </group>
        </group>');
        xml_adopt($body, $select_resource, 'http://www.w3.org/2002/xforms');
    
  
    // Maps and controls ADDED BY IAN
/* ART temporary removed    
     $map = new SimpleXMLElement(
        '<group faims_scrollable="false" ref="map">
            <label>Map</label>
            <input faims_certainty="false" faims_map="true" ref="map">
                <label/>
            </input>                                      
        
            <group faims_style="orientation" ref="mapContainer">
              <label/>
              <group faims_style="even" ref="child0">
                <label/>
                <trigger ref="zoomGPS">
                  <label>CenterMe</label>
                </trigger>
              </group>
              <group faims_style="even" ref="child1">
                <label/>
                <trigger ref="clear">
                  <label>Remove</label>
                </trigger>
              </group>
              <group faims_style="even" ref="child2">
                <label/>
                <trigger ref="create">
                  <label>Create</label>
                </trigger>
              </group>
            </group>
        </group>');
     xml_adopt($body_control, $map, 'http://www.w3.org/2002/xforms');
     
     $gps = new SimpleXMLElement('<group ref="gps">  
            <label>Control</label>
            <trigger ref="connectexternal">
              <label>Connect To External GPS</label>
            </trigger>
            <trigger ref="connectinternal">
              <label>Connect To Internal GPS</label>
            </trigger>
            <trigger ref="startsync">
              <label>Start Synching</label>
            </trigger>
            <trigger ref="stopsync">
              <label>Stop Synching</label>
            </trigger>
            <trigger ref="startTimeLog">
              <label>Start Time Log</label>
            </trigger>
            <trigger ref="startDistanceLog">
              <label>Start Distance Log</label>
            </trigger>
            <trigger ref="stopTrackLog">
              <label>Stop Track Log</label>
            </trigger>
        </group>');
     xml_adopt($body_control, $gps, 'http://www.w3.org/2002/xforms');
*/    
    // Data ADDED BY IAN
    /*
     $data = new SimpleXMLElement(
     '<group ref="data" faims_scrollable="false">
        <label>Saved Features</label>
        <select1 faims_annotation="false" faims_certainty="false" ref="RS_FeatureList" appearance="compact">
          <label>Saved Remote Sensing Features</label>
          <item>
            <label>placeholder</label>
            <value>placeholder</value>
          </item>
        </select1>
      </group>');
    xml_adopt($body, $data, 'http://www.w3.org/2002/xforms');
  
        
    // GPS ADDED BY IAN (Artem: it was added into wrong place)
     $gps = new SimpleXMLElement(
     '<group ref="gps">  
        <label>Control</label>
        <trigger ref="connectexternal">
          <label>Connect To External GPS</label>
        </trigger>
        <trigger ref="connectinternal">
          <label>Connect To Internal GPS</label>
        </trigger>
        <trigger ref="startsync">
          <label>Start Synching</label>
        </trigger>
        <trigger ref="stopsync">
          <label>Stop Synching</label>
        </trigger>
        <trigger ref="startTimeLog">
          <label>Start Time Log</label>
        </trigger>
        <trigger ref="startDistanceLog">
          <label>Start Distance Log</label>
        </trigger>
        <trigger ref="stopTrackLog">
          <label>Stop Track Log</label>
        </trigger>
      </group>');
    xml_adopt($body, $gps, 'http://www.w3.org/2002/xforms');   
   */   

    // ARTEM, CAN WE DELETE THIS STUFF, OR ELSE ADD COMMENTS WHAT IT'S FOR AND WHY IT HAS BEEN COMMENTED OUT
    
    /*
        <select1 ref="devices" appearance="full">
          <label>{Devices}:</label>
          <item>
            <label>{dummy}</label>
            <value>dummy</value>
          </item>
        </select1>
        <trigger ref="login">
          <label>{Start.Recording}</label>
        </trigger>
*/    
    
    /*
    $usergroup = $body->addChild('group','','http://www.w3.org/2002/xforms');
    $usergroup->addChild('label','User List');
    $usergroup = $usergroup->addChild('group');
    $usergroup->addAttribute('ref', 'users');
    $usergroup->addAttribute('faims_scrollable', 'false');
    $usergroup->addChild('label','User List');

    $userselect = $usergroup->addChild('select1');
    $userselect->addAttribute('ref', 'users');
    $userselect->addAttribute('appearance', 'compact');
    $userselect->addChild('label','{Users}:');
    $item = $userselect->addChild('item');
    $item->label = '{dummy}';
    $item->value = 'dummy'; */

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
    addComment($faims, 'list of tabgroups for rectypes(archentities)');
    
    $uniqcnt = 1;
    // Loop through each of the record types (ArchEntTypes) and output fields
    $rectyps = is_array($rt_toexport)?$rt_toexport:explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname);
        if($rtnamex==""){
               $rtnamex = "Rectype".$rt;
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

        addComment($faims, 'ArchEnt / Record Type: '.mb_strtoupper($rtname) );        
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
        
        //
        $details =  $rtStructs['typedefs'][$rt]['dtFields'];
        
        $hasattachfile = false;
        
        $contaier_no = 1; //container for resource field type
        
        $cnt_pertab = 0;
        $issectab = false;

        // Output for each field (attribute) in the record type
        foreach ($details as $dtid=>$detail) {

            $is_repeatable = ($detail[$int_rt_repeat]!='1');
            
            $dt_type = $detail[$int_rt_dt_type];
            
            $dtdisplayname = $detail[$int_rt_disp_name]; // the display name for this field
            $dtdisplaynamex = getProperName($dtdisplayname);
            if($dtdisplaynamex==""){
                   $dtdisplaynamex = "Rectype".$rt."_Field".$dtid;
            }
            
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
                      '<container'.$contaier_no.'>
                        <child1>
                          <'.$dtdisplaynamex.'/>
                        </child1>
                        <child2>
                          <'.$dtdisplaynamex.'_clearPointer/>
                        </child2>
                       </container'.$contaier_no.'>');
                    xml_adopt($tab, $container);                    
                    
                    //add to body/group/tab
                    $container = new SimpleXMLElement(
                        '<group faims_style="orientation" ref="container'.$contaier_no.'">
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
                            <trigger ref="'.$dtdisplaynamex.'_clearPointer">
                              <label>Clear</label>
                            </trigger>
                          </group>
                        </group>');
                    xml_adopt($group, $container);                    
                
                    foreach ($dt_pointers as $rt2) {

                        $rtname2 = $rtStructs['names'][$rt2];
                        $rtnamex2 = getProperName($rtname2);
                        if($rtnamex2==""){
                            $rtnamex2 = "Rectype".$rt2;
                        }
                        
                        $tab->addChild($dtdisplaynamex.'_browse_'.$rtnamex2);
                        
                        //@todo - specify the exact name of resource - pointer record type
                        $trigger = new SimpleXMLElement(
                           '<trigger ref="'.$dtdisplaynamex.'_browse_'.$rtnamex2.'">
                                <label>'.getResStrA16n('Browse '.prepareText($rtname2)).'</label>
                            </trigger>'
                        );
                        xml_adopt($group, $trigger);
                    }//for
                    
                    $contaier_no++;
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
                
                
                //TODO  - support pointer, relmarker            
                if(false && $dt_type=='resource'){
                
                }else if($dt_type=='file'){
                    
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
        
        //add save/delete buttons to last tab
        $tab->addChild('Update');
        //$tab->addChild('Update2');
        $tab->addChild('Delete');
        
        $trigger = $group->addChild('trigger');
        $trigger->addAttribute('ref', 'Update');
        $trigger->addChild('label', getResStrA16n('Save Record') );
        $trigger = $group->addChild('trigger');
        $trigger->addAttribute('ref', 'Delete');
        $trigger->addChild('label', getResStrA16n('Delete Record') );
        

    }//foreach
    
    return prepareXml($root);
    
} // generation of user interface schema


function generate_Logic($projname, $rt_toexport){

    global $rtStructs, $dtTerms, $supported;
    
    
    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $int_rt_dt_type = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_Type"];
    
    //not used $int_rt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    //not used $int_rt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];
    $int_dt_repeat = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_MaxValues"];

    $int_rt_disp_name = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DisplayName"];
    
    $idx_rst_pointers = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_PtrFilteredIDs"];    
    
    
    $function_section = "";
    $event_section = "";
    $load_selectors = '';
    $load_related = '';
    $rectype_to_entname = '';

    // Loop through each of the record types (ArchEntTypes) and output fields
    $rectyps = is_array($rt_toexport) ?$rt_toexport :explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname);
        if($rtnamex==""){
               $rtnamex = "Rectype".$rt;
        }
        
        $rectype_to_entname .=  '
            if("'.$rtnamex.'".equals(rectype)){
                return "'.prepareText($rtname).'";
            }
        ';
        
                $event_section .= '
onEvent("'.$rtnamex.'", "show", "onShowEditForm(\"'.$rtnamex.'\")");';
        
        $load_related_part = '';
        
        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];
    
        $details =  $rtStructs['typedefs'][$rt]['dtFields'];
        
        $hasattachfile = false;
        $headername = $rtnamex."/".$rtnamex.'_General_Information'; //first tab implied if no header at top of record
        $headername_uids = $rtnamex."/".$rtnamex.'_uids'; //first tab implied if no header at top of record
        $cnt_pertab = 0;
        $issectab = false;

        // Output for each field (attribute) in the record type
        foreach ($details as $dtid=>$detail) {

            $is_repeatable = ($detail[$int_dt_repeat]!='1');
            
            $dt_type = $detail[$int_rt_dt_type];
            
            $dtdisplayname = $detail[$int_rt_disp_name]; // the display name for this record type
            $dtdisplaynamex = getProperName($dtdisplayname);
            if($dtdisplaynamex==""){
                   $dtdisplaynamex = "Rectype".$rt."_Field".$dtid;
            }

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
                        //Object locations = fetchAll("select vocabid, vocabname from vocabulary left join attributekey using (attributeid) where attributename = 'location';");
                        //$action = populateCheckBoxGroup("tabgroup1/tab1/locations",locations);
                        
                }else if ($termsCount<4) {
                    $action = 'populateRadioGroup';
                }else{
                    $action = 'populateDropDown';
                }
                
                $load_selectors .= '
'.($action.'("'.$headername.'/'.$dtdisplaynamex.'", makeVocab("'.prepareText($dtdisplayname).'"));');
            }else if ($dt_type=='resource') {
                
                $dt_pointers = $detail[$idx_rst_pointers];
                $dt_pointers = explode(",", $dt_pointers);
                if(count($dt_pointers)>0){ //ignore unconstrained
                
                    foreach ($dt_pointers as $rt2) {

                        $rtname2 = $rtStructs['names'][$rt2];
                        $rtnamex2 = getProperName($rtname2);
                        if($rtnamex2==""){
                            $rtnamex2 = "Rectype".$rt2;
                        }
                    $event_section .= ' 
onEvent("'.$headername.'/'.$dtdisplaynamex.'_browse_'.$rtnamex2.'", "click", "browseRecord(\"'.$rtnamex2.'\", \"'.$headername.'/'.$dtdisplaynamex.'\")");';
                    }//for

                    $event_section .= ' 
onEvent("'.$headername.'/'.$dtdisplaynamex.'_clearPointer", "click", "clearPointer(\"'.$headername.'/'.$dtdisplaynamex.'\")");';                
                
                    $load_related_part .= '
                    fillPointer("'.$headername.'/'.$dtdisplaynamex.'");';
                }
            }     
            //TODO  - support pointer, relmarker       
            
            $cnt_pertab++;
        }//for detail types
        
/* art temp 2014-02-28                   
        if($hasattachfile){  //add events to view attached files
              $eventsection .= '
onEvent("'.$headername.'/viewattached", "click", "viewArchEntAttachedFiles(entityId)");';
        }
*/        

        if($load_related_part!=''){
                $load_related .= '                
if("'.$rtnamex.'".equals(rectype)){'.$load_related_part.'
    return;
}
';
        }


//event handlers for save/delete
$event_section = $event_section.'
onEvent("'.$headername.'/Update", "delayclick", "saveRecord(\"'.$rtnamex.'\")");
onEvent("'.$headername.'/Delete", "delayclick", "deleteRecord(\"'.$rtnamex.'\")");
';

    }//for record types

$function_section  = '
loadRelatedRecords(rectype){
    '.$load_related.'
}      

getEntityNameByRectype(rectype) {
    '.$rectype_to_entname.
    'return null;
}

//loop for each enum field for every rectype 
loadAllAttributes(){
    showToast("load attributes");
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

showWarning("Ver 15. Thanks for trying this module!", "This module has been generated from a Heurist database structure. You can customise the module yourself or we can help you. Contact info@fedarch.org for help.");

//stack of tabs in use
ArrayList tabs_edit = new ArrayList();
ArrayList tabs_select = new ArrayList();

/*** EVENTS ***/
onEvent("control/data", "show", "refreshEntities()");
onEvent("control/data/rectypeList", "click", "refreshEntities()");
onEvent("user/usertab/login", "click", "login()"); 

onEvent("control/data/recordList", "click", "loadRecord()"); //load particular record
onEvent("control/data/newRecord", "click", "newRecord()");
//selectresource tab
onEvent("selectresource/data", "show", "onShowSelect()");
onEvent("selectresource/data/recordList", "click", "onSelectRecord()");
onEvent("selectresource/data/newRecord", "click", "onNewRecordInSelect()");

/*** START: this section of code depends on the module and record/entity types selected ***/
'.$event_section.$function_section;    
    
/* misc functions to add */
$out = $out.'
/*** END: this section of code depends on the module and record/entity types selected ***/

/*** navigation functions ***/
String last_invoker = null; //keep field path
//
onShowSelect(){    

    if(tabs_select.size()>0 && last_invoker.equals(tabs_select.get(tabs_select.size()-1))){
        //open new select
    
    }else{
        //return from new record
        if(tabs_edit.size()>0){
            tabs_edit.remove(tabs_edit.size()-1);
        }
    }
    
        String last_select_callback = tabs_select.get(tabs_select.size()-1);
    
        String[] parts = last_select_callback.split("=");
        String rectype = parts[0]; //rectype to search
        String fieldpath = parts[1]; //
    
        //search record for given rectype    
        String entName = getEntityNameByRectype( rectype );
        populateList("selectresource/data/recordList", fetchEntityList(entName));
    
}
//
onShowEditForm(rectype){

    if(tabs_edit.size()>0 && rectype.equals(tabs_edit.get(tabs_edit.size()-1))){
        //return from select
        if(tabs_select.size()>0){
            //last_invoker = tabs_select.get(tabs_select.size()-1);
            tabs_select.remove(tabs_select.size()-1);
        }
    }else{
        tabs_edit.add(rectype);
    }
}
/*** selectresource functions ***/
// rectype - to search
// fieldpath - field to assign 
browseRecord(rectype, fieldpath) {

    last_invoker = rectype+"="+fieldpath;

    tabs_select.add(rectype+"="+fieldpath); //keep last call
    showTabGroup("selectresource");
    
}
//
onNewRecordInSelect(){
    String last_select_callback = tabs_select.get(tabs_select.size()-1);
    String[] parts = last_select_callback.split("=");
    String rectype = parts[0];
    
    for (String rt : tabs_edit) {
        if (rt.equals(rectype) ) {
            showWarning("Already in use", "Can not create new "+rectype+". Save previous");    
            return;
        }
    }    
    
    newTabGroup(rectype); //load and clear fields    
}
//
onSelectRecord() {

    String record_id = getListItemValue(); //get id from list
    String last_select_callback = tabs_select.get(tabs_select.size()-1);
    
    String[] parts = last_select_callback.split("=");
    String fieldname = parts[1]; //
    parts = fieldname.split("/");
    String rectype = parts[0]; //editing rectype
    String rectype_tab = parts[1];
    String field = parts[2];

    //back to edit form
    cancelTabGroup("selectresource", false);
    showTab(rectype+"/"+rectype_tab);
    
    showToast("selected "+record_id);
    
    //ArrayList pairs = new ArrayList();
    //pairs.add(new NameValuePair("selected "+record_id, record_id));
    
    populateDropDown(fieldname, loadEntity(record_id) );
    
    String fieldname_uid = getPointerFiledName(fieldname);
    setFieldValue(fieldname_uid, record_id);    
}
//
getPointerFiledName(fieldname){
    String[] parts = fieldname.split("/");
    parts[1] = parts[0]+"_uids";
    parts[2] = parts[2]+"_UID";
    return parts[0]+"/"+parts[1]+"/"+parts[2];
}
//
clearPointer(fieldname){
    ArrayList pairs = new ArrayList();
    pairs.add(new NameValuePair("browse for resource record", ""));
    populateDropDown(fieldname, pairs );
    
    String fieldname_uid = getPointerFiledName(fieldname);
    setFieldValue(fieldname_uid, "");
}
//fill pointer field
fillPointer(fieldname){
    String fieldname_uid = getPointerFiledName(fieldname);
    String res_id = getFieldValue(fieldname_uid);
    if(null==res_id || "".equals(res_id) || "null".equals(res_id)){
        clearPointer(fieldname);
    }else{
        populateDropDown(fieldname, loadEntity(res_id));
    }
}

/*** record management function ***/
newRecord(){
    String rectype = getFieldValue("control/data/rectypeList");
    
    for (String rt : tabs_edit) {
        if (rt.equals(rectype) ) {
            showWarning("Already in use", "Can not create new "+rectype+". Save previous");    
            return;
        }
    }    
    
    newTabGroup(rectype); //load and clear fields
}
//listener on click on records list
loadRecord() {
    String record_id = getListItemValue(); //get id from list
    String rectype = getFieldValue("control/data/rectypeList");
    loadRecordById(record_id, rectype);
}
//
loadRecordById(entid, rectype) {
    
    if (isNull(entid)) return;
    
    showToast(entid);
    showTabGroup(rectype, entid);
    
    setFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID", entid);
    
    loadRelatedRecords(rectype); 
    //updateRelns();
}

//
loadEntity(record_id){
    return fetchAll(""+
"SELECT uuid, group_concat(coalesce(measure   || \' \'  || vocabname || \'(\'  ||  freetext ||\'; \'|| (certainty * 100.0) || \'% certain)\',  "+
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
"WHERE uuid = \'"+record_id+"\' "+
"GROUP BY uuid;");

}

//
saveRecord(rectype) {
    //todo - verify all required fields
    if (false){ 
        showWarning("Logic Error", "Cannot save record without id");
        return;
    }
    
    String record_id = getFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID");
    if("".equals(record_id)){
        record_id=null;
    }
    
    if (!isNull(record_id)) { 
        entity = fetchArchEnt(record_id); //need for geometry
        //data = entity.getGeometryList();
    }
    // first null is map data
    saveTabGroup(rectype, record_id, null, null, "setFieldValue(\""+rectype+"/"+rectype+"_uids/FAIMS_UID\", getLastSavedRecordId());");
}

deleteRecord(rectype){
    String record_id = getFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID");
    if (!isNull(record_id)) {
        showAlert("Confirm Deletion", "Press OK to Delete this record!", "deleteRecordConfirmed(\""+rectype+"\")", "deleteRecordCanceled()");
    }
}
deleteRecordConfirmed(rectype){
    String record_id = getFieldValue(rectype+"/"+rectype+"_uids/FAIMS_UID");
    deleteArchEnt(record_id);
    cancelTabGroup(rectype, false);
}
deleteRecordCanceled(){
    showToast("Delete Cancelled.");
}       
/*** END record management function ***/

/*** main list of records on control/data ***/
refreshEntities() {

   tabs_select.clear();
   tabs_edit.clear();

   showToast("Fetching all entities...");
   
   // populateDropDown("control/data/rectypeList", getRectypeList());
   String entName = getEntityNameByRectype( getFieldValue("control/data/rectypeList") );
   
   populateList("control/data/recordList", fetchEntityList(entName));
}

// make vocabulary
makeVocab(String attrib){
    Object a = fetchAll("select vocabid, vocabname from vocabulary join attributekey using (attributeid) where attributename = \'"+attrib+"\' ");
    return a;
}

/*** USER ***/

getDefaultUsersList() {
    users = fetchAll("select userid, fname || \' \' || lname from user");
    return users;
}

populateListForUsers(){
    populateDropDown("user/usertab/users", getDefaultUsersList());
}

populateListForUsers();

String username = "";
String device = "";

login(){
    Object userResult = fetchOne("select userid,fname,lname,email from user where userid=\'" + getFieldValue("user/usertab/users") + "\';");
    User user = new User(userResult.get(0),userResult.get(1),userResult.get(2),userResult.get(3));
    userid = userResult.get(0);
    setUser(user);
    username = userResult.get(1) + " " + userResult.get(2);
    showTabGroup("control");
}

/*** SYNC  ***/
setSyncMinInterval(10.0f);
setSyncMaxInterval(20.0f);
setSyncDelay(5.0f);

startSync() {
    setSyncEnabled(true);
    setFileSyncEnabled(true);
}

stopSync() {
    setSyncEnabled(false);
    setFileSyncEnabled(false);
}

loadAllAttributes();
';
    
return $out;   
}


//sanitize rt, dt, disp names
function getProperName($name){
        
        $name = str_replace(' ','_',trim(ucwords($name)));
        
        $goodname = preg_replace('~[^a-z0-9]+~i','', $name); //str_replace('__','_',);
        return prepareText($goodname);
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
If the field name contains the substring 'photo', 'picture', 'take' / 'video', 'movie', 'shoot' / 'sound', 'audio', 'record'  (case indifferent), 
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
  </div>
</body>
</html>

