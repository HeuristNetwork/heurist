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

    $rt_toexport = @$_REQUEST['rt'];
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
  </style>  
</head>

<body style="padding:44px;" class="popup">


    <script src="../../common/php/loadCommonInfo.php"></script>

    <div class="banner"><h2>Build FAIMS Project</h2></div>
    <div id="page-inner" style="width:740px; margin:0px 5%; padding: 0.5em;">   
    
    
<?php
    
    $invalid = (!$projname || preg_match('/[^A-Za-z0-9_\$]/', $projname)); //'[\W]'

    //STEP 1 - define record types to be exported
    mysql_connection_select(DATABASE);
    
    if($step=='1'){
        
        if(!$rt_toexport){
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
            $rtIDs = mysql__select_assoc("defRecTypes","rty_ID","rty_Name"," rty_ID in ($rt_toexport) order by rty_ID");
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

        print "<div id='buttondiv2' style='display:".(($step=='1')?"none":"block")."'><div class='lbl_form'>Record types to include in export:</div>"; 
        print "<input type='button' value='Select Record Types' id='btnSelRecType1' onClick='onSelectRectype(\"".HEURIST_DBNAME."\")'/></div>";
        
        // List of record types for export
        print "<div id='selectedRectypes' style='width:470px;color:black;padding-left:200px;font-weight:bold;'></div>";

        if(count($rt_invalid_masks)>0){
            print "<p style='color:red'>You have invalid title masks in the following record types: <b>"
            .implode(",",$rt_invalid_masks)."</b>
 FAIMS requires setting of at least one field (attrribute) as an identifier, which is based  on the fields in the title mask. Please correct the title mask(s) before proceeding (edit the record type in Database Designer > Essentials > Manage RecordTypes/Fields)</p>";
        }
 
        // Don't show Start Export button until record types have been selected       
        print "<div id='buttondiv' style='display:".(($rt_toexport && $step!='1')?"block":"none")."'><div class='lbl_form'></div><input type='submit' value='Start Export' /></div>";

        print "</form>";
        if($rt_toexport){
            print "<script>showSelectedRecTypes('".$rt_toexport."')</script>";
        }

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
        $supported = array ('freetext', 'blocktext', 'integer', 'date', 'year', 'boolean', 'enum', 'float', 'file');
        //$unsupported = array('relmarker','relationtype','separator','resource','calculated','fieldsetmarker','urlinclude','geo');
        // Note: Geos are supported, but FAIMS uses a different methodology for recording them, that is they are an implicit part of every record 
        $content_a16n = null;

        //file_put_contents($folder."/project.settings", $projname." and basic information 3");
        file_put_contents($folder."/data_schema.xml", generateSchema($projname, $rt_toexport));
        file_put_contents($folder."/ui_schema.xml", generate_UI_Schema($projname, $rt_toexport));
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

    global $rtStructs, $dtStructs, $dtTerms, $ind_parentid, $ind_label, $ind_descr, $supported;
                                                                        
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
    

    $rectyps = explode(",", $rt_toexport);
    
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
                    
                    $dtdisplayname = $detail[$int_rt_disp_name]; // the display name for this field
                    $dtdisplaynamex = getProperName($dtdisplayname);
                    if($dtdisplaynamex==""){
                           $dtdisplaynamex = "Rectype".$rt."_Field".$dtid;
                    }

                    if(!@$reltypes["has".$dtdisplaynamex]){
                        array_push($reltypes, "has".$dtdisplaynamex);
                    }
                }
                
           }//for detals
        }//for rectypes
        
        if(count($reltypes)>0){
            
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
                    
                }else{    //respurce-pointer
                    
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
function generate_UI_Schema($projname, $rt_toexport){

    global $rtStructs, $dtTerms, $ind_parentid, $ind_label, $ind_descr, $supported;

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
    $style = new SimpleXMLElement('<style><orientation><orientation/></orientation><even><layout_weight/></even></style>');
    xml_adopt($faims, $style);
    
    // user
    $user = new SimpleXMLElement('<user><usertab><users/></usertab></user>');
    xml_adopt($faims, $user);
    
    //<devices/><login/>
    //$faims->addChild('user')->addChild('usertab')->addChild('users');
    
    
    // ------------------------------------------------------------------------------------------------------------
    
    // Header part  2  
    
    // ARTEM: THIS SECTION WAS BELOW THE BODY SECTION, MOVED UP HERE 6.30PM 30/9/13
         
    addComment($faims, 'control tabgroup that contains button to manipulate entities: load, list, create');

    $tabgroup_control = $faims->addChild('control');
    
    $tab_buttonholder  = $tabgroup_control->addChild('buttonholder');
    
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
    xml_adopt($tabgroup_control, $tab_map);
     
    $tab_data  = $tabgroup_control->addChild('data');
    
    $tab_gps = new SimpleXMLElement('<gps>
              <connectexternal/>
              <connectinternal/>
              <startsync/>
              <stopsync/>
              <startTimeLog/>
              <startDistanceLog/>
              <stopTrackLog/>
            </gps>');
    xml_adopt($tabgroup_control, $tab_gps);
      
      
    // -------------------------------------------------------------------------------------------------------
    
    // Body section 3
  
    addComment($body, 'Describes controls for each tabgroup and its nested tabs', 'http://www.w3.org/2002/xforms');
    
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
    </group>'); 
    xml_adopt($body, $style, 'http://www.w3.org/2002/xforms');
    
    // User
    $user = new SimpleXMLElement(
    '<group ref="user">
      <label>'.getResStrA16n('User List').'</label>
      <group ref="usertab" faims_scrollable="false">
        <label>'.getResStrA16n('User List').'</label>
        <select1 appearance="compact" ref="users">
          <label>{Users}:</label>
          <item>
            <label>placeholder</label>
            <value>placeholder</value>
          </item>
        </select1>
      </group>
    </group>');
    xml_adopt($body, $user, 'http://www.w3.org/2002/xforms');
    
    // Maps and controls ADDED BY IAN
     $map = new SimpleXMLElement(
     '<group ref="control">  
        <label>Maps and Controls</label>
        <group faims_scrollable="false" ref="map">
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
        </group>
        
        <group ref="gps">  
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
        </group>
            
      </group>');
    xml_adopt($body, $map, 'http://www.w3.org/2002/xforms');
    
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
    

    
    // --------------------------------------------------------------------------------------------------------
    
    // Output specifications for each ArchEnt (Record type in Heurist)
    addComment($faims, 'list of tabgroups for entities');
    
    // Loop through each of the record types (ArchEntTypes) and output fields
    $rectyps = explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname);
        if($rtnamex==""){
               $rtnamex = "Rectype".$rt;
        }
        
        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];
        
        $tab_buttonholder->addChild($rtnamex);
        $tab_data->addChild('new'.$rtnamex);
        $tab_data->addChild($rtnamex.'List');
        $tab_data->addChild($rtnamex.'Load');
        
        $headername = $rtnamex.'_General_Information'; //first tab implied if no header at top of record

        addComment($faims, 'ArchEnt / Record Type: '.mb_strtoupper($rtnamex) );        
        $tabgroup_head = $faims->addChild($rtnamex);  //tabgroup for this record type
        $tab = $tabgroup_head->addChild($headername); //first tab for this record type

        addComment($body, mb_strtoupper($rtnamex), 'http://www.w3.org/2002/xforms');
        $tabgroup_body = $body->addChild('group','','http://www.w3.org/2002/xforms');
        $tabgroup_body->addChild('label',  getResStrA16n(prepareText($rtname)) );
        $tabgroup_body->addAttribute('ref', $rtnamex);
        $tabgroup_body->addAttribute('faims_archent_type', prepareText($rtname));
        
        $group = $tabgroup_body->addChild('group'); //first tab
        $group->addAttribute('ref', $headername);
        $group->addChild('label', 'Gen'); // getResStrA16n(prepareText($descr)) );

        $details =  $rtStructs['typedefs'][$rt]['dtFields'];
        
        $hasattachfile = false;

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
            
                if($hasattachfile){ //add to previous one
                        $tab->addChild('viewattached');            
                        $trigger = $group->addChild('trigger');
                        $trigger->addAttribute('ref', 'viewattached');
                        $trigger->addChild('label', 'View Attached Files');
                }
                
                $hasattachfile = false;
            
                //create new tab
                $headername = $rtnamex.'_'.$dtdisplaynamex;
                $tab = $tabgroup_head->addChild($headername); //first tab
                $group = $tabgroup_body->addChild('group'); //first tab
                $group->addAttribute('ref', $headername);
                $group->addChild('label', getResStrA16n( prepareText($detail[$int_rt_disp_name] )) );
            }
           
            
            if(!in_array($dt_type, $supported)){
                continue;
            }

            $tab->addChild($dtdisplaynamex); //add to strucsture
            if($dt_type=='file'){
                   $tab->addChild('attach'.$dtdisplaynamex);
            }

            $isvocab = ($dt_type=='enum');
            
            // TO LOOK AT  -  isIdentifier not defined (in logs, 5.20pnm 30/9/13)
            
            $isIdentifier = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['isIdentifier'];

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

/*
            if($dt_type=='enum' || $dt_type=='relationtype'){
                $terms = $detail[$int_rt_termtree];
                //convert to tree
                getTermsTree($property, $dt_type, $terms);
            }
*/
        }//for details
        
        if($hasattachfile){
                $tab->addChild('viewattached');            
                $trigger = $group->addChild('trigger');
                $trigger->addAttribute('ref', 'viewattached');
                $trigger->addChild('label', 'View Attached Files');
        }
        
        
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

    global $rtStructs, $dtTerms, $ind_parentid, $ind_label, $ind_descr, $supported;
    
    
    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $int_rt_dt_type = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_Type"];
    
    //not used $int_rt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    //not used $int_rt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];
    $int_dt_repeat = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_MaxValues"];

    $int_rt_disp_name = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DisplayName"];
    
    
$out = "";

    // Loop through each of the record types (ArchEntTypes) and output fields
    $rectyps = explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname);
        if($rtnamex==""){
               $rtnamex = "Rectype".$rt;
        }
        
        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];

