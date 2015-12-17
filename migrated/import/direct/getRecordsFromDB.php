<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* getRecordsFromDB.php
* gets all records in a specified database (later, a selection) and write directly to current DB
* Reads from either H2 or H3 format databases
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @param       includeUgrps=1 will output user and group information in addition to definitions
* @param       approvedDefsOnly=1 will only output Reserved and Approved definitions
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    if(isForAdminOnly("to import records")){
        return;
    }

	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
	require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");
	require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");
	require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');
    require_once(dirname(__FILE__).'/../../search/actions/actionMethods.php');

?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Database-to-database Transfer</title>
        <link rel=stylesheet href="../../common/css/global.css" media="all">
        <link rel=stylesheet href="../../common/css/admin.css" media="all">
    </head>
    <body class="popup">
		<!-- script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
		<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
		<script src="../../common/php/loadCommonInfo.php"></script -->

    <script type="text/javascript">
function printMapping(){
    var frm = document.getElementById('mapping');
    var i, ele, lbl, tp='', res = '';
    var elem = frm.elements;
    for(i = 0; i < elem.length; i++)
    {
        ele = elem[i];
        if(ele.localName=='select' && ele.value>0){
            if(tp!=ele.id.substr(0,3)){
                res = res + '\n';
                tp=ele.id.substr(0,3);
            }
            //find label
            lbl = document.getElementById('lbl'+ele.id.substr(2));
            if(lbl){
                res = res + lbl.innerHTML+' ==> '+ele.options[ele.selectedIndex].text+'\n';
            }
        }
    }
    if(res){
            alert(res);

    }
}
    </script>


    <div class="banner"><h2>Database-to-database Transfer</h2></div>
    <div id="page-inner">

<?php
			mysql_connection_overwrite(DATABASE);
			if(mysql_error()) {
				die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
			}
