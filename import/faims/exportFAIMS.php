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
    
        $folder = HEURIST_DOCUMENT_ROOT."/faims/new/".$projname;
        
        //create export folder
        if(!file_exists($folder)){
            if (!mkdir($folder, 0777, true)) {
                    die('Failed to create folder for '.$folder);
            }
        }else{ //clear folder
            array_map('unlink', glob($folder."/*"));    
        }

        
        file_put_contents($folder."/project.settings", $projname." and basic information 3");
        file_put_contents($folder."/data_schema.xml", "Heurist record types, fields, terms and constraints which will populate a new FAIMS structure SQLite database through the FAIMS server");
        file_put_contents($folder."/ui_schema.xml", "We should be able to build this easily from the Heurist definitions");
        file_put_contents($folder."/ui_logic.bsh", "this is a Java beanshell file which provides the operational  logic for the tablet interface");
        file_put_contents($folder."/arch16N.properties", "archaeologicalisation’ file, provides a translation of all fields, terms and instructions to local language, something like: Click to add unit = Cliquer pour ajouter l’unité");
        
            
        $cmdline = "tar -cvzf ".$folder.".tar.gz ".$folder."/*";
          
        $res1 = 0;  
        $output1 = exec($cmdline, $output, $res1);
        if ($res1 != 0 ) {
                    echo ("<p class='error'>Error code $res1: Unable to create tar archive $folder.tar.gz<br>&nbsp;<br>");
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
    
?>
  </div>
</body>
</html>