$out .= "
/*** ArchEnt: $rtnamex ***/";

$out .= '
refreshEntities() {
    showToast("Fetching saved contexts...");
    populateList("control/data/'.$rtnamex.'List", fetchEntityList("'.$rtnamex.'"));
}
';

//event handlers
$eventsection = '
onEvent("control/data/'.$rtnamex.'List", "click", "load'.$rtnamex.'()");
onEvent("control/data/new'.$rtnamex.'", "click", "new'.$rtnamex.'()");
';

$selectors = '';

        $details =  $rtStructs['typedefs'][$rt]['dtFields'];
        
        $hasattachfile = false;
        $headername = $rtnamex."/".$rtnamex.'_General_Information'; //first tab implied if no header at top of record

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
            
                if($hasattachfile){  //add events to view attached files
                     $eventsection .= '
onEvent("'.$headername.'/viewattached", "click", "viewArchEntAttachedFiles(entityId)");';
                }
                $hasattachfile = false;
                //new tab
                $headername = $rtnamex."/".$rtnamex.'_'.$dtdisplaynamex;
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
                
                $eventsection .= '
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
                
                $selectors .= '
'.($action.'("'.$headername.'/'.$dtdisplaynamex.'", makeVocab("'.$dtdisplayname.'"));');
            }     
            //TODO  - support pointer, relmarker       
            
        }//for detail types
        
        if($hasattachfile){  //add events to view attached files
              $eventsection .= '
onEvent("'.$headername.'/viewattached", "click", "viewArchEntAttachedFiles(entityId)");';
        }
        $eventsection .= '
