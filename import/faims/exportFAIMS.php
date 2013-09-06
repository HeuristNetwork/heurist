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
*   Build faims tarball based on heurist projecct
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
    require_once(dirname(__FILE__)."/../../records/files/fileUtils.php");

    if(isForAdminOnly("to sync FAIMS database")){
        return;
    }

    $rt_toexport = @$_REQUEST['rt'];
    $projname = @$_REQUEST['projname'];
    $step = @$_REQUEST['step'];
    
    if($step=='2' && $projname){
        downloadFile('application/zip', HEURIST_UPLOAD_DIR."faims/new/".$projname.".zip");
        exit();
    }
?>
<html>
<head>
  <title>Build FAIMS Project</title>
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
        padding:14px;
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
    //if( !$rt_toexport || $invalid ){

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
    }
        print "<form name='startform' action='exportFAIMS.php' method='get'>";
        print "<input id='rt_selected' name='rt' type='hidden'>";
        print "<input name='step' value='1' type='hidden'>";
        print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
        print "<div><div class='lbl_form'>Project name:</div><input name='projname' value='".($projname?$projname:HEURIST_DBNAME)."' size='80'></div>";

        print "<div><div class='lbl_form'>Record types to include in project:</div>";
        print "<input type='button' value='Select Record Type' id='btnSelRecType1' onClick='onSelectRectype(\"".HEURIST_DBNAME."\")'/></div>";

        print "<div id='selectedRectypes' style='width:470px;color:black;padding-left:200px;font-weight:bold;'></div>";
        
        print "<div><div class='lbl_form'></div><input type='submit' value='Start Export' /></div>";
        print "</form>";
        if($rt_toexport){
            print "<script>showSelectedRecTypes('".$rt_toexport."')</script>";
        }
    //exit();
    //}

    if( $rt_toexport && !$invalid ){

        $folder = HEURIST_UPLOAD_DIR."faims/new/".$projname;

        //create export folder
        if(!file_exists($folder)){
            if (!mkdir($folder, 0777, true)) {
                    die('Failed to create folder for '.$folder);
            }
        }else{ //clear folder
            array_map('unlink', glob($folder."/*"));
        }


        //load definitions (USE CACHE)
        $rtStructs = getAllRectypeStructures(true);
        $dtStructs = getAllDetailTypeStructures(true);
        $dtTerms = getTerms(true);
        $ind_parentid =  $dtTerms['fieldNamesToIndex']['trm_ParentTermID'];
        $ind_label =  $dtTerms['fieldNamesToIndex']['trm_Label'];
        $ind_descr =  $dtTerms['fieldNamesToIndex']['trm_Description'];
        $unsupported = array('relmarker','relationtype','separator','resource','calculated','fieldsetmarker','urlinclude'); 
        $content_a16n = null;

        //file_put_contents($folder."/project.settings", $projname." and basic information 3");
        file_put_contents($folder."/data_schema.xml", generateSchema($projname, $rt_toexport));
        file_put_contents($folder."/ui_schema.xml", generate_UI_Schema($projname, $rt_toexport));
        file_put_contents($folder."/arch16N.properties", arrToProps($content_a16n));
        //file_put_contents($folder."/ui_logic.bsh", "this is a Java beanshell file which provides the operational  logic for the tablet interface");


        //CREATE TARBALL $cmdline = "tar -cvzf ".$folder.".tar.gz ".$folder."/*";
        //CREATE ZIPFILE
        unlink($folder.".zip");
        $cmdline = "zip -r -j ".$folder.".zip ".$folder;
       
        $res1 = 0;
        $output1 = exec($cmdline, $output, $res1);
        if ($res1 != 0 ) {
                    echo ("<p class='error'>Error code $res1: Unable to create archive $folder.tar.gz<br>&nbsp;<br>");
                    echo("\n\n".$output);
                    echo "</p>";
        }else{
            //print "<p>".$serverBaseURL."</p>";
            //print "<p><a href='".$serverBaseURL."/faims/new/".$projname.".tar.gz' target='_blank'>Download FAIMS project definitions tarball</a></p>";
            print "<p><div class='lbl_form'></div><a href='exportFAIMS.php/$projname.zip?db=".HEURIST_DBNAME."&step=2&projname=".$projname."' target='_blank'>Download FAIMS project definitions zip archive</a></p>";
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

function add16n($line){
    global $content_a16n;
    if(!isset($content_a16n)){
        $content_a16n = array();
    }
    if(isset($line) && !in_array($line, $content_a16n)){
        array_push($content_a16n, $line);
    }
}
function arrToProps(){
    global $content_a16n;
    $res = "";
    foreach ($content_a16n as $prop){
        $res = $res."{".str_replace(' ','_',$prop)."}=".$prop."\n";
    }
    return $res;
}

function generateSchema($projname, $rt_toexport){

    global $rtStructs, $dtStructs, $dtTerms, $ind_parentid, $ind_label, $ind_descr, $unsupported;


    $root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="sampleDataXML.xsl"?><dataSchema />');
    $root->addAttribute('name', DATABASE);
    $root->addAttribute('preparer', "Heurist user:".get_user_name());


    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $ind_rt_titlemask = $rtStructs['typedefs']['commonNamesToIndex']['rty_CanonicalTitleMask'];//'rty_TitleMask'];
    $int_dt_type = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Type'];
    $int_dt_name = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Name'];
    $int_dt_ccode = $dtStructs['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

    $int_dt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    $int_dt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];

    //$int_dt_termtree = $dtStructs['typedefs']['fieldNamesToIndex']["dty_JsonTermIDTree"];
    //$int_dt_termtree_dis = $dtStructs['typedefs']['fieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];

    $rectyps = explode(",", $rt_toexport);
    
    ///create RelationshipElement if there are 1 or more record types to export
    if(count($rectyps)>0){

        $termLookup = $dtTerms['termsByDomainLookup']['relation'];

        foreach ($termLookup as $termid=>$term) {

            if($term[$ind_parentid]>0){

                $rel = $root->addChild('RelationshipElement');
                $rel->addAttribute('name', getFullTermName($term, 'relation'));// $term[$ind_label]);
                $rel->addAttribute('type', 'bidirectional');
                $rel->description = $termid."-".$term[$ind_label].($term[$ind_descr]?"-".$term[$ind_descr]:"");
/*
                // NAME
                $property = $rel->addChild('property');
                $property->addAttribute('type', 'string');
                $property->addAttribute('name', 'Name / Title');
                $property->addAttribute('isIdentifier', 'true');

                // COMMENT
                $property = $rel->addChild('property');
                $property->addAttribute('type', 'string');
                $property->addAttribute('name', 'Short summary');

                // INTERPREATATION
                $property = $rel->addChild('property');
                $property->addAttribute('type', 'string');
                $property->addAttribute('name', 'Comments on relationship');

                // TITLE OF SOURCE REC
                $property = $rel->addChild('property');
                $property->addAttribute('type', 'string');
                $property->addAttribute('name', 'Source record title');
                $property->addAttribute('isIdentifier', 'true');

                // TITLE OF TARGET REC
                $property = $rel->addChild('property');
                $property->addAttribute('type', 'string');
                $property->addAttribute('name', 'Target record title');
                $property->addAttribute('isIdentifier', 'true');
*/
            }
        }
    }    

    foreach ($rectyps as $rt) {
        
        $rt_descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];

        $arch_element = $root->addChild('ArchaeologicalElement');

        $arch_element->addAttribute('type', $rtStructs['names'][$rt] );
        $arch_element->description = "{".$rt_descr."}";
        $titlemask = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_titlemask];
        
        add16n($rt_descr);

//error_log("titlemask>>>>".$titlemask);

        $details =  $rtStructs['typedefs'][$rt]['dtFields'];

//error_log(">>>".print_r($dtStructs['typedefs'][$dtid],true));

        foreach ($details as $dtid=>$detail) {

            $det = $dtStructs['typedefs'][$dtid]['commonFields'];

            $dt_type = $det[$int_dt_type];
            if(in_array($dt_type, $unsupported)){
                continue;
            }

            $property = $arch_element->addChild('property');

            $property->addAttribute('type', $dt_type);
            $property->addAttribute('name', $det[$int_dt_name]);

            $ccode = $det[$int_dt_ccode];

            $is_in_titlemask = ( strpos($titlemask, ".".$ccode."]")!=false || strpos($titlemask, "[".$ccode."]")!=false ) ;
            if($is_in_titlemask){
                $property->addAttribute('isIdentifier', 'true');
            }

            if($dt_type=='enum' || $dt_type=='relationtype'){
                $terms = $detail[$int_dt_termtree];
                //convert to tree
                getTermsTree($property, $dt_type, $terms);
            }
        }

    }//foreach

    return utf8_encode(formatXML($root));
}