?>
<h2>FOR  ADVANCED USERS ONLY</h2>
<p>
This script reads records from a source database of H2 or H3 format, maps the record type, field type and term codes to new values,
and writes the records into the current logged-in database. It also transfers uploaded file records. It does not (at present) transfer tags and othe user data
The current database can already contain data, new records are appended and IDs adjsuted for records and files.
<br /><br />
If you find you are missing some record types, field types or terms from the Target database, click Save Settings, create the new record types/fields/terms that you need , then return to this function and click Load Settings. You may find it easier to import the record types you need from the Source database, as this also brings in all necessary fields and terms
<br /><br />
Make sure the target records and field types are compatible. <b>If you get the codes wrong, you will get a complete dog's breakfast in your target database ...</b>
</p>
<?php
			//print ">>>".(defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);

			$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);
			if($dt_SourceRecordID==0){  //getDetailTypeLocalID
				//add missed detail type
				mysql_query("INSERT INTO `defDetailTypes` ( dty_Name,  dty_Documentation,  dty_Type,  dty_HelpText,  dty_EntryMask,  dty_Status,
					dty_OriginatingDBID,  dty_NameInOriginatingDB,  dty_IDInOriginatingDB,  dty_DetailTypeGroupID,  dty_OrderInGroup,  dty_JsonTermIDTree,  dty_TermIDTreeNonSelectableIDs,
				dty_PtrTargetRectypeIDs,  dty_FieldSetRectypeID,  dty_ShowInLists,  dty_NonOwnerVisibility,  dty_Modified,  dty_LocallyModified) VALUES ('Original ID',' ','freetext','The original ID of the record in a source database from which these data were imported','','reserved',2,'Original ID',36,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0)");

				if (mysql_error()) {
				}else{
					$dt_SourceRecordID = mysql_insert_id();
					define('DT_ORIGINAL_RECORD_ID', $dt_SourceRecordID);
				}

			}
			if($dt_SourceRecordID==0){
?>
<hr><b>Original record IDs</b>
<p>
This data transfer function saves the original (source) record IDs in the <i>Original ID</i> field (origin code 2-36) for each record
<br/>This field does not exist in the database - please import it from the Heurist Core definitions database (db#2)
<br/>You do not need to add the <i>Original ID</i> field to each record type, it is recorded automatically as additional data.
<a href="../../admin/structure/import/selectDBForImport.php?db=<?=HEURIST_DBNAME ?>" title="Import database structure elements"
            target=_blank><b>Import structure elements</b></a> (loads in new tab)
<br/>(choose H3 Core definitions database (db#2), import the <i>Original ID container record</i>, then delete it - the required field remains)
<br/>Reload this page after importing the field
</p>
<hr/>
<?php
			}else{
?>
<hr><b>Original record IDs</b>
<p>
This data transfer function saves the original (source) record IDs in the <i>Original ID</i> field (origin code 2-36) for each record
<br />You do not need to add the <i>Original ID</i> field to each record type, it is recorded automatically as additional data
</p>
<?php
			}


			$sourcedbname = NULL;
			$password = NULL;
			$username = NULL;
			$user_id_insource = NULL;
			$user_workgroups = array();

			$is_h2 = (@$_REQUEST['h2']==1);

			$db_prefix = $is_h2?"heuristdb_" :$dbPrefix;

			// ----FORM 1 - SELECT THE SOURCE DATABASE --------------------------------------------------------------------------------

			if(!array_key_exists('mode', $_REQUEST) || !array_key_exists('sourcedbname', $_REQUEST)){

				print "<form name='selectdbtype' action='getRecordsFromDB.php' method='get'>";
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				if(!$is_h2){
					print "<input name='h2' value='1' type='hidden'>";
				}
				print "<input type='submit' value='Switch to H".($is_h2?"3":"2")." databases' /><br/>";
				print "</form>";

				print "<form name='selectdb' action='getRecordsFromDB.php' method='post'>";
				//  We have to use 'get', 'post' fails to transfer target database to step 2
				print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<input name='h2' value='".($is_h2?1:0)."' type='hidden'>";
				// print "Enter source database name (prefix added automatically): <input type='text' name='sourcedbname' />";
				print "<br/>Choose source database: <select id='db' name='sourcedbname'>";

				$list = mysql__getdatabases(false,null,null,$db_prefix);
				foreach ($list as $name) {
						print "<option value='$name'>$name</option>";
				}
				print "</select>";
				if(!$is_h2){
				print "<div style=\"padding:5px;\">";
				print "Username:&nbsp;<input type='text' name='username' id='username' size='20' class='in'>&nbsp;&nbsp;";
				print "Password:&nbsp;<input type='password' name='password' size='20' class='in'>&nbsp;&nbsp;";
				print "Use the same as current:&nbsp;<input type='checkbox' checked='checked' name='samelogin' value='1'/>";

				if(@$_REQUEST['loginerror']=='1'){
					print '<br/><font color="#ff0000">Incorrect Username / Password for source database</font>';
				}

				print "</div>";
				}
				print "&nbsp;&nbsp;<input type='submit' value='Continue'/>";
				print "</form>";
				exit;
			}

			// ----FORM 2 - MAPPINGS FORM ----------------------------------------------------------------------------------------

			$sourcedbname = $_REQUEST['sourcedbname'];

			if(!$is_h2){
				//verify user+password for source database
				$usecurrentlogin = (@$_REQUEST['samelogin']=='1');

				if($usecurrentlogin || (!(@$_REQUEST['username']  and  @$_REQUEST['password'])) ){
					$username = get_user_username();
					//take from database
					$res = mysql_query('select * from '.USERS_TABLE.' where '.USERS_USERNAME_FIELD.' = "'.mysql_real_escape_string($username).'"');
					$user = mysql_fetch_assoc($res);
					if ($user){
						$password = $user[USERS_PASSWORD_FIELD];
					}else{
						$password = "";
					}
					$needcrypt = false;

				} else {
					$username = $_REQUEST['username'];
					$password = $_REQUEST['password'];
					$needcrypt = true;//(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2');
				}

				mysql_connection_select($db_prefix.$sourcedbname);

				$res = mysql_query('select * from '.USERS_TABLE.' where '.USERS_USERNAME_FIELD.' = "'.mysql_real_escape_string($username).'"');


				$user = mysql_fetch_assoc($res);

   				if ( $user  &&
		 			$user[USERS_ACTIVE_FIELD] == 'y'  &&
		 			(($needcrypt && crypt($password, $user[USERS_PASSWORD_FIELD]) == $user[USERS_PASSWORD_FIELD]) ||
		 			 (!$needcrypt && $password == $user[USERS_PASSWORD_FIELD]))
		 			)
			    {
					$user_id_insource  = $user[USERS_ID_FIELD];

					$user_workgroups = mysql__select_array('sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
		                              'ugl_UserID='.$user_id_insource.' and grp.ugr_Type != "User" order by ugl_GroupID');

				}else{


					header('Location: ' . HEURIST_BASE_URL_V3 . 'import/direct/getRecordsFromDB.php?loginerror=1&db='.HEURIST_DBNAME);
					exit;
				}
				mysql_connection_overwrite(DATABASE);
			}


			if(@$_REQUEST['mode']=='2'){

				createMappingForm(null);

			} else

				// ---- visit #3 - SAVE SETTINGS -----------------------------------------------------------------

                if(@$_REQUEST['mode']=='3'){
                    saveSettings();
                } else
					// ---- visit #4 - LOAD SETTINGS -----------------------------------------------------------------

					if(@$_REQUEST['mode']=='4'){
						loadSettings();
					} else

						// ---- visit #5 - PROCESS THE TRANSFER -----------------------------------------------------------------

						if(@$_REQUEST['mode']=='5'){
							doTransfer();
							//transfer();
						}

						// ---- Create mapping form -----------------------------------------------------------------

            //get mapping from resaved config
            function getPresetId($config, $id){
				    if($config && array_key_exists($id, $config)){
					            return $config[$id];
				    }else{
					            return null;
			        }
			}

			function createMappingForm($config){

				global $sourcedbname, $db_prefix, $dbPrefix, $is_h2, $password, $username;

				$sourcedb = $db_prefix.$sourcedbname;

				print "<br>\n";
				print "Source database: <b>$sourcedb</b> <br>\n";

				if($is_h2){
					$res=mysql_query("select * from `$sourcedb`.records limit 1");
				}else{
					$res=mysql_query("select * from $sourcedb.sysIdentification");
				}
				if (!$res) {
					die ("<p>Unable to open source database <b>$sourcedb</b>. Make sure you have included prefix");
				}


				print "<form id='mapping' name='mappings' action='getRecordsFromDB.php' method='post'>";
				print "<input id='mode' name='mode' value='5' type='hidden'>"; // calls the transfer function
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<input name='h2' value='".($is_h2?1:0)."' type='hidden'>";
				print "<input name='sourcedbname' value='$sourcedbname' type='hidden'>";
				if(!$is_h2){
					print "<input name='username' value='$username' type='hidden'>";
					print "<input name='password' value='$password' type='hidden'>";
				}
				print "<input name='reportlevel' value='1' type='checkbox' checked='checked'>&nbsp;Report level: show errors only<br>";
				print "Check the code mappings below, then click  <input type='button' value='Import data' onclick='{document.getElementById(\"mode\").value=5; document.forms[\"mappings\"].submit();}'>\n";
				// alert(document.getElementById(\"mode\").value);

				print "<input type='button' value='Print mapping' onclick='{printMapping();}'>&nbsp;";
                print "<input type='button' value='Save settings' onclick='{document.getElementById(\"mode\").value=3; document.forms[\"mappings\"].submit();}'>";

				$filename = HEURIST_FILESTORE_DIR."settings/importfrom_".$sourcedbname.".cfg";

				if(file_exists($filename)){
					print "&nbsp;<input type='submit' value='Load settings' onclick='{document.getElementById(\"mode\").value=4; document.forms[\"mappings\"].submit();}'>\n";
				}

				print "<p><hr>\n";

				// --------------------------------------------------------------------------------------------------------------------
				// Get the record type mapping, by default assume that the code is unchanged so select the equivalent record type if available
				$allrectypes = getAllRectypeStructures(); //in current database
				$entnames = $allrectypes['names'];
				$seloptions = createOptions("or", $entnames);

				if($is_h2){
					$query1 = "SELECT DISTINCT `rec_type`,`rt_name`, '0' as cnt FROM `$sourcedb`.`records`,`$sourcedb`.`rec_types` where `rec_type`=`rt_id`";
				}else{
					$query1 = "SELECT rty_ID, rty_Name, count(rec_ID) as cnt, rty_OriginatingDBID, rty_IDInOriginatingDB  ".
					"from `$sourcedb`.`Records` ".
					"left join `$sourcedb`.`defRecTypes` on rec_RecTypeID=rty_ID ".
					"group by rty_ID";
				}
				$res1 = mysql_query($query1);
				if (mysql_num_rows($res1) == 0) {
					die ("<p><b>Sorry, there are no data records in this database, or database is bad format</b>");
				}
				print "<h3>Record type mappings</h3> &nbsp;<b>$sourcedb</b> &nbsp;[RT code] &nbsp;n=use count ==> <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
				print "<table>";
				while ($row1 = mysql_fetch_array($res1)) {
					$rt=$row1[0]; //0=rec_RecTypeID
					$cnt=$row1[2];
					$selopts = $seloptions;
                    $selectedId = null;
                    $bgcolor = "";

					if($config){
						$selectedId = getPresetId($config,"cbr".$rt);
                    }else{
                        if(!$is_h2){ //find by concept code
                            $selectedId = findByRtConceptCode($row1[3], $row1[4], $allrectypes);
                            if($selectedId){
                                $bgcolor = "style='background-color:#ccffcc;'";
                            }
                        }
                        if(!$selectedId){ //find the closest name
						    $selectedId = findClosestName($row1[1], $entnames);  //1=rty_Name
                            if($selectedId<0){ //exact match
                                $bgcolor = "style='background-color:#ccc;'";
                                $selectedId = -$selectedId;
                            }
					    }
                    }

					if($selectedId){
						$repl = "value='".$selectedId."'";
						$selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
					}

					print "<tr $bgcolor><td><label id='lblr$rt'>".$row1[1].(($is_h2)?"":" [$rt] n=$cnt ")."</label></td>".
					"<td>==> <select id='cbr$rt' name='cbr$rt' class='rectypes'><option id='or0' value='0'></option>".$selopts."</select></td></tr>\n";
				} // loop through record types
				print "</table>";


				// --------------------------------------------------------------------------------------------------------------------
				// Get the field type mapping, by default assume that the code is unchanged so select the equivalent detail type if available
				//create the string for combobox
				$alldettypes = getAllDetailTypeStructures(); //in current database
				$entnames = $alldettypes['names'];
				$seloptions = createOptionsDt($alldettypes);

				print "<h3>Field type mappings</h3> &nbsp;<b>$sourcedb</b>  &nbsp;[FT code - type] ==> <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
				if($is_h2){
					$query1 = "SELECT DISTINCT `rd_type`,`rdt_name`,`rdt_type` FROM `$sourcedb`.`rec_details`,`$sourcedb`.`rec_detail_types` ".
					"where `rd_type`=`rdt_id`";
				}else{
					$query1 = "SELECT DISTINCT `dtl_DetailTypeID`,`dty_Name`,`dty_Type`,`dty_OriginatingDBID`,`dty_IDInOriginatingDB` FROM `$sourcedb`.`recDetails`,`$sourcedb`.`defDetailTypes` ".
					"where `dtl_DetailTypeID`=`dty_ID`";
				}
				$res1 = mysql_query($query1);
				print "<table>";
				while ($row1 = mysql_fetch_array($res1)) {
					$ft=$row1[0]; //0=dtl_DetailTypeID

					$selopts = $seloptions;
                    $bgcolor = "";
                    $selectedId = null;

					//find the closest name
					if($config){
						$selectedId = getPresetId($config,"cbd".$ft);
					}else{

                        if(!$is_h2){ //find by concept code
                            $selectedId = findByDtConceptCode($row1[3], $row1[4], $alldettypes);
                            if($selectedId){
                                $bgcolor = "style='background-color:#ccffcc;'";
                            }
                        }

                        if(!$selectedId){	//find the closest name
						    $selectedId = findClosestName($row1[1], $entnames); //dty_Name
                            if($selectedId<0){ //exact match
                                $bgcolor = "style='background-color:#ccc;'";
                                $selectedId = -$selectedId;
                            }
					    }
                    }
					if($selectedId){
						$repl = "value='".$selectedId."'";
						$selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
					}

					print "<tr $bgcolor><td><label id='lbld$ft'>".$row1[1]." [ $ft - ".$row1[2]." ] </label></td>".  //2=dty_Type
					"<td>==> <select id='cbd$ft' name='cbd$ft' class='detailTypes'><option id='od0' value='0'></option>".
					$selopts."</select></td></tr>\n";
				} // loop through field types
				print "</table>";

				// --------------------------------------------------------------------------------------------------------------------

				createTermsOptions($config, 'enum');
				createTermsOptions($config, 'relation');

                // --------------------------------------------------------------------------------------------------------------
                createTagsOptions($config);

				print "</form>";

			}

			function createTermsOptions($config, $type){

				global $sourcedbname, $db_prefix, $dbPrefix, $is_h2;

				$sourcedb = $db_prefix.$sourcedbname;

				$allterms = getTerms(); //in current database
				$entnames = $allterms['termsByDomainLookup'][$type];
				foreach ($entnames as $id => $name) {
					$entnames[$id] = $name[0];
				}
				$seloptions = createOptions("ot", $entnames);


				print "<h3>Term mappings ($type"."s)"."</h3> &nbsp;<b>$sourcedb</b> &nbsp;[Term code]  ==> <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
				// Get the term mapping, by default assume that the code is unchanged so select the equivalent term if available
				if($is_h2){
					$query1 = "SELECT DISTINCT `rdl_id`,`rdl_id`,`rd_val` FROM `$sourcedb`.`rec_details`,`$sourcedb`.`rec_detail_lookups` ".
					"where (`rd_type`=`rdl_rdt_id`) AND (`rdl_value`=`rd_val`) AND (`rdl_related_rdl_id` is ".
					(($type!='enum')?"not":"")." null)";
				}else{
					$dt_type = ($type=='enum')?$type:'relationtype';

					$query1 = "SELECT DISTINCT `dtl_Value`,`trm_ID`,`trm_Label`,`trm_OriginatingDBID`,`trm_IDInOriginatingDB` FROM `$sourcedb`.`recDetails`,`$sourcedb`.`defTerms` ".
					"where (`dtl_Value`=`trm_ID`) AND (`dtl_DetailTypeID` in (select `dty_ID` from `$sourcedb`.`defDetailTypes` ".
					"where (`dty_Type`='$dt_type') ))";
				}
				$res1 = mysql_query($query1);
				print "<table>";
				while ($row1 = mysql_fetch_array($res1)) {
					$tt=$row1[0]; //0=trm_ID

					$selopts = $seloptions;
                    $bgcolor = "";
                    $selectedId = null;

					if($config){
						$selectedId = getPresetId($config,"cbt".$tt);
					}else{//	if(!$selectedId){	//find the closest name

                        if(!$is_h2){ //find by concept code
                            $selectedId = findTermDtConceptCode($row1[3], $row1[4], $allterms, $type);
                            if($selectedId){
                                $bgcolor = "style='background-color:#ccffcc;'";
                            }
                        }

                        if(!$selectedId){    //find the closest name
						    $selectedId = findClosestName($row1[2], $entnames); //trm_Label
                            if($selectedId<0){ //exact match
                                $bgcolor = "style='background-color:#ccc;'";
                                $selectedId = -$selectedId;
                            }

                        }
					}
					if($selectedId){
						$repl = "value='".$selectedId."'";
						$selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
					}

					print "<tr $bgcolor><td><label id='lblt$tt'>".$row1[2]." [ $tt ] </label></td>".
					"<td>==> <select id='cbt$tt' name='cbt$tt' class='terms'><option id='ot0' value='0'></option>".
					$selopts."</select></td></tr>\n";
				} // loop through terms
				print "</table>";
			}


            function getUsers($database=USERS_DATABASE){
                $res = mysql_query("select usr.ugr_ID, usr.ugr_Name,
                concat(usr.ugr_FirstName, ' ', usr.ugr_LastName) as fullname
        from `".$database."`.`sysUGrps` usr
        where usr.ugr_Enabled='y' and
        usr.ugr_Type='user' and
        usr.ugr_FirstName is not null and
        usr.ugr_LastName is not null and
        usr.ugr_IsModelUser=0
        order by fullname");

                $allusers = array();
                while ($row = mysql_fetch_row($res)) {
                    $allusers[$row[0]] = $row[1];
                }
                return $allusers;
            }
            //
            function createTagsOptions($config){

                global $sourcedbname, $db_prefix, $dbPrefix, $is_h2;

                $sourcedb = $db_prefix.$sourcedbname;

                $allusers = getUsers(); //in current database
                $seloptions = createOptions("us", $allusers);


                print "<h3>User mappings for tag (keyword) import"."</h3>".
                    " &nbsp;<b>$sourcedb</b> &nbsp;[User id] ==> <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
                // Get users with tags
                if($is_h2){
                    $query1 = "select usr.Id, usr.Username, usr.Realname as fullname, count(kwl_rec_id)
                            from `$sourcedb`.`Users` usr, `$sourcedb`.`keywords` kwd, `$sourcedb`.`keyword_links` kwl
                            where usr.IsModelUser=0 and kwd.kwd_usr_id=usr.Id and kwl.kwl_kwd_id=kwd.kwd_id
                            group by usr.Id
                            order by fullname";

                }else{

                    $query1 = "select usr.ugr_ID, usr.ugr_Name,
                            concat(usr.ugr_FirstName, ' ', usr.ugr_LastName) as fullname,
                            count(rtl_RecID)
                            from `$sourcedb`.`sysUGrps` usr, `$sourcedb`.`usrTags` kwd, `$sourcedb`.`usrRecTagLinks` kwl
                            where usr.ugr_Enabled='y' and usr.ugr_Type='user' and
                            usr.ugr_IsModelUser=0 and kwd.tag_UGrpID=usr.ugr_ID and kwl.rtl_TagID=kwd.tag_ID
                            group by usr.ugr_ID
                            order by fullname";
                }
                $res1 = mysql_query($query1);
                print "<table>";
                while ($row1 = mysql_fetch_array($res1)) {
                    $tt=$row1[0]; //0=usr_ID

                    $selopts = $seloptions;
                    $bgcolor = "";
                    $selectedId = null;

                    if($config){
                        $selectedId = getPresetId($config,"cbu".$tt);
                    }else{//    if(!$selectedId){    //find the closest name

                            $selectedId = findClosestName($row1[1], $allusers); //ugr_Name
                            if($selectedId<0){ //exact match
                                $bgcolor = "style='background-color:#ccc;'";
                                $selectedId = -$selectedId;
                            }
                    }
                    if($selectedId){
                        $repl = "value='".$selectedId."'";
                        $selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
                    }

                    print "<tr $bgcolor><td><label id='lblu$tt'>".$row1[1]." &nbsp;[ $tt ] </label></td>".
                    "<td>==> <select id='cbu$tt' name='cbu$tt' class='users'><option id='ou0' value='0'></option>".
                    $selopts."</select></td></tr>\n";
                } // loop through terms
                print "</table>";
            }
			// ---- Create options for HTML select -----------------------------------------------------------------
           function createOptionsDt($alldettypes){
               $pref = "od";
               $dttypes = $alldettypes['typedefs'];
               $fid_name = intval($dttypes['fieldNamesToIndex']["dty_Name"]);
               $fid_type = intval($dttypes['fieldNamesToIndex']["dty_Type"]);
               $res = "";

                foreach ($dttypes as $id => $def) {
                    if(is_numeric($id)){
                        $res = $res."<option id='".$pref.$id."' name='".$pref.$id."' value='".$id."'>".$def['commonFields'][$fid_name]." [ $id - ".$def['commonFields'][$fid_type]." ]</option>";
                    }
                }

                return $res;
            }

			function createOptions($pref, $names){

				$res = "";

				foreach ($names as $id => $name) {
					$res = $res."<option id='".$pref.$id."' name='".$pref.$id."' value='".$id."'>".$name." [$id]</option>";
				}

				return $res;
			}
            //
            // returns the id in array with the same concept id
            //
            function findByRtConceptCode($dbid, $id, $allrectypes){

                   if(intval($dbid)>0 && intval($id)>0){

                        $conceptid = $dbid."-".$id;

                        $rectypes = $allrectypes['typedefs'];
                        $fid = intval($allrectypes['typedefs']['commonNamesToIndex']["rty_ConceptID"]);

                        foreach ($rectypes as $id => $def) {
                            if(is_numeric($id) && $def['commonFields'][$fid]==$conceptid){
                                    return $id;
                            }
                        }
                   }
                   return null;
            }
            function findByDtConceptCode($dbid, $id, $alldettypes){

                   if(intval($dbid)>0 && intval($id)>0){

                        $conceptid = $dbid."-".$id;

                        $dttypes = $alldettypes['typedefs'];
                        $fid = intval($alldettypes['typedefs']['fieldNamesToIndex']["dty_ConceptID"]);

                        foreach ($dttypes as $id => $def) {
                            if(is_numeric($id) && $def['commonFields'][$fid]==$conceptid){
                                    return $id;
                            }
                        }
                   }
                   return null;
            }
            function findTermDtConceptCode($dbid, $id, $allterms, $type){

                   if(intval($dbid)>0 && intval($id)>0){

                        $conceptid = $dbid."-".$id;

                        $terms = $allterms['termsByDomainLookup'][$type];
                        $fid = intval($allterms['fieldNamesToIndex']["trm_ConceptID"]);

                        foreach ($terms as $id => $def) {
                            if(is_numeric($id) && $def[$fid]==$conceptid){
                                    return $id;
                            }
                        }
                   }
                   return null;
            }




			//
			// returns the id in array with closest similar name
			// return null if no similar names are found
			//
			function findClosestName($tocompare, $names){

				$tocompare = strtolower($tocompare);
				$minp = 55;
				$keepid = null;

				foreach ($names as $id => $name) {

					$name = strtolower($name);
					if($tocompare == $name){
						return -$id;
					}else{
						similar_text($tocompare, $name, $p);
						if($p > $minp){
							$keepid = $id;
							$minp = $p;
						}
					}

				}

				return $keepid;
			}

			// ---- SAVE CURRENT SETTINGS INTO FILE --------------------------------------------------------------

			function loadSettings(){
				global $sourcedbname;

				$filename = HEURIST_FILESTORE_DIR."settings/importfrom_".$sourcedbname.".cfg";

				$str = file_get_contents($filename);

				$arr = explode(",",$str);
				$config = array();
				foreach ($arr as $item2) {
					$item = explode("=",$item2);
					if(count($item)<2){
					}else{
						//print $item2."  ";
						$config["".$item[0]] = $item[1];
					}
				}

				print "SETTINGS ARE LOADED!!!!";
				createMappingForm($config);
			}

			function saveSettings(){
				global $sourcedbname;

				$config = array();
				$str = "";
				foreach ($_REQUEST as $name => $value) {
					$pos = strpos($name,"cb");
					if(is_numeric($pos) && $pos==0){
						$str  = $str.$name."=".$value.",";
						$config[$name] = $value;
					}
				}

				$folder = HEURIST_FILESTORE_DIR."settings/";

				if(!file_exists($folder)){
					if (!mkdir($folder, 0777, true)) {
						die('Failed to create folder for settings');
					}
				}

				$filename = $folder."importfrom_".$sourcedbname.".cfg";

				file_put_contents($filename, $str);

				print "SETTINGS ARE SAVED!!!!";
				createMappingForm($config);
			}

            function printMapSettings(){
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Database-to-database Transfer Mapping</title>
        <link rel=stylesheet href="../../common/css/global.css" media="all">
    </head>
    <body>
<?php

                $str = "";
                foreach ($_REQUEST as $name => $value) {
                    $pos = strpos($name,"cb");
                    if(is_numeric($pos) && $pos==0){
                        $str  = $name."=".$value;

                        print $str."<br/>";
                    }
                }

                print "</body></html>";
            }


			// ---- TRANSFER AND OTHER FUNCTIONS -----------------------------------------------------------------

			function doTransfer()
			{
				global $sourcedbname, $dbPrefix, $db_prefix, $is_h2, $user_id_insource, $user_workgroups;

				$sourcedb = $db_prefix.$sourcedbname;

				$rep_errors_only = (@$_REQUEST['reportlevel']=="1");


				echo "<p>Now copying data from <b>$sourcedb</b> to <b>". $dbPrefix.HEURIST_DBNAME. "</b><p>Processing: ";

				$terms_h2 = array();

                $user_rights = null;
				// Loop through types for all records in the database (actual records, not defined types)
				if($is_h2){
					//load all terms
					$query1 = "SELECT `rdl_id`,`rdl_value` FROM `$sourcedb`.`rec_detail_lookups`";
					$res1 = mysql_query($query1);
					while ($row1 = mysql_fetch_array($res1)) {
						$terms_h2[$row1[1]] = $row1[0];
					}

					$query1 = "SELECT DISTINCT (`rec_type`) FROM `$sourcedb`.`records`";

                    $user_rights = "rec_temporary=0";
				}else{
					$query1 = "SELECT DISTINCT (`rec_RecTypeID`) FROM $sourcedb.Records";

					$user_rights = ' (rec_FlagTemporary=0 and (rec_OwnerUGrpID='.$user_id_insource.' or (not rec_NonOwnerVisibility="hidden")';
					// rec_NonOwnerVisibility="public")
					if (!empty($user_workgroups)) {
							$user_rights = $user_rights.' or rec_OwnerUGrpID in (' . join(',', $user_workgroups) . ')))';
					}else{
							$user_rights = $user_rights.'))';
					}

				}
                if($user_rights){
                    $query1 = $query1." where ".$user_rights;
                }

				$res1 = mysql_query($query1);
				if(!$res1) {
					print "<br>Bad query for record type loop $res1 <br>";
					print "$query1<br>";
					die ("<p>Sorry ...</p>");
				}


				$added_records = array();
				$unresolved_pointers = array();
				$missed_terms = array();
				$missed_terms2 = array();

				/*$detailTypes = getAllDetailTypeStructures(); //in current database
				$detailTypes = $detailTypes['typedefs'];
				$fld_ind = $detailTypes['fieldNamesToIndex']['dty_Type']
				$detailTypes[$dttype][$fld_ind];*/

				print "<br>************************************************<br>Import records";
				print "<br>The following section adds records and allocates them new IDs.";
				if(!$rep_errors_only){
					print "<br>It reports this in the form Old ID => New ID";
				}




				// loop through the set of rectypes actually in the records in the database
				while ($row1 = mysql_fetch_array($res1)) {
					$rt = $row1[0];

					if(!array_key_exists('cbr'.$rt, $_REQUEST)) continue;

					$recordType = $_REQUEST['cbr'.$rt];
					if(intval($recordType)<1) {
						print "<br>Record type $rt is not mapped";
						ob_flush();flush(); // flush to screen
						continue;
					}

					//@todo - add record type name
					$rt_counter = 0;
					print "<br>Record type: $rt"; // tell user somethign is happening
					ob_flush();flush(); // flush to screen
					if($is_h2){
						$query2 = "select `rec_id`,`rec_url` from `$sourcedb`.`records` Where `$sourcedb`.`records`.`rec_type`=$rt";
					}else{
						$query2 = "select `rec_ID`,`rec_URL` from $sourcedb.Records Where $sourcedb.Records.rec_RecTypeID=$rt";
					}
                    if($user_rights){
                        $query2 = $query2." and ".$user_rights;
                    }

					$res2 = mysql_query($query2);
					if(!$res2) {
						print "<div  style='color:red;'>Bad query for records loop for source record type $rt</div>";
						print "<br>Query: $query2";
						ob_flush();flush(); // flush to screen
						continue;
						//die ("<p>Sorry ...</p>");
					}

					//special detailtype to keep original record id
					$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);


					while ($row2 = mysql_fetch_array($res2)) {

						//select details and create details array
						$rid = $row2[0]; //record id

						//				print "<br>".$rid."&nbsp;&nbsp;&nbsp;";

						if($is_h2){
							$query3 = "SELECT `rd_type`, `rdt_type`, `rd_val`, `rd_file_id`, astext(`rd_geo`)
							FROM `$sourcedb`.`rec_details` rd, `$sourcedb`.`rec_detail_types` dt where rd.`rd_type`=dt.`rdt_id` and rd.`rd_rec_id`=$rid order by `rd_type`";
						}else{
							$query3 = "SELECT `dtl_DetailTypeID`, `dty_Type`, `dtl_Value`, `dtl_UploadedFileID`, astext(`dtl_Geo`)
							FROM $sourcedb.`recDetails` rd, $sourcedb.`defDetailTypes` dt where rd.`dtl_DetailTypeID`=dt.`dty_ID` and rd.`dtl_RecID`=$rid order by `dtl_DetailTypeID`";
						}
						$res3 = mysql_query($query3);
						// todo: check query was successful
						if(!$res3) {
							print "<br>record ".$rid."&nbsp;&nbsp;&nbsp;<div  style='color:red;'>bad select of detail fields</div>";
							print "<br>query: $query3";
							ob_flush();flush(); // flush to screen
							continue;
							//die ("<p>Sorry ...</p>");
						}

						$unresolved_records = array();
						$details = array();
						$dtid = 0;
						$key = 0;
						$ind = 0;
						$values = array();

						//add special detail type 2-589 - reference to original record id
						if($dt_SourceRecordID>0){
							$details["t:".$dt_SourceRecordID] = array('0'=>$rid);
						}

						while ($row3 = mysql_fetch_array($res3)) {

							if($dtid != $row3[0]){
								if($key>0) {
									$details["t:".$key] = $values;
								}
								$dtid = $row3[0];
								$values = array();
								$ind;
							}

							if(!array_key_exists('cbd'.$row3[0], $_REQUEST)) continue;
							$key = $_REQUEST['cbd'.$row3[0]];
							if(intval($key)<1) {
								if($rt==52){//debug
									print "mapping not defined for detail (field) #".$dtid;
								}
								//mapping for this detail type is not specified
								continue;
							}

							$value = $row3[2];

							//determine the type of filedtype
							if($row3[1]=='enum' || $row3[1]=='relationtype'){

								if($is_h2){
									if(array_key_exists($value, $terms_h2)){
										$value = $terms_h2[$value];
									}else{
										if(array_search($value, $missed_terms)==false){
											array_push($missed_terms, $value);
										}
									}
								}
								$term = getDestinationTerm($value);

								if($term==null){

									$ind = array_search(intval($value), $missed_terms2);
									if ( count($missed_terms2)==0 || ($ind==0 && $missed_terms2[$ind]!=intval($value)) ) {
										array_push($missed_terms2, intval($value));
									}
									$value = null;
								}else{
									$value = $term;
								}

							}else if($row3[1]=='file'){

								if($is_h2){
									$value = copyRemoteFileH2($row3[3]); //returns new file id
								}else{
									$value = copyRemoteFile($row3[3]); //returns new file id
								}

							}else if($row3[1]=='geo'){

								$value = $value." ".$row3[4]; // string   geotype+space+wkt

							}else if($row3[1]=='relmarker'){

							}else if($row3[1]=='resource'){
								//find the id of record in destionation database among pairs of added records
								if(array_key_exists($value, $added_records)){
									$value = $added_records[$value];
								}else{
									array_push($unresolved_records, $key."|".$value);
									//print "<div  style='color:#ffaaaa;'>resource record#".$value." not found</div>";
									$value = null; //ingnore
								}
							}

							if($value!=null){
								$values[$ind] = $value;
								$ind++;
							}

						}//while by details

						//for last one
						if($key>0 && count($values)>0){
							$details["t:".$key] = $values;
						}

						$ref = null;

						//add-update Heurist record
						$out = saveRecord(null, //record ID
							$recordType,
							$row2[1], // URL
							null, //Notes
							null, //???get_group_ids(), //group
							null, //viewable    TODO: SHOULD BE A CHOICE
							null, //bookmark
							null, //pnotes
							null, //rating
							null, //tags
							null, //wgtags
							$details,
							null, //-notify
							null, //+notify
							null, //-comment
							null, //comment
							null, //+comment
							$ref,
							$ref,
							2	// import without check of record type structure
						);

						if (@$out['error']) {
							print "<br>Source record# ".$rid."&nbsp;&nbsp;&nbsp;";
							print "=><div style='color:red'> Error: ".implode("; ",$out["error"])."</div>";
						}else{

							$new_recid = $out["bibID"];
							$added_records[$rid] = $new_recid;

							$rt_counter++;

							if(count($unresolved_records)>0){
								$unresolved_pointers[$new_recid] = $unresolved_records;
							}

							if(!$rep_errors_only){
								print "<br>".$rid."&nbsp;=>&nbsp;".$out["bibID"];

								if (@$out['warning']) {
									print "<br>Warning: ".implode("; ",$out["warning"]);
								}
							}

						}
					}//while by record of particular record type

					if($rt_counter>0){
						print "&nbsp;&nbsp;=> added $rt_counter records";
					}

					ob_flush();flush(); // flush to screen

				} // end of loop for record types

				if(count($missed_terms)>0){
					print "<br><br>*********************************************************";
					print "<br>These terms IDs are not found in $sourcedb<br>";
					print implode('<br>',$missed_terms);
				}

				if(count($missed_terms2)>0){
					print "<br><br>*********************************************************";
					print "<br>Mapping for these terms IDs is not specified<br>";
					print implode('<br>',$missed_terms2);
				}

				if(count($unresolved_pointers)>0){

					$notfound_rec = array();

					print "<br><br>*********************************************************";
					print "<br>Finding and setting unresolved record pointers<br>";
					if(!$rep_errors_only){
						print "<br>It reports in form: source RecID => now target pointer RecID => in Rec Id<br>";
					}

					//resolve record pointers
					$inserts = array();
					foreach ($unresolved_pointers as $recid => $unresolved_records) {

						foreach ($unresolved_records as $code) {

							//print "<br>".$code;
							$aa = explode("|", $code);
							$dt_id = $aa[0];
							$src_recid = $aa[1];

							//print "    ".$dt_id."=".$src_recid;

							if(array_key_exists($src_recid, $added_records)){

								if(!$rep_errors_only){
									print "<br>".$src_recid."=>".$added_records[$src_recid]."=>".$recid;
								}

								array_push($inserts, "($recid, $dt_id, ".$added_records[$src_recid].", 1)");
							}else{
								if(array_search($src_recid, $notfound_rec)==false){
									array_push($notfound_rec, $src_recid." for ".$recid);
								}
							}
						}
					}

					if(count($notfound_rec)>0){
						print "<br>These records are specified as pointers in source database but they were not added into target database:<br>";
						print implode('<br>',$notfound_rec);
					}


					if (count($inserts)>0) {//insert all new details
						$query1 = "insert into $dbPrefix".HEURIST_DBNAME.".recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values " . join(",", $inserts);
						mysql_query($query1);
						print "<br><br>Total count of resolved pointers:".count($inserts);
					}
				}

                //IMPORT TAGS/KEYWORDS ==================================

                // get all target users
                // find corresspondant source users
                // find all tags with records for source user
                // add tags to target tag table
                // add tag links in target for all imported records

                // get all target users
                $allusers = getUsers(); //in target database
                $rkeys = array_keys($_REQUEST);
                $tags_corr = array();

                foreach($rkeys as $rkey) {
                    if(strpos($rkey,'cbu')===0 && @$allusers[$_REQUEST[$rkey]])
                    {

                    // find corresspondant source users
                    $source_uid = substr($rkey,3);
                    $target_uid = $_REQUEST[$rkey];

                    //find all tags with records for source user
                    if($is_h2){
                        $query1 = "select kwd.kwd_id, kwd.kwd_name, count(kwl_rec_id)
                                from `$sourcedb`.`keywords` kwd, `$sourcedb`.`keyword_links` kwl
                                where kwd.kwd_usr_id=$source_uid and kwl.kwl_kwd_id=kwd.kwd_id
                                group by kwd.kwd_id
                                order by kwd.kwd_id";

                    }else{

                        $query1 = "select kwd.tag_ID, kwd.tag_Text, count(rtl_RecID)
                                from `$sourcedb`.`usrTags` kwd, `$sourcedb`.`usrRecTagLinks` kwl
                                where kwd.tag_UGrpID=$source_uid and kwl.rtl_TagID=kwd.tag_ID
                                group by kwd.tag_ID
                                order by kwd.tag_ID";
                    }

                    //add tags to target tag table
                    $res1 = mysql_query($query1);
                    while ($row1 = mysql_fetch_array($res1)) {
                        $ress = get_ids_for_tags( array($row1[1]), true, $target_uid);

                        if(count($ress)>0){
                            $tags_corr[$row1[0]] = array($ress[0], $row1[1], 0, $target_uid);
                        }
                    }

                    }
                }//foreach users

                foreach ($added_records as $oldid => $newid) {

                    // find tag links in source database
                    if($is_h2){
                        $query1 = "select kwl.kwl_kwd_id
                                from `$sourcedb`.`keyword_links` kwl
                                where kwl.kwl_rec_id=".$oldid;
                    }else{

                        $query1 = "select kwl.rtl_TagID
                                from `$sourcedb`.`usrRecTagLinks` kwl
                                where kwl.rtl_RecID=".$oldid;
                    }
                   $res1 = mysql_query($query1);
                    while ($row1 = mysql_fetch_array($res1)) {

                        $newtag = @$tags_corr[$row1[0]];
                        if($newtag){

                            mysql_query('insert ignore into `'.USERS_DATABASE.
                                   '`.usrRecTagLinks (rtl_RecID, rtl_TagID, rtl_AddedByImport) '
                                  .' values ('.$newid.','.$newtag[0].',1)');
                            $tag_count = mysql_affected_rows();
                            if (mysql_error()) {

                            } else if ($tag_count > 0) {
                                    $tags_corr[$row1[0]][2]++;
                                    mysql_query('insert into `'.USERS_DATABASE.
                               '`.usrBookmarks (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID, bkm_AddedByImport) '
                               .'values( ' . $newtag[3] . ', now(), now(), '.$newid.',1 )');
                            }
                        }
                    }
                }
                //report
                print "<br />*********************************************************<br />";
                print "Tags added<br /><br />";
                foreach ($tags_corr as $oldid => $newtag) {
                    print $oldid." ".$newtag[1]." ==> ".$newtag[0].",  ".$newtag[2]." records<br />";
                }
                print "<br /><br />";


                //FINAL ==================================
                print "<br><br><br><h3>Transfer completed - <a href=getRecordsFromDB.php?db=" . HEURIST_DBNAME .
                  " title='Return to the main search page of the current database'><b>return to main page</b></a></h3>";

			} // function doTransfer

			/**
			* callback from saveRecord
			*
			* @param mixed $message
			*/
			function jsonError($message) {

				//mysql_query("rollback");
			}


			/**
			* copy file from another h3 instance and register it
			*
			* @param mixed $src_fileid - file id in source db
			* @return int - file id in destionation db, null - if copy and registration are failed
			*/
			function copyRemoteFile($src_fileid){

				global $sourcedbname, $dbPrefix, $db_prefix;
				$sourcedb = $db_prefix.$sourcedbname;

				$_src_HEURIST_FILESTORE_DIR =  HEURIST_UPLOAD_ROOT.$sourcedbname.'/';


				$res = mysql_query("select * from $sourcedb.`recUploadedFiles` where ulf_ID=".$src_fileid);
				if (mysql_num_rows($res) != 1) {
					print "<div  style='color:red;'>no entry for file id#".$src_fileid."</div>";
					return null; // nothing returned if parameter does not match one and only one row
				}

				$file = mysql_fetch_assoc($res);

				$need_copy = false;
				$externalFile = false;

				if ($file['ulf_FileName']) {
					$filename = $file['ulf_FilePath'].$file['ulf_FileName']; // post 18/11/11 proper file path and name
					$need_copy = ($file['ulf_FilePath'] == $_src_HEURIST_FILESTORE_DIR);
				} else if ($file['ulf_ExternalFileReference']) {
					$filename = $file['ulf_ExternalFileReference']; // post 18/11/11 proper file path and name
					$need_copy = false;
					$externalFile = true;
				} else {
					$filename = $_src_HEURIST_FILESTORE_DIR . $file['ulf_ID']; // pre 18/11/11 - bare numbers as names, just use file ID
					$need_copy = true;
				}

				if(!$externalFile && !file_exists($filename)){
					//check if this file is remote
					print "<div  style='color:red;'>File $filename not found. Can't register file</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}

				if(false && $externalFile){
					//@todo - copy external file and save
					$data = loadRemoteURLContent($filename);

					if (!$data) {
						print "<div  style='color:red;'>Error. Can't download $filename. Can't register URL</div>";
						ob_flush();flush(); // flush to screen
						return null;
					}
				}

				if($need_copy){
					$newfilename = HEURIST_FILESTORE_DIR.$file['ulf_OrigFileName'];
					//if file in source upload dirtectiry copy it to destionation upload directory
					if(!copy($filename, $newfilename)){
						print "<div  style='color:red;'>Can't copy file $fielname to ".HEURIST_FILESTORE_DIR."</div>";
						ob_flush();flush(); // flush to screen
						return null;
					}
					$filename = $newfilename;
				}

				//returns new file id in dest database
				if ($externalFile) {
					$jsonData = json_encode( array('remoteURL' => $filename,
													'ext' => $file['ulf_MimeExt'],
													'params' => $file['ulf_Parameters'] ? $file['ulf_Parameters']:null
												));
					$ret = register_external($jsonData, null, false);
				}else {
					$ret = register_file($filename, null, false);
				}
				if(intval($ret)>0){
					return $ret;
				}else{
					print "<div  style='color:red;'>Can't register file ".$filename."</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}
			}

			/**
			* copy file from another H2 instance and register it
			*
			* @param mixed $src_fileid - file id in source db
			* @return int - file id in destionation db, null - if copy and registration are failed
			*/
			function copyRemoteFileH2($src_fileid){

				global $sourcedbname, $dbPrefix, $db_prefix;
				$sourcedb = $db_prefix.$sourcedbname;

				$HEURIST_UPLOAD_ROOT_OLD =	@$_SERVER["DOCUMENT_ROOT"]."/HEURIST_FILESTORE/HEURIST_Vsn2_uploaded-heurist-files/";
				$_src_HEURIST_FILESTORE_DIR =  $HEURIST_UPLOAD_ROOT_OLD.$sourcedbname.'/';


				$res = mysql_query("select * from `$sourcedb`.`files` where `file_id`=".$src_fileid);
				if (mysql_num_rows($res) != 1) {
					print "<div  style='color:red;'>no entry for file id#".$src_fileid."</div>";
					return null; // nothing returned if parameter does not match one and only one row
				}

				$file = mysql_fetch_assoc($res);

				$filename = $_src_HEURIST_FILESTORE_DIR ."/". $file['file_id'];

				if(!file_exists($filename)){
					//check if this file is remote
					print "<div  style='color:red;'>File $fielname not found. Can't register it</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}

				$newfilename = HEURIST_FILESTORE_DIR.$file['file_orig_name'];
				//if file in source upload dirtectiry copy it to destionation upload directory
				if(!copy($filename, $newfilename)){
					print "<div  style='color:red;'>Can't copy file $fielname to ".HEURIST_FILESTORE_DIR."</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}
				$filename = $newfilename;

				//returns new file id in dest database
				$ret = register_file($filename, null, false);
				if(intval($ret)>0){
					return $ret;
				}else{
					print "<div  style='color:red;'>Can't register file ".$filename."</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}
			}

			/**
			* put your comment there...
			*
			* @param mixed $src_termid
			*/
			function getDestinationTerm($src_termid){

				if(!array_key_exists('cbt'.$src_termid, $_REQUEST)) {
					return null;
				}
				$key = $_REQUEST['cbt'.$src_termid];
				if(intval($key)<1) {
					//mapping for this term is not specified
					return null;
				}

				return $key;
			}
		?>
    </div>
	</body>
</html>
