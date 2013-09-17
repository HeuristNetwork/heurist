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
*   Write FAIMS project definition files (db schema, ui schema, a16N, ui logic
*   based on the Heurist database
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
    require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');
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
        print "<div>This function writes a set of FAIMS project definition files ".
        "(db schema, ui schema, ui logic, A16N) to a zip file, based on the record types ".
        "selected from the Heurist database. <p/>These files can be loaded into ".
        "the FAIMS server to create a new module.<p/></div>";

        print "<form name='startform' action='exportFAIMS.php' method='get'>";
        print "<input id='rt_selected' name='rt' type='hidden'>";
        print "<input name='step' value='1' type='hidden'>";
        print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
        print "<div><div class='lbl_form'>Project name:</div><input name='projname' value='".($projname?$projname:HEURIST_DBNAME)."' size='25'></div>";

        print "<div id='buttondiv2' style='display:".(($step=='1')?"none":"block")."'><div class='lbl_form'>Record types to include in export:</div>"; 
        print "<input type='button' value='Select Record Types' id='btnSelRecType1' onClick='onSelectRectype(\"".HEURIST_DBNAME."\")'/></div>";

        print "<div id='selectedRectypes' style='width:470px;color:black;padding-left:200px;font-weight:bold;'></div>";
        
        print "<div id='buttondiv' style='display:".(($rt_toexport && $step!='1')?"block":"none")."'><div class='lbl_form'></div><input type='submit' value='Start Export' /></div>";

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
        file_put_contents($folder."/arch16N.properties", arrToProps());
        copy("ui_logic.bsh", $folder."/ui_logic.bsh"); //this is a Java beanshell file which provides the operational  logic for the tablet interface


        //CREATE TARBALL $cmdline = "tar -cvzf ".$folder.".tar.gz ".$folder."/*";
        //CREATE ZIPFILE
        unlink($folder.".zip");
        $cmdline = "zip -r -j ".$folder.".zip ".$folder;
       
        $res1 = 0;
        $output1 = exec($cmdline, $output, $res1);
        if ($res1 != 0 ) {
                    echo ("<p class='error'>Error code $res1: Unable to create archive $folder.tar.gz<br>&nbsp;<br>");
                    echo $output;
                    echo "</p>";
        }else{
            //print "<p>".$serverBaseURL."</p>";
            //print "<p><a href='".$serverBaseURL."/faims/new/".$projname.".tar.gz' target='_blank'>Download FAIMS project definitions tarball</a></p>";
            print "<br><br><div class='lbl_form'></div>".
            "<a href='exportFAIMS.php/$projname.zip?db=".HEURIST_DBNAME."&step=2&projname=".$projname."' target='_blank' style='color:blue; font-size:1.2em'>Click here to start download FAIMS project definitions zip archive</a>";
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

function getResStr($str, $val=null){
    add16n($str, $val);
    return "{".str_replace(' ','_',$str)."}";
}

function add16n($line, $val=null){
    global $content_a16n;
    if(!isset($content_a16n)){
        $content_a16n = array();
    }
    if(isset($line) && !@$content_a16n[$line]){   //!in_array($line, $content_a16n)){
        $content_a16n[$line] = $val?$val:$line;
    }
}
//generate a16n output
function arrToProps(){
    global $content_a16n;
    $res = "";
    foreach ($content_a16n as $prop=>$value){
        $res = $res."{".str_replace(' ','_',$prop)."}=".$value."\n";
    }
    return $res;
}

function addComment($ele, $text, $ns=null){
    if($ns){
        $ele->addChild('remark____comment', $text, $ns);
    }else{
        $ele->addChild('remark____comment', $text);
    }
}
function prepareXml($root){
    $out = $root->asXML();
    $out = str_replace('<remark____comment>','<!--',$out);
    $out = str_replace('</remark____comment>','-->',$out);
    return utf8_encode(formatXML2($out));
}    
    
function generateSchema($projname, $rt_toexport){

    global $rtStructs, $dtStructs, $dtTerms, $ind_parentid, $ind_label, $ind_descr, $unsupported;


    $root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="sampleDataXML.xsl"?><dataSchema />');
    $root->addAttribute('name', DATABASE);
    $root->addAttribute('preparer', "Heurist user:".get_user_name());


    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $ind_rt_titlemask = $rtStructs['typedefs']['commonNamesToIndex']['rty_TitleMask']; //names
    $ind_rt_titlemask_canonical = $rtStructs['typedefs']['commonNamesToIndex']['rty_CanonicalTitleMask'];// codes
    $int_dt_type = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Type'];
    $int_dt_name = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Name'];
    $int_dt_description = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Documentation'];
    $int_dt_ccode = $dtStructs['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

    $int_dt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    $int_dt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];

    //$int_dt_termtree = $dtStructs['typedefs']['fieldNamesToIndex']["dty_JsonTermIDTree"];
    //$int_dt_termtree_dis = $dtStructs['typedefs']['fieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];

    $rectyps = explode(",", $rt_toexport);
    
    ///create RelationshipElement if there are 1 or more record types to export
    if(count($rectyps)>0){

        $termLookup = $dtTerms['termsByDomainLookup']['relation'];
        
        addComment($root, 'In Heurist, relationships are stored in a single relationship record type and 
        different types of relationship are distinguished by the relationship type field (attribute) whose values 
        can be organised hierarchichally. In FAIMS, each type of relationship is a separate record type, organised 
        into three classes - bidirectional, container and hierarchy');

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
        
        addComment($root, 'Archaeological elements correspond with Heurist record types');
        addComment($root, 'Properties correspond with Heurist base field types for this record type');
    }    

    foreach ($rectyps as $rt) {
        
        $rt_descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];

        addComment($root, strtoupper($rtStructs['names'][$rt])); 
        $arch_element = $root->addChild('ArchaeologicalElement');

        $arch_element->addAttribute('type', $rtStructs['names'][$rt] );
        $arch_element->description = str_replace("&", "&amp;", $rt_descr);
        $titlemask = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_titlemask];
        $titlemask_canonical = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_titlemask_canonical];
        
        //backward capability - make sure that titlemask is canonical
        $titlemask = titlemask_make($titlemask, $rt, 1, null, _ERR_REP_SILENT);

        $has_identifier = false;

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
            if($det[$int_dt_description]){
                $property->addChild('description', str_replace("&", "&amp;", $det[$int_dt_description]));
            }                                         

            $ccode = $det[$int_dt_ccode];

            $is_in_titlemask = (!( strpos($titlemask, ".".$ccode."]")===false && strpos($titlemask, "[".$ccode."]")===false )) ;
            if($is_in_titlemask){
                $property->addAttribute('isIdentifier', 'true');
                $has_identifier = true;
            }

            if($dt_type=='enum' || $dt_type=='relationtype'){
                $terms = $detail[$int_dt_termtree];
                //convert to tree
                $cnt = getTermsTree($property, $dt_type, $terms);
                
                $rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'] = $cnt;
            }
        }
        
        if(!$has_identifier){
            addComment($root, 'ERROR!!!!! This ArchaeologicalElement does not have identifiers!!! Verify TitleMask in Heurist recordtype!'); 
            
        }

    }//foreach recordtype

    $out = prepareXml($root);

    return $out;
}