/**
* put your comment there...
*
* @param mixed $projname
* @param mixed $rt_toexport
*/
function generate_UI_Schema($projname, $rt_toexport){

    global $rtStructs, $dtStructs, $dtTerms, $ind_parentid, $ind_label, $ind_descr, $unsupported;


   add16n('{Users}'); 
   add16n('{dummy}');
   add16n('{User List}');
   add16n('{Devices}');
   add16n('{Start.Recording}');
   add16n('{Save.Record}');
   add16n('{Delete.Record}');
   
    $root = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>
<h:html xmlns="http://www.w3.org/2002/xforms" xmlns:h="http://www.w3.org/1999/xhtml" xmlns:ev="http://www.w3.org/2001/xml-events" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:jr="http://openrosa.org/javarosa" />');

    $head = $root->addChild("head",'','http://www.w3.org/1999/xhtml');
    $body = $root->addChild("body");

    $head->addChild("title", str_replace("_"," ",DATABASE));
    $model = $head->addChild("model",'','http://www.w3.org/2002/xforms');
    $instance = $model->addChild("instance");
    $faims = $instance->addChild("faims");
    $faims->addAttribute('id', DATABASE);

    $style = new SimpleXMLElement('<style><orientation><orientation/></orientation><even><layout_weight/></even></style>');
    xml_adopt($faims, $style);
    
    $user = new SimpleXMLElement('<user><usertab><users/></usertab></user>');
    xml_adopt($faims, $user);
    //<devices/><login/>
    //$faims->addChild('user')->addChild('usertab')->addChild('users');

    $style = new SimpleXMLElement('<group ref="style">
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
    
    $user = new SimpleXMLElement('<group ref="user">
      <label>{User List}</label>
      <group ref="usertab">
        <label>{User List}</label>
        <select1 ref="users">
          <label>{Users}:</label>
          <item>
            <label>{dummy}</label>
            <value>dummy</value>
          </item>
        </select1>
      </group>
    </group>');
    xml_adopt($body, $user, 'http://www.w3.org/2002/xforms');
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
    $ind_rt_titlemask = $rtStructs['typedefs']['commonNamesToIndex']['rty_CanonicalTitleMask'];//'rty_TitleMask'];
    $int_dt_type = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Type'];
    $int_dt_name = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Name'];
    $int_dt_ccode = $dtStructs['typedefs']['fieldNamesToIndex']['dty_ConceptID'];
    $int_dt_desc = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Documentation'];

    $int_dt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    $int_dt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];
    $int_dt_disp_name = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DisplayName"];

    
    $tabgroup_control = $faims->addChild('control');
    $tab_buttonholder  = $tabgroup_control->addChild('buttonholder');
    $tab_data  = $tabgroup_control->addChild('data');
    $tab_gps = new SimpleXMLElement('<gps>
              <connectexternal/>
              <connectinternal/>
              <startsync/>
              <stopsync/>
            </gps>');
    xml_adopt($tabgroup_control, $tab_gps);

    $rectyps = explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname);
        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];
        add16n($rtname);
        add16n($descr);
        $descr = '{'.$descr.'}';
        
        $tab_buttonholder->addChild($rtnamex);
        $tab_data->addChild($rtnamex.'List');
        $tab_data->addChild($rtnamex.'Load');

        $tabgroup_head = $faims->addChild($rtnamex); //tabgroup for rectype
        $tab = $tabgroup_head->addChild('main');     //first tab

        $tabgroup_body = $body->addChild('group','','http://www.w3.org/2002/xforms'); //tabgroup for rectype
        $tabgroup_body->addChild('label', "{".$rtname."}");
        $tabgroup_body->addAttribute('ref', $rtnamex);
        $tabgroup_body->addAttribute('faims_archent_type', $rtname);
        
        $group = $tabgroup_body->addChild('group'); //first tab
        $group->addAttribute('ref', 'main');
        $group->addChild('label', $descr);

        $details =  $rtStructs['typedefs'][$rt]['dtFields'];

        foreach ($details as $dtid=>$detail) {

            $det = $dtStructs['typedefs'][$dtid]['commonFields'];

            $dt_type = $det[$int_dt_type];
            
            if($dt_type=='separator'){
                //create new tab
               
                $headername = 'header'.$rt.'_'.$dtid;
                $tab = $tabgroup_head->addChild($headername);     //first tab
                
                $group = $tabgroup_body->addChild('group'); //first tab
                $group->addAttribute('ref', $headername);
                $group->addChild('label', "{".$detail[$int_dt_disp_name]."}");
                add16n( $detail[$int_dt_disp_name] );    
            }
            
            if(in_array($dt_type, $unsupported)){
                continue;
            }

            $dtname = $det[$int_dt_name];
            $dtnamex = getProperName($dtname);

            $tab->addChild($dtnamex); //add to strucsture

            $isvocab = ($dt_type=='enum');

            $input = $group->addChild($isvocab?'select1':'input');
            $input->addChild('label', "{".$detail[$int_dt_disp_name]."}");
            $input->addAttribute('ref', $dtnamex);
            $input->addAttribute('faims_attribute_name', $dtname);
            $input->addAttribute('faims_read_only', 'false');
            $input->addAttribute('faims_certainty', 'false');
            $input->addAttribute('appearance', 'compact');
            
            add16n( $detail[$int_dt_disp_name] );

            $input->addAttribute('faims_attribute_type', $isvocab?'vocab': ($dt_type=='float' || $dt_type=='integer' ?'measure':'freetext'));

            if($isvocab){ //empty value for selection
                $item = $input->addChild('item');
                $item->label = '{dummy}';
                $item->value = 'dummy';
            }

            $ftype = null;
            if($dt_type=='float' || $dt_type=='integer'){
                $ftype =  'decimal';
            }else if($dt_type=='blocktext'){
                $ftype = 'string';
            }

            if($ftype){
                $path = '/faims/'.$rtnamex.'/'.$dtnamex;
                $bind = $model->addChild('bind');
                $bind->addAttribute('nodeset', $path);
                $bind->addAttribute('type', $ftype);
            }

/*
            if($dt_type=='enum' || $dt_type=='relationtype'){
                $terms = $detail[$int_dt_termtree];
                //convert to tree
                getTermsTree($property, $dt_type, $terms);
            }
*/
        }
        
        //add save/delete buttons to last tab
        $tab->addChild('Update');
        //$tab->addChild('Update2');
        $tab->addChild('Delete');
        
        $trigger = $group->addChild('trigger');
        $trigger->addAttribute('ref', 'Update');
        $trigger->addChild('label', '{Save.Record}');
        $trigger = $group->addChild('trigger');
        $trigger->addAttribute('ref', 'Delete');
        $trigger->addChild('label', '{Delete.Record}');
        

    }//foreach

    return utf8_encode(formatXML($root));
}

