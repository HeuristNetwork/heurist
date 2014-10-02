<?php

    /**
    * editTermsImport.php: importer for a text file of terms to be inserted into current term tree branch
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    define('SAVE_URI', 'disabled');

    // TODO: REMOVE define('dirname(__FILE__)', dirname(__FILE__));     // this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant

    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../common/php/getRecordInfoLibrary.php');
    require_once(dirname(__FILE__).'/../saveStructureLib.php');

    if (! (is_logged_in()  &&  is_admin()  &&  HEURIST_SESSION_DB_PREFIX != "")) return;

    $parent_id = intval($_REQUEST['parent']);
    $domain = $_REQUEST['domain'];

    $has_codes = (@$_REQUEST['has_codes']=="1");
    $has_descr = (@$_REQUEST['has_descr']=="1");

    if (!$parent_id) return;

    $success_msg = null;
    $failure_msg = null;
    $res_array = null;

    $res = upload_termsfile($parent_id, $domain, $has_codes, $has_descr);
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
        <title>Import list of terms into current branch</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/edit.css">
        <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/admin.css">

        <style type="text/css">
            .success { font-weight: bold; color: green; margin-left: 3px; }
            .failure { font-weight: bold; color: red; margin-left: 3px; }
            .input-row div.input-header-cell {width:90px; vertical-align:baseline}
        </style>
    </head>

    <body class="popup">

        <script type="text/javascript">
            var result = null;
            <?php   if ($res_array) { ?>
                result = <?=$res_array?>;
                <?php   }
            ?>
        </script>


        <?php   if ($success_msg) { ?>
            <div class="success"><?= $success_msg ?>
            </div>
            <?php       } else if ($failure_msg) { ?>
            <div class="failure"><?= $failure_msg ?>
            </div>
            <?php       }
        ?>

        <form action="editTermsImport.php?db=<?= HEURIST_DBNAME?>" method="post" enctype="multipart/form-data" style="border: 0px;">
            <input type="hidden" name="parent" value="<?= $parent_id ?>">
            <input type="hidden" name="domain" value="<?= $domain ?>">
            <input type="hidden" name="uploading" value="1">

            <div class="input-row">
                <div class="input-header-cell">Select file to import <br>(text file with one term per line)
                </div>
                <div class="input-cell"><input type="file" name="import_file" style="display:inline-block;">
                </div>
            </div>

            <div class="input-row">
                <div class="input-header-cell">Terms are followed on each line,  in this order, by comma-separated:
                </div>
                <div class="input-cell">
                    <input type="checkbox" name="has_codes" value="1" style="display:inline-block;">codes&nbsp;&nbsp;&nbsp;&nbsp;
                    <input type="checkbox" name="has_descr" value="1" style="display:inline-block;">extended labels
                </div>
            </div>

            <div class="actionButtons" style="padding-right:80px">
                Terms are imported as children of the currently selected term<p>
                <input type="button" onclick="window.document.forms[0].submit();" value="Import" style="margin-right:10px">
                <input type="button" value="Done" onClick="window.close(result);">
            </div>

        </form>
    </body>
</html>

<!-- END OF OUTPUT -->

<?php

    function upload_termsfile($parent_id, $domain, $has_codes, $has_descr) {

        if (! @$_REQUEST['uploading']) return null;
        if (! $_FILES['import_file']['size']) return array('error'=>'Error occurred during import - file had zero size');


        $filename = $_FILES['import_file']['tmp_name'];
        $parsed = array();

        $row = 0;
        if (($handle = fopen($filename, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $num = count($data);
                if($num>0){
                    if($has_codes){
                        $code = substr(trim($data[0]), 0, 99);
                        $ind = 1;
                    }else{
                        $code = '';
                        $ind = 0;
                    }

                    if($num>$ind){

                        $label = substr(trim($data[$ind]), 0, 399);
                        $len = strlen($label);
                        if($len>0 && $len<400){
                            $desc = "";
                            if($has_descr){
                                $ind++;
                                for ($c=$ind; $c < $num; $c++) {
                                    if($c>1) $desc = $desc.",";
                                    $desc = $desc.$data[$c];
                                }
                            }
                            array_push($parsed, array($code, $label,substr($desc, 0, 999),$domain,$parent_id,1));
                            $row++;
                        }
                    }
                }
            }
        }

        if($handle){
            fclose($handle);
        }

        if($row==0) return array('error'=>'No appropriate line found to process in import file');

        $mysqli = mysqli_connection_overwrite(DATABASE);

        $colNames = array('trm_Code','trm_Label','trm_Description','trm_Domain','trm_ParentTermID','trm_AddedByImport');

        $rv['parent'] = $parent_id;
        $rv['result'] = array(); //result

        foreach ($parsed as $ind => $dt) {
            $res = updateTerms($colNames, "1-1", $dt, $mysqli);
            array_push($rv['result'], $res);
        }
        $rv['terms'] = getTerms();

        $mysqli->close();

        return $rv;
    }

?>