/**
* generate with simplexml
* 
* @param mixed $projname
* @param mixed $rt_toexport
*/
function generate_UI_Schema($projname, $rt_toexport){

    global $rtStructs, $dtStructs, $dtTerms, $ind_parentid, $ind_label, $ind_descr, $unsupported;


   add16n('Users'); 
   add16n('dummy');
   add16n('Devices');
   add16n('Start.Recording');
   
    $root = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>
<h:html xmlns="http://www.w3.org/2002/xforms" xmlns:h="http://www.w3.org/1999/xhtml" xmlns:ev="http://www.w3.org/2001/xml-events" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:jr="http://openrosa.org/javarosa" />');

    $head = $root->addChild("head",'','http://www.w3.org/1999/xhtml');
    $body = $root->addChild("body");

    $head->addChild("title", str_replace("_"," ",DATABASE));
    addComment($head, 'model lists user interface tabgroups, tabs and content of tabs', 'http://www.w3.org/2002/xforms');
    $model = $head->addChild("model",'','http://www.w3.org/2002/xforms');
    $instance = $model->addChild("instance");
    addComment($model, 'list of numeric and blocktext(memo) attributes(fields)');

    $faims = $instance->addChild("faims");
    $faims->addAttribute('id', DATABASE);

    $style = new SimpleXMLElement('<style><orientation><orientation/></orientation><even><layout_weight/></even></style>');
    xml_adopt($faims, $style);
    
    $user = new SimpleXMLElement('<user><usertab><users/></usertab></user>');
    xml_adopt($faims, $user);
    //<devices/><login/>
    //$faims->addChild('user')->addChild('usertab')->addChild('users');

    addComment($body, 'describes controls for each tabgroup and its nested tab', 'http://www.w3.org/2002/xforms');
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
      <label>'.getResStr('User List').'</label>
      <group ref="usertab">
        <label>'.getResStr('User List').'</label>
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
    $int_dt_type = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Type'];
    $int_dt_name = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Name'];
    $int_dt_ccode = $dtStructs['typedefs']['fieldNamesToIndex']['dty_ConceptID'];
    $int_dt_desc = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Documentation'];

    $int_dt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    $int_dt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];
    $int_dt_repeat = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_MaxValues"];

    $int_dt_disp_name = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_DisplayName"];

    
    addComment($faims, 'control tabgroup that contains button to manipulate entities: load, list, create');
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
    addComment($faims, 'list of tabgroups for entities');

    $rectyps = explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $rtname = $rtStructs['names'][$rt];
        $rtnamex = getProperName($rtname);
        $descr = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description];
        
        $tab_buttonholder->addChild($rtnamex);
        $tab_data->addChild($rtnamex.'List');
        $tab_data->addChild($rtnamex.'Load');

        addComment($faims, strtoupper($rtnamex) );        
        $tabgroup_head = $faims->addChild($rtnamex); //tabgroup for rectype
        $tab = $tabgroup_head->addChild('main');     //first tab

        addComment($body, strtoupper($rtnamex), 'http://www.w3.org/2002/xforms');
        $tabgroup_body = $body->addChild('group','','http://www.w3.org/2002/xforms'); //tabgroup for rectype
        $tabgroup_body->addChild('label', getResStr($rtname) );
        $tabgroup_body->addAttribute('ref', $rtnamex);
        $tabgroup_body->addAttribute('faims_archent_type', $rtname);
        
        $group = $tabgroup_body->addChild('group'); //first tab
        $group->addAttribute('ref', 'main');
        $group->addChild('label', getResStr($descr) );

        $details =  $rtStructs['typedefs'][$rt]['dtFields'];

        foreach ($details as $dtid=>$detail) {

            $is_repeatable = ($detail[$int_dt_repeat]!='1');
            
            $det = $dtStructs['typedefs'][$dtid]['commonFields'];

            $dt_type = $det[$int_dt_type];
            
            if($dt_type=='separator'){
                //create new tab
               
                $headername = 'header'.$rt.'_'.$dtid;
                $tab = $tabgroup_head->addChild($headername);     //first tab
                
                $group = $tabgroup_body->addChild('group'); //first tab
                $group->addAttribute('ref', $headername);
                $group->addChild('label', getResStr( $detail[$int_dt_disp_name] ) );
            }
            
            if(in_array($dt_type, $unsupported)){
                continue;
            }

            $dtname = $det[$int_dt_name];
            $dtnamex = getProperName($dtname);

            $tab->addChild($dtnamex); //add to strucsture

            $isvocab = ($dt_type=='enum');

            $input = $group->addChild($isvocab?($is_repeatable?'select':'select1'):'input');
            $input->addChild('label', getResStr( $detail[$int_dt_disp_name] ) );
            $input->addAttribute('ref', $dtnamex);
            $input->addAttribute('faims_attribute_name', $dtname);
            $input->addAttribute('faims_read_only', 'false');
            $input->addAttribute('faims_certainty', 'false');
            if($isvocab) {
                $termsCount = @$rtStructs['typedefs'][$rt]['dtFields'][$dtid]['termdepth'];
                $input->addAttribute('appearance', ($termsCount>4)?'compact':'full');
            }

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
        $trigger->addChild('label', getResStr('Save.Record') );
        $trigger = $group->addChild('trigger');
        $trigger->addAttribute('ref', 'Delete');
        $trigger->addChild('label', getResStr('Delete.Record') );
        

    }//foreach
    
    return prepareXml($root);
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

    $meta = $property->addChild('lookup');

