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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/**
* editTermForm.php
*
* add individual term for given vocabulary
*
* 2012-11-20
* @autor: Artem Osmakov
*
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
*
* @todo
**/

// User must be system administrator or admin of the owners group for this database
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/saveStructure.php');

if (!is_admin()) {
    print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to modify database structure</span><p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
    return;
}

$parent_id = @$_REQUEST['parent'];
?>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Heurist - Add <?=($parent_id==0?"vocabulary":"term")?></title>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
    	<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
   		<!--<link rel=stylesheet href="../../common/css/admin.css">-->
		<style type="text/css">
			.dtyField {
				padding-bottom: 3px;
				padding-top: 3px;
				display: inline-block;
			}
			.dtyLabel {
				display: inline-block;
				width: 75px;
				text-align: right;
				padding-right: 3px;
			}
		</style>

</head>
<body class="popup">
	<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
<?php

	$return_res = "";

	if(@$_REQUEST['domain']==null){
		echo "Terms domain is not defined";
	}else if($parent_id==null){
		echo "Parent vocabulary is not defined";
	}else if(@$_REQUEST['process']=="action"){

		if(@$_REQUEST['name']==null || $_REQUEST['name']==""){
			echo "<div style='color:red'>Display name is mandatory!</div>";
		}else{

			$db = mysqli_connection_overwrite(DATABASE); //artem's

			$res = updateTerms(array('trm_Label','trm_Description','trm_Domain','trm_ParentTermID','trm_Status','trm_Code'), $parent_id."-1",
					array($_REQUEST['name'],$_REQUEST['description'],$_REQUEST['domain'], ($parent_id==0?null:$parent_id) ,"open",$_REQUEST['code']), null);

			if(is_numeric($res)){

				echo "<script>top.HEURIST.terms = \n" . json_format(getTerms(),true) . ";\n</script>";
				echo "<div style='color:green'>New term has been added successfully</div>";
				$return_res = ($parent_id==0)?$res:"ok";
			}else{
				echo "<div style='color:red'>".$res."</div>"; //error
			}
		}
	}
?>
<script type="text/javascript">

	var return_res = "<?=$return_res ?>";

	function showOtherTerms(){

		top.HEURIST.util.popupURL(top, top.HEURIST.basePath + "admin/structure/editTerms.php?popup=1&vocabid=<?=$parent_id ?>&domain=<?=$_REQUEST['domain'] ?>&db=<?=$_REQUEST['db'] ?>",
		{
		"close-on-blur": false,
		"no-resize": false,
		height: 620,
		width: 900,
		callback: function(needTreeReload) {
			return_res = 'ok';
		}
		});
	}

		setTimeout(function(){document.getElementById("trmName").focus();},500);
</script>
<div>
        <form name="main" action="editTermForm.php" method="post">

        	<input name="process" value="action" type="hidden" />
        	<input name="domain" value="<?=$_REQUEST['domain']?>" type="hidden" />
        	<input name="db" value="<?=HEURIST_DBNAME?>" type="hidden" />
        	<input name="parent" value="<?=$_REQUEST['parent']?>" type="hidden" />

			<div class="dtyField"><label class="dtyLabel" style="color: red;">Display name:</label><input id="trmName" name="name" style="width:300px" value="<?=@$_REQUEST['name'] ?>" /></div>
			<div class="dtyField"><label class="dtyLabel">Description:</label><input name="description" style="width:300px" value="<?=@$_REQUEST['description'] ?>" /></div>
			<div class="dtyField"><label class="dtyLabel">Code:</label><input name="code" style="width:80px" value="<?=@$_REQUEST['code'] ?>" /></div>

			<div style="text-align: right; padding-top:8px;">
					<input id="btnEditTree" type="button" value="Edit terms" onClick="{showOtherTerms();}"/>
					<input id="btnSave" type="submit" value="Save"/>
					<input id="btnCancel" type="button" value="Close" onClick="{window.close(return_res)}" />
			</div>

		</form>
</div>
</body>
</html>