function getProperName($name){
        $goodname = preg_replace('~[^a-z0-9]+~i','_', $name);
        return str_replace('__','_',$goodname);
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
// dash separated list of term label and all its parents
//
function getFullTermName($term, $datatype){

    global $dtTerms, $ind_parentid, $ind_label;

    $name = "";

    if($term){
        if($term[$ind_parentid]>0){
            $allterms = $dtTerms['termsByDomainLookup'][$datatype];
            $name = getFullTermName( @$allterms[$term[$ind_parentid]], $datatype);
        }
        $name = ($name?$name."-":"").$term[$ind_label];
    }
    return $name;
}

function getTermsTree($property, $datatype, $terms){

    global $dtTerms;

//error_log(">>>terms=".$terms);

    if($datatype == "relmarker" || $datatype === "relationtype"){
        $datatype = "relation";
    }else if(!($datatype=="enum" || $datatype=="relation")){
        return;
    }

    if(is_numeric($terms)){
        $termTree =  $dtTerms['treesByDomain'][$datatype][$terms];
    }else{
        $termTree = json_decode($terms);
    }
    if(count($termTree)<1){
        return;
    }

    $meta = $property->addChild('lookup');

//error_log(">>>".print_r($termTree, true));

    createSubTree($meta, $datatype, $termTree, "");
}

function createSubTree($meta, $datatype, $termTree, $parentname){

    global $dtTerms, $ind_label;
    $termLookup = $dtTerms['termsByDomainLookup'][$datatype];

    foreach ($termTree as $termid=>$child_terms){

        $termName = @$termLookup[$termid][0];
        if($termName){
            $lbl = str_replace("&", "&amp;", $parentname.$termName);
            add16n($lbl);
            $meta->addChild('term', '{'.$lbl.'}');
            createSubTree($meta, $datatype, $child_terms, $parentname.$termName."-");
        }
    }

}

function formatXML($simpleXml){
    $dom = dom_import_simplexml($simpleXml)->ownerDocument;
    //$dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    return $dom->saveXML();
}


?>
  </div>
</body>
</html>

