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

    if(isForAdminOnly("to sync FAIMS database")){
        return;
    }


//@todo HARDCODED id of OriginalID
$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);
    if($dt_SourceRecordID==0){
        print "Detail type 'source record id' not defined";
        return;
    }

$dt_Geo = (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:0);

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

</head>
<body style="padding:44px;" class="popup">


    <script src="../../common/php/loadCommonInfo.php"></script>

    <div class="banner"><h2>Build FAIMS Project</h2></div>
    <div id="page-inner" style="width:640px; margin:0px auto; padding: 0.5em;">
<?php

    $rt_toexport = @$_REQUEST['rt'];
    $projname = @$_REQUEST['projname'];

    $invalid = (!$projname || preg_match('[\W]', $str));

    //STEP 1 - define record types to be exported
    //if( !$rt_toexport || $invalid ){

        if($rt_toexport && $invalid){
            if($projname){
                print "<div style='color:red'>Only letters, numbers and underscores (_) are allowed in project name</div>";
            }else{
                print "<div style='color:red'>Project name is mandatory</div>";
            }
        }

        print "<form name='startform' action='exportFAIMS.php' method='get'>";
        print "<input id='rt_selected' name='rt' type='hidden'>";
        print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
        print "<div>Project name: <input name='projname' value='".($projname?$projname:HEURIST_DBNAME)."' size='100'></div><br/>";
        print "<div>Record types to include in project:";

        print "<span id='selectedRectypes' style='width:270px;padding:10px;font-weight:bold;'></span>";
        print "<input type='button' value='Select Record Type' id='btnSelRecType1' onClick='onSelectRectype(\"".HEURIST_DBNAME."\")'/><br/><br/>";

        print "<div><input type='submit' value='Start Export' /></div>";
        print "</form></div>";
        if($rt_toexport){
            print "<script>showSelectedRecTypes('".$rt_toexport."')</script>";
        }
    //exit();
    //}

    if( $rt_toexport && !$invalid ){

        $folder = HEURIST_UPLOAD_DIR."/faims/new/".$projname;

        //create export folder
        if(!file_exists($folder)){
            if (!mkdir($folder, 0777, true)) {
                    die('Failed to create folder for '.$folder);
            }
        }else{ //clear folder
            array_map('unlink', glob($folder."/*"));
        }


        //file_put_contents($folder."/project.settings", $projname." and basic information 3");
        file_put_contents($folder."/data_schema.xml", generateSchema($projname, $rt_toexport));
        //file_put_contents($folder."/ui_schema.xml", "We should be able to build this easily from the Heurist definitions");
        //file_put_contents($folder."/ui_logic.bsh", "this is a Java beanshell file which provides the operational  logic for the tablet interface");
        //file_put_contents($folder."/arch16N.properties", "archaeologicalisation’ file, provides a translation of all fields, terms and instructions to local language, something like: Click to add unit = Cliquer pour ajouter l’unité");


        //CREATE TARBALL $cmdline = "tar -cvzf ".$folder.".tar.gz ".$folder."/*";

        $res1 = 0;
        //$output1 = exec($cmdline, $output, $res1);
        if ($res1 != 0 ) {
                    echo ("<p class='error'>Error code $res1: Unable to create archive $folder.tar.gz<br>&nbsp;<br>");
                    echo("\n\n".$output);
                    echo "</p>";
        }else{
            //print "<p>".$serverBaseURL."</p>";
            print "<p><a href='".$serverBaseURL."/faims/new/".$projname.".tar.gz' target='_blank'>Download FAIMS project definitions tarball</a></p>";
        }

    }

/*
Since PHP 5.3
$phar = new PharData('project.tar');
// add all files in the project
$phar->buildFromDirectory(dirname(__FILE__) . '/project');

Or use pear install Archive_Tar
http://stackoverflow.com/questions/333130/build-tar-file-from-directory-in-php-without-exec-passthru
*/

function generateSchema($projname, $rt_toexport){

    $root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="sampleDataXML.xsl"?><dataSchema />');
    $root->addAttribute('name', DATABASE);
    $root->addAttribute('preparer', "Heurist user:".get_user_name());

    //load definitions (USE CACHE)
    $rtStructs = getAllRectypeStructures(true);
    $dtStructs = getAllDetailTypeStructures(true);
    $dtTerms = getTerms(true);

    $ind_rt_description = $rtStructs['typedefs']['commonNamesToIndex']['rty_Description'];
    $ind_rt_titlemask = $rtStructs['typedefs']['commonNamesToIndex']['rty_CanonicalTitleMask'];//'rty_TitleMask'];
    $int_dt_type = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Type'];
    $int_dt_name = $dtStructs['typedefs']['fieldNamesToIndex']['dty_Name'];
    $int_dt_ccode = $dtStructs['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

    $int_dt_termtree = $rtStructs['typedefs']['dtFieldNamesToIndex']["rst_FilteredJsonTermIDTree"];
    $int_dt_termtree_dis = $rtStructs['typedefs']['dtFieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];

    //$int_dt_termtree = $dtStructs['typedefs']['fieldNamesToIndex']["dty_JsonTermIDTree"];
    //$int_dt_termtree_dis = $dtStructs['typedefs']['fieldNamesToIndex']["dty_TermIDTreeNonSelectableIDs"];


    $unsupported = array('relmarker','relationtype','resource','separator','calculated','fieldsetmarker','urlinclude');

    $rectyps = explode(",", $rt_toexport);
    foreach ($rectyps as $rt) {

        $arch_element = $root->addChild('ArchaeologicalElement');

        $arch_element->addAttribute('type', $rtStructs['names'][$rt] );
        $arch_element->description = "{".$rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_description]."}";
        $titlemask = $rtStructs['typedefs'][$rt]['commonFields'][$ind_rt_titlemask];

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
                getTermsTree($dtTerms, $property, $dt_type, $terms );
            }
        }

    }//foreach

    ///create RelationshipElement if there are 1 or more record types to export
    if(count($rectyps)>0){

        $ind_parentid =  $dtTerms['fieldNamesToIndex']['trm_ParentTermID'];
        $ind_label =  $dtTerms['fieldNamesToIndex']['trm_Label'];
        $ind_descr =  $dtTerms['fieldNamesToIndex']['trm_Description'];

        $termLookup = $dtTerms['termsByDomainLookup']['relation'];

        foreach ($termLookup as $termid=>$term) {

            if($term[$ind_parentid]>0){

                $rel = $root->addChild('RelationshipElement');
                $rel->addAttribute('name', $term[$ind_label]);
                $rel->addAttribute('type', 'bidirectional');
                $rel->description = $termid."-".$term[$ind_label].($term[$ind_descr]?"-".$term[$ind_descr]:"");

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

            }
        }
    }

    return utf8_encode($root->asXML());
}

function getTermsTree($dtTerms, $property, $datatype, $terms){


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

    createSubTree($dtTerms, $meta, $datatype, $termTree, "");
}

function createSubTree($dtTerms, $meta, $datatype, $termTree, $parentname){

    $termLookup = $dtTerms['termsByDomainLookup'][$datatype];

    foreach ($termTree as $termid=>$child_terms){

        $termName = @$termLookup[$termid][0];
        if($termName){
            $meta->addChild('term', '{'.str_replace("&", "&amp;", $parentname.$termName).'}');
            createSubTree($dtTerms, $meta, $datatype, $child_terms, $parentname.$termName."-");
        }
    }

}


?>
  </div>
</body>
</html>

