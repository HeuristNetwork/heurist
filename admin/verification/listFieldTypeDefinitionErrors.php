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
    require_once('getFieldTypeDefinitionErrors.php');
    
    $lists = getInvalidFieldTypes(null);
    $dtysWithInvalidTerms = $lists["terms"];
    $dtysWithInvalidNonSelectableTerms = $lists["terms_nonselectable"];
    $dtysWithInvalidRectypeConstraint = $lists["rt_contraints"];
?>
<html>

	<head>
        <script src="../../common/js/utilsUI.js"></script>
		<script type=text/javascript>
        
            var Hul = top.HEURIST.util;

			function open_selected() {
				var cbs = document.getElementsByName('bib_cb');
				if (!cbs  ||  ! cbs instanceof Array)
				return false;
				var ids = '';
				for (var i = 0; i < cbs.length; i++) {
					if (cbs[i].checked)
					ids = ids + cbs[i].value + ',';
				}
				var link = document.getElementById('selected_link');
				if (!link)
				return false;
				link.href = '../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:' + ids;
				return true;
			}
            
            function onEditFieldType(dty_ID){

                var url = top.HEURIST.basePath + "admin/structure/editDetailType.html?db=<?= HEURIST_DBNAME?>";
                if(dty_ID>0){
                    url = url + "&detailTypeID="+dty_ID; //existing
                }else{
                    return;
                }

                top.HEURIST.util.popupURL(top, url,
                {   "close-on-blur": false,
                    "no-resize": false,
                    height: 680,
                    width: 700,
                    callback: function(context) {
                    }
                });
            }
		</script>

		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
		<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
		<style type="text/css">
			h3, h3 span {
				display: inline-block;
				padding:0 0 10px 0;
			}
			.msgline {
				line-height:2em;
			}
		</style>
	</head>

	<body class="popup">
    
    
        <script src="../../common/js/utilsLoad.js"></script>
        <script src="../../common/php/loadCommonInfo.php"></script>
    
		<div class="banner">
			<h2>Invalid Field Type Definition check</h2>
		</div>
		<div id="page-inner">
<?php
    if (count($dtysWithInvalidTerms)>0 || count($dtysWithInvalidNonSelectableTerms)>0 || count($dtysWithInvalidRectypeConstraint)>0){
?>
        
 The following field definitions have inconsistent data. To fix the problem please perform one of follwoing actions:<br /><br /> 
Click hyperlinked name of field name to open an edit form on that Field Type definition. Look edit and save the Terms or Pointer definitions.
<!-- <br />or<br />
2. Click "Auto Repair" button. -->
            <br />
			<hr/>
<?php

						foreach ($dtysWithInvalidTerms as $row) {
?>
                    <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has 
<?= count($row['invalidTermIDs'])?> invalid term ID<?=(count($row['invalidTermIDs'])>1?"s":"")?> 
(code: <?= join(",",$row['invalidTermIDs'])?>)
                    </div>
<?php
						}//for
						foreach ($dtysWithInvalidNonSelectableTerms as $row) {
?>
                    <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has 
<?= count($row['invalidTermIDs'])?> invalid non selectable term ID<?=(count($row['invalidNonSelectableTermIDs'])>1?"s":"")?> 
(code: <?= join(",",$row['invalidNonSelectableTermIDs'])?>)
                    </div>
<?php
						}
						foreach ($dtysWithInvalidRectypeConstraint as $row) {
?>
                    <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has 
<?= count($row['invalidTermIDs'])?> invalid record type constraint<?=(count($row['invalidRectypeConstraint'])>1?"s":"")?> 
(code: <?= join(",",$row['invalidRectypeConstraint'])?>)
                    </div>
<?php
						}

    }else{
        print "All field type definitions are valid";
    }
?>            
            
		</div>
	</body>
</html>

