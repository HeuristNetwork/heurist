<?php

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
    print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to modify database structure</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
    return;
}
?>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Heurist - Add term</title>

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
<?php

	$ok = "";

	if(@$_REQUEST['domain']==null){
		echo "Terms domain is not defined";
	}else if(@$_REQUEST['parent']==null){
		echo "Parent vocabulary is not defined";
	}else if(@$_REQUEST['process']=="action"){

		if(@$_REQUEST['name']==null || $_REQUEST['name']==""){
			echo "<div style='color:red'>Display name is mandatory!</div>";
		}else{

			$db = mysqli_connection_overwrite(DATABASE); //artem's

			$res = updateTerms(array('trm_Label','trm_Description','trm_Domain','trm_ParentTermID','trm_Status','trm_Code'), $_REQUEST['parent']."-1",
					array($_REQUEST['name'],$_REQUEST['description'],$_REQUEST['domain'],$_REQUEST['parent'],$_REQUEST['status'],$_REQUEST['code']), null);

			if(is_numeric($res)){

				echo "<script>top.HEURIST.terms = \n" . json_format(getTerms(),true) . ";\n</script>";
				echo "<div style='color:green'>New term has been added successfully</div>";
				$ok = "'ok'";
			}else{
				echo "<div style='color:green'>".$res."</div>"; //error
			}
		}
	}
?>
<div>
        <form name="main" action="editTermForm.php" method="post">

        	<input name="process" value="action" type="hidden" />
        	<input name="domain" value="<?=$_REQUEST['domain']?>" type="hidden" />
        	<input name="db" value="<?=HEURIST_DBNAME?>" type="hidden" />
        	<input name="parent" value="<?=$_REQUEST['parent']?>" type="hidden" />

			<div class="dtyField"><label class="dtyLabel">Display name:</label><input name="name" style="width:300px"/></div>
			<div class="dtyField"><label class="dtyLabel">Description:</label><input name="description" style="width:300px"/></div>
			<div class="dtyField"><label class="dtyLabel">Code:</label><input name="code" style="width:80px"/></div>
            <div id="divStatus" class="dtyField"><label class="dtyLabel">Status:</label>
                <select class="dtyValue" name="status" onChange="editTerms.onChangeStatus(event)">
                    <option selected="selected">open</option>
                    <option>pending</option>
                    <option>approved</option>
                    <!-- option>reserved</option -->
                </select>
            </div>

			<div style="text-align: right; padding-top:8px;">
					<input id="btnSave" type="submit" value="Save Changes"/>
					<input id="btnCancel" type="button" value="Close" onClick="{window.close(<?=$ok?>)}" />
			</div>

		</form>
</div>
</body>
</html>