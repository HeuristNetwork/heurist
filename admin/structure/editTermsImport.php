<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php
	define('SAVE_URI', 'disabled');

	define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	//require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
	require_once(dirname(__FILE__).'/saveStructure.php');

	if (! (is_logged_in()  &&  is_admin()  &&  HEURIST_SESSION_DB_PREFIX != "")) return;

	$parent_id = intval($_REQUEST['parent']);
	$domain = $_REQUEST['domain'];

	if (!$parent_id) return;

	$success_msg = null;
	$failure_msg = null;
	$res_array = null;

	//list($result_array, $failure_msg) = upload_file($parent_id);
	$res = upload_file($parent_id, $domain);
	if($res!=null){
		if(array_key_exists('error', $res)){
			$failure_msg = $res['error'];
		}else{
			$success_msg = "Record type imported";
			$res_array = json_format($res);
		}
	}
?>
<html>
 <head>

  <title>Import list of terms</title>
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/edit.css">
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/admin.css">

  <style type="text/css">
.success { font-weight: bold; color: green; margin-left: 3px; }
.failure { font-weight: bold; color: red; margin-left: 3px; }
.input-row div.input-header-cell {width:90px; vertical-align:baseline}
  </style>

  <!-- script type="text/javascript">
  	function onload(){
  		var result = null
	}
  </script -->
 </head>

 <body class="popup">

<script type="text/javascript">
  		var result = null;
<?php   if ($res_array) { ?>
			result = <?= $res_array ?>;
<?php	}  ?>
</script>


<?php   if ($success_msg) { ?>
  <div class="success"><?= $success_msg ?></div>
<?php	} else if ($failure_msg) { ?>
  <div class="failure"><?= $failure_msg ?></div>
<?php	} ?>

  <form action="editTermsImport.php?db=<?= HEURIST_DBNAME?>" method="post" enctype="multipart/form-data" border="0">
   <input type="hidden" name="parent" value="<?= $parent_id ?>">
   <input type="hidden" name="domain" value="<?= $domain ?>">
   <input type="hidden" name="uploading" value="1">


   <div class="input-row">
    	<div class="input-header-cell">Select file to import <br>(text file with one term per line)</div>
        <div class="input-cell"><input type="file" name="import_file" style="display:inline-block;"></div>
   </div>
   <div class="actionButtons" style="padding-right:80px">
   		Terms are imported as children of the currently selected term<p>
   		<input type="button" onclick="window.document.forms[0].submit();" value="Import" style="margin-right:10px">
   		<input type="button" value="Done" onClick="window.close(result);"></div>
   </div>
  </form>
 </body>
</html>
<?php

/***** END OF OUTPUT *****/

function upload_file($parent_id,$domain) {

	if (! @$_REQUEST['uploading']) return null;
	if (! $_FILES['import_file']['size']) return array('error'=>'Error occurred during import - file had zero size');


	$filename = $_FILES['import_file']['tmp_name'];
	$parsed = array();

	$row = 0;
	if (($handle = fopen($filename, "r")) !== FALSE) {
    	while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        	$num = count($data);
        	if($num>0){
        		$label = substr(trim($data[0]), 0, 399);
        		$len = strlen($label);
        		if($len>0 && $len<400){
					$desc = "";
        			for ($c=1; $c < $num; $c++) {
        				if($c>1) $desc = $desc.",";
        				$desc = $desc.$data[$c];
        			}
        			array_push($parsed, array($label,substr($desc, 0, 999),$domain,$parent_id,1));
        			$row++;
				}
			}
    	}
	}
	if($handle){
   		fclose($handle);
	}

	if($row==0) return array('error'=>'No one appropriate line found');

	$db = mysqli_connection_overwrite(DATABASE); //artem's

	$colNames = array('trm_Label','trm_Description','trm_Domain','trm_ParentTermID','trm_AddedByImport');

	$rv['parent'] = $parent_id;
	$rv['result'] = array(); //result

	foreach ($parsed as $ind => $dt) {
			$res = updateTerms($colNames, "1-1", $dt, $db);
			array_push($rv['result'], $res);
	}
	$rv['terms'] = getTerms();

	$db->close();

	return $rv;
}

?>