//error_log(">>>".print_r($termTree, true));

    return createSubTree($meta, $datatype, $termTree, "");
}

function createSubTree($meta, $datatype, $termTree, $parentname){

    global $dtTerms, $ind_label;
    $termLookup = $dtTerms['termsByDomainLookup'][$datatype];
    $ind_ccode = $dtTerms['fieldNamesToIndex']['trm_ConceptID'];
    $ind_label = $dtTerms['fieldNamesToIndex']['trm_Label'];
    $ind_descr = $dtTerms['fieldNamesToIndex']['trm_Description'];
    $ind_tcode = $dtTerms['fieldNamesToIndex']['trm_Code'];
    
    $cnt = 0;

    foreach ($termTree as $termid=>$child_terms){

        $termName = @$termLookup[$termid][$ind_label];
        
        if($termName){
            $termCcode = @$termLookup[$termid][$ind_ccode];
            $termDescr = @$termLookup[$termid][$ind_descr];
            $termCode = @$termLookup[$termid][$ind_tcode];
            if($termCode){
                $termCode = "[".$termCode."]";
            }else{
                $termCode = "";
            }
            
            if(strpos($termCcode,'-')==false) {
                $termCcode = '0-'.$termCcode;
            }
            $term = $meta->addChild('term', getResStr($termCcode, $termName.$termCode) );
            $term->addChild('description', str_replace("&", "&amp;", $termName).$termCode.($termDescr?":".str_replace("&", "&amp;", $termDescr):"") );
            $cnt++;
            
            //$lbl = str_replace("&", "&amp;", $parentname.$termName);
            //$meta->addChild('term', getResStr($lbl) );
            
            $cnt = $cnt + createSubTree($meta, $datatype, $child_terms, $parentname.$termName."-");
        }
    }
    
    return $cnt;
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