onEvent("'.$headername.'/Update", "delayclick", "save'.$rtnamex.'()");
onEvent("'.$headername.'/Delete", "delayclick", "delete'.$rtnamex.'()");
';

$out .= $eventsection;            

$rt_id = mb_strtolower($rtnamex)."_id";

//now event handlers - new, load, save, delete
$out .= '
String '.$rt_id.' = null;
load'.$rtnamex.'Attributes();

new'.$rtnamex.'(){
    '.$rt_id.' = null;
    newTabGroup("'.$rtnamex.'");
}
load'.$rtnamex.'() {
    //new'.$rtnamex.'();
    '.$rt_id.' = getListItemValue();
    load'.$rtnamex.'From('.$rt_id.');
}
load'.$rtnamex.'From(entid) {
    
    '.$rt_id.' = entid;
    if (isNull(entid)) return;
    showToast(entid);
    showTabGroup("'.$rtnamex.'", entid);
    updateAll'.$rtnamex.'(); 
    //updateRelns();
}

save'.$rtnamex.'() {
    //todo - verify all required fields
    if (false){ 
        showWarning("Logic Error", "Cannot save record without id");
        return;
    }
    
    if (!isNull('.$rt_id.')) {
        entity = fetchArchEnt('.$rt_id.');
    }
    // first null is map data
    saveTabGroup("'.$rtnamex.'", '.$rt_id.', null, null, "'.$rt_id.' = getLastSavedRecordId();");
}

