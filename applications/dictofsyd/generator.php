<html>
<body>
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
* Script to generate standalone (html) pages for DOS website
*
*   parameters
*   path - output folder.  /var/www/htdocs/dosh3-deploy/ - by default
*   step=1 generate content pages
*       ft - type
*       r1,r2 - from to record id
*   step=2 generate browser pages
*
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/


require_once(dirname(__FILE__)."/php/incvars.php");
require_once(dirname(__FILE__)."/php/utilsFile.php");
require_once(dirname(__FILE__)."/php/Record.php");
require_once(dirname(__FILE__)."/php/getRecordInfo.php");

require_once(dirname(__FILE__)."/php/utilsMakes.php");

if(@$_REQUEST['step']=="1" || @$_REQUEST['step']=="2"){ //start generation

if($_REQUEST['pwd']=="sydarb43"){

	mysql_connection_select();
    $is_generation = true;
    $urlbase = "../"; //$urlbase".deploy/";

	if(@$_REQUEST['path']){
		$path = $_REQUEST['path'];
	}else{
		$path = "/var/www/htdocs/dosh3-deploy/";
	}
	$ft = $_REQUEST['ft'];  //entity type

if(@$_REQUEST['step']=="1"){

	//find the list of records

	if($ft>3000){
		$where = ", ".$ft." as enttype, d2.dtl_Value as rname from Records r, recDetails d ".
		" left join recDetails d2 on d.dtl_RecID=d2.dtl_RecID and d2.dtl_DetailTypeID=".DT_NAME.
		" where r.rec_RecTypeID=".RT_ENTITY." and r.rec_ID=d.dtl_RecID and d.dtl_DetailTypeID=".DT_ENTITY_TYPE." and d.dtl_Value=".$ft;
    }else if($ft==RT_ROLE){

        $where = ", 0 as enttype, d2.dtl_Value as rname from Records r, recDetails d ".
        " left join recDetails d2 on d.dtl_RecID=d2.dtl_RecID and d2.dtl_DetailTypeID=".DT_NAME.
        " where r.rec_RecTypeID=".RT_ROLE." and r.rec_ID=d.dtl_RecID and d.dtl_DetailTypeID=".DT_ROLE_TYPE." and d.dtl_Value=3324";

	}else {
		$where = ", d.dtl_Value as enttype, d2.dtl_Value as rname, d3.dtl_Value as roletype from Records r ".
        " left join recDetails d on r.rec_ID=d.dtl_RecID and d.dtl_DetailTypeID=".DT_ENTITY_TYPE.
		" left join recDetails d2 on r.rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID=".DT_NAME.
        " left join recDetails d3 on r.rec_ID=d3.dtl_RecID and d3.dtl_DetailTypeID=".DT_ROLE_TYPE.
		" where r.rec_RecTypeID";

		if($ft>0){
			$where = $where."=".$ft;
		}else{
			$where = $where." in (5,13,24,25,27,28,29)"; //
		}
	}
	if(@$_REQUEST['r1'] && @$_REQUEST['r2'] && is_numeric($_REQUEST['r1']) && is_numeric($_REQUEST['r2']))
    {
        if (intval($_REQUEST['r1'])<intval($_REQUEST['r2']))
	    {
		    $where = $where." and  r.rec_ID>".$_REQUEST['r1']." and r.rec_ID<".$_REQUEST['r2'];
	    }else{
            $where = $where." and  r.rec_ID=".$_REQUEST['r1'];
        }
    }


	//NOT USED $type_cache = (@$_REQUEST['fhml']=='1')?0:1;
	$is_recreate = (@$_REQUEST['fcreate']=='1');
    $limit = @$_REQUEST['limit']?intval($_REQUEST['limit']):0;
    $media_filepath = @$_REQUEST['filepath'];
    $media_is_recreate = (@$_REQUEST['filecreate']=='1');
    $media_thumb = $path.$media_filepath."thumbnail/";
    $media_full = $path.$media_filepath."full/";

	$path_popup = $path."popup";
	$path_preview = $path."preview";
    $path_citation = $path."citation";

    createDir($path_popup);
    createDir($path_preview);
    createDir($path_citation);
    if($media_is_recreate){
        createDir($media_thumb);
        createDir($media_full);
    }

	$query = 'select r.rec_ID as rec_id, r.rec_RecTypeID as rtype '.$where;

	//echo $query."<br>";
    echo "let's go!<br>";
/**/
	$res2 = mysql_query($query);
	$cntp = 1;
    $err_count = 0;

$stime0 = explode(' ', microtime());
$stime0 = $stime0[1] + $stime0[0];

	while (($row2 = mysql_fetch_assoc($res2))) {

        if (($row2['roletype'] && $row2['roletype']!=3324) ) continue; //.if role it may be occupation only
        //|| $row2['rec_id']==301

		$subfolder = getStaticSubfolder($row2['rtype'], $row2['enttype']);

		$filename_html = $path.$subfolder;
        createDir($filename_html);

        $rname = getStaticFileName($row2['rtype'], $row2['enttype'], $row2['rname'], $row2['rec_id']);
		$filename_html = $path.$rname; //.".html";

		if(!$is_recreate){
			//not recreate if html already exists
			if(file_exists($filename_html)){
				continue;
			}
		}


		$rec_id = $row2['rec_id'];
        $_REQUEST['name'] = $rec_id;

        try{

            ob_start();
            require(dirname(__FILE__)."/php/pageFrame.php");
            $out2 = ob_get_clean();


		    //XSLT $out2 = showRecord($rec_id, $type_cache);

		    if($out2){
			    saveAsFile($out2, $filename_html);
		    }else{
			    //report error
                echo "".($cntp)." <div style='color:red'>$rname - ERROR!!!</div>";
                $err_count++;
                continue;
		    }
        } catch (Exception $e) {
            echo "".($cntp)." <div style='color:red'>$rname - ERROR:". $e->getMessage()."</div>";
            $err_count++;
            continue;
        }

        ob_start();
        require(dirname(__FILE__)."/php/pagePreview.php");
        $out = ob_get_clean();
		//XSLT $out = showPreview($rec_id, $type_cache);
		if($out){
			saveAsFile($out, $path_preview."/".$rec_id);
		}else{
            echo "".($cntp)." <div style='color:blue'>$rname - preview generation failed</div>";
            $err_count++;
		}

        if($row2['rtype']==RT_ENTRY){

            ob_start();
            require(dirname(__FILE__)."/php/pageCitation.php");
            $out = ob_get_clean();
            if($out){
                saveAsFile($out, $path_citation."/".$rec_id);
            }else{
                echo "".($cntp)." <div style='color:blue'>$rname - citation generation failed</div>";
                $err_count++;
            }

		}else if($row2['rtype']==RT_MEDIA){

            ob_start();
            require(dirname(__FILE__)."/php/pagePopup.php");
            $out = ob_get_clean();
			//XSLT $out = showPopup($rec_id, $type_cache);
			if($out){
				saveAsFile($out, $path_popup."/".$rec_id);
			}else{
                echo "".($cntp)." <div style='color:blue'>$rname - popup generation failed</div>";
                $err_count++;
			}

            //copy full media file and create thumbnail (for images)
            if($media_is_recreate){
                  if(!publicMedia($record, $path.$media_filepath)){
                      echo "".($cntp)." <div style='color:blue'>$rname - copy media file failed</div>";
                      $err_count++;
                  }
            }
		}


        echo "".($cntp)." <a href='./deploy/".$rname."'>$rname</a>";
        if(isset($starttime)){
            $mtime = explode(' ', microtime());
            $totaltime = $mtime[0] + $mtime[1] - $starttime;
            printf("  ".$query_times.' %.3f sec.', $totaltime);
        }
        $query_times = "";
        print "<br>";
        ob_flush();flush();

		$cntp++;
		if($limit && $cntp>$limit){
			echo "TEST LIMIT $limit pages<br />";
			break;
		}
	}

    echo "<br /><br />".($cntp-1)." pages have been generated <br />";
    echo "errors: ".$err_count."<br />";

    if($cntp>1){
$mtime0 = explode(' ', microtime());
        $tottime = ($mtime0[0] + $mtime0[1] - $stime0);
        echo "Total time: ".sprintf('%.2f', $tottime/60)." mins<br />";
        //echo "Max time ".printf(' %.3f sec.', $maxtime[0])."   ".$maxtime[1]."<br />";
        echo "Average ".sprintf(' %.3f sec.', $tottime/($cntp-1))." sec<br />";
    }

}else if(@$_REQUEST['step']=="2"){ //start generation browse pages  ===============================

 	$path_browse = $path."browse";
    createDir($path_browse);

	$types = array();
	if($ft=='0'){
		$types = array("artefact","building","event","natural",
					"organisation",
"person",
"place",
"structure",
"entry",
"map",
"media",
"role",
"term",
"contributor");
	}else{
		$types = array($ft);
	}

 foreach ($types as $ftype){
        $_REQUEST['r'] = $ftype;

        ob_start();
        require("browse.php");
        $out = ob_get_clean();
		if($out){
			saveAsFile($out, $path_browse."/".$ftype);
            echo "<a href='./deploy/browse/".$ftype."'>".$ftype."</a><br/>";
		}else{
				//report error
                echo "error ".$ftype."<br/>";
		}
        ob_flush();flush();
 }//foreach

}

    echo "<a href='./generator.php'>back to form</a>";
}else{
	echo "wrong password";
}

}else{

?>
<h1>DOS web site generator (H3)</h1>
<form method="post">
<input name="step" value="1" type="hidden" />
<table>
<tr><td>Select type</td><td>
<select name="ft">

<option value="25">All Entities</option>
<option value="3291">artefact</option>
<option value="3294">building</option>
<option value="3296">event</option>
<option value="3298">natural</option>
<option value="3300">organisation</option>
<option value="3301">person</option>
<option value="3302">place</option>
<option value="3305">structure</option>

<option value="13">Entries</option>
<option value="28">Maps</option>
<option value="5">Multimedia</option>
<option value="27">Roles</option>
<option value="29">Subjects</option>
<option value="24">Contributors</option>

<option value="0">All</option>
</select>
</td></tr>
<tr><td>And/or range of records ids</td><td>from&nbsp;<input type="text" value="" name="r1" size="6">&nbsp;to&nbsp;<input type="text" value="" name="r2" size="6">&nbsp;(all records if left blank)</td></tr>
<tr><td>Recreate pages</td><td><input type="checkbox" checked="checked" name="fcreate" value="1" /></td></tr>
<!-- tr><td>Re-request hml</td><td><input type="checkbox" checked="checked" name="fhml" value="1" /></td></tr -->
<tr><td>Folder on server</td><td><input type="text" value="/var/www/htdocs/dosh3-deploy/" name="path" size="80"></td></tr>
<tr><td>Subfolder for media</td><td><input type="text" value="files/" name="filepath" size="80"> leave empty to use getMedia.php</td></tr>
<tr><td>Copy media</td><td><input type="checkbox" value="1" name="filecreate"></td></tr>
<tr><td>Password</td><td><input type="password" value="" name="pwd"></td></tr>
<tr><td>Test limit</td><td><input type="text" value="" name="limit" size="10"> leave empty to generate all</td></tr>
<tr><td colspan="2"><button type="submit">Start</button></td></tr>
</table>
</form>
<hr />

<h3>Browse pages and scripts</h3>
<form method="post">
<input name="step" value="2" type="hidden" />
<table>
<tr><td>Select type</td><td>
<select name="ft">

<option value="artefact">artefact</option>
<option value="building">building</option>
<option value="event">event</option>
<option value="natural">natural</option>
<option value="organisation">organisation</option>
<option value="person">person</option>
<option value="place">place</option>
<option value="structure">structure</option>

<option value="entry">Entries</option>
<option value="map">Maps</option>
<option value="media">Multimedia</option>
<option value="role">Roles</option>
<option value="term">Subjects</option>
<option value="contributor">Contributors</option>

<option value="0">All</option>
</select>
</td></tr>
<tr><td>Folder on server</td><td><input type="text" value="/var/www/htdocs/dosh3-deploy/" name="path" size="80"></td></tr>
<tr><td>Password</td><td><input type="password" value="" name="pwd"></td></tr>
<tr><td colspan="2"><button type="submit">Start</button></td></tr>
</table>
</form>

<?php
}
?>
</body>
</html>
<?php
/**
* original H3 database does not contain these tables. They are required to improve performance
*/
function createAndFillFactoidsCache(){
/*

  use hdb_dos_3;
  DROP TABLE IF EXISTS `recFacctoidsCache`;
  CREATE TABLE `recFacctoidsCache` (

  `rfc_RecID` int(10) unsigned NOT NULL,
  `rfc_SourceRecID` int(10) unsigned,
  `rfc_TargetRecID` int(10) unsigned,
  `rfc_RoleRecID` int(10) unsigned,

  PRIMARY KEY  (`rfc_RecID`),
  KEY `rfc_sourcePtrKey` (`rfc_SourceRecID`),
  KEY `rfc_TargetPtrKey` (`rfc_TargetRecID`),
  KEY `rfc_RolePtrKey` (`rfc_RoleRecID`)
  );
  delete from `recFacctoidsCache` where rfc_RecID>0;
  INSERT INTO `recFacctoidsCache`
select distinct r.rec_ID, d1.dtl_Value as src, d2.dtl_Value as trg, d3.dtl_Value as role
from Records r
left join recDetails d1
on r.rec_ID=d1.dtl_RecID and d1.dtl_DetailTypeID = 87
left join recDetails d2
on r.rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID = 86
left join recDetails d3
on r.rec_ID=d3.dtl_RecID and d3.dtl_DetailTypeID = 88
where r.rec_RecTypeID = 26;

count = 25377


  use hdb_dos_3;
  DROP TABLE IF EXISTS `recAnnotationCache`;
  CREATE TABLE `recAnnotationCache` (

  `rac_RecID` int(10) unsigned NOT NULL,
  `rac_EntryRecID` int(10) unsigned,
  `rac_TargetRecID` int(10) unsigned,

  PRIMARY KEY  (`rac_RecID`),
  KEY `rac_EntryPtrKey` (`rac_EntryRecID`),
  KEY `rac_TargetPtrKey` (`rac_TargetRecID`)
  );
  delete from `recAnnotationCache` where rac_RecID>0;
  INSERT INTO `recAnnotationCache`
select distinct r.rec_ID, d1.dtl_Value as entry, d2.dtl_Value as entity
from Records r
left join recDetails d1
on r.rec_ID=d1.dtl_RecID and d1.dtl_DetailTypeID = 42
left join recDetails d2
on r.rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID = 13
where r.rec_RecTypeID = 15;

count=22742
*/
}
?>