delete'.$rtnamex.'(){
    if (!isNull('.$rt_id.')) {
        showAlert("Confirm Deletion", "Press OK to Delete this '.$rtname.'!", "reallyDelete'.$rtnamex.'()", "doNotDelete'.$rtnamex.'()");
    }
}
reallyDelete'.$rtnamex.'(){
    deleteArchEnt('.$rt_id.');
    cancelTabGroup("'.$rtnamex.'", false);
}
doNotDelete'.$rtnamex.'(){
    showToast("Delete Cancelled.");
}       
 
load'.$rtnamex.'Attributes(){
'.$selectors.'
}

updateAll'.$rtnamex.'(){
//load related records ?
}      
';        

        
        
    }//for record types

//header    
$out = '    
/****** 
 FAIMS Logic File generated by Heurist Vsn '.HEURIST_VERSION.', '. date('l jS \of F Y h:i:s A').'
 Database: '.DATABASE.'   Heurist user:'.get_user_name().'
 ******/

User user; // dont touch
String userid;

setSyncEnabled(true);
setFileSyncEnabled(true);

showWarning("Thanks for trying this module!", "This module has been generated from a Heurist database structure. You can customise the module yourself or we can help you. Contact info@fedarch.org for help.");


/*** control ***/

onEvent("control/data", "show", "refreshEntities()");
onEvent("control/gps/connectexternal", "click", "startExternalGPS()");
onEvent("control/gps/connectinternal", "click", "startInternalGPS()");
'.$out;    
    
/* misc functions to add */
$out = $out.'
// MISC FUNCTIONS    
makeVocab(String attrib){
    Object a = fetchAll("select vocabid, vocabname from vocabulary join attributekey using (attributeid) where attributename = \'"+attrib+"\' ");
    return a;
}
';

//footer
$out = $out.'
/*** USER ***/
/*** Should not require changing: edit the code below with extreme precaution ***/

getDefaultUsersList() {
    users = fetchAll("select userid, fname || \' \' || lname from user");
    return users;
}

populateListForUsers(){
    populateList("user/usertab/users", getDefaultUsersList());
}

populateListForUsers();

String username = "";
String device = "";

login(){
    Object userResult = fetchOne("select userid,fname,lname,email from user where userid=\'" + getListItemValue() + "\';");
    User user = new User(userResult.get(0),userResult.get(1),userResult.get(2),userResult.get(3));
    userid = userResult.get(0);
    setUser(user);
    username = userResult.get(1) + " " + userResult.get(2);
    showTabGroup("control");
}

onEvent("user/usertab/users", "click", "login()");

/*** SYNC ***/

onEvent("control/gps/startsync", "click", "startSync()");
onEvent("control/gps/stopsync", "click", "stopSync()");

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

