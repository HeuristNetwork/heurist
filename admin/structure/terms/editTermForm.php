<?php

    /**
    * editTermForm.php: add individual term for given vocabulary
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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

    // User must be system administrator or admin of the owners group for this database
    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../common/php/getRecordInfoLibrary.php');
    require_once(dirname(__FILE__).'/../saveStructureLib.php');

    if (!is_admin()) {
        print "<html><head>";
        print '<meta http-equiv="content-type" content="text/html; charset=utf-8">';
        print "<link rel=stylesheet href='../../../common/css/global.css'></head>".
        "<body><div class=wrap><div id=errorMsg>".
        "<span>You must be logged in as system administrator to modify database structure</span>".
        "<p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
        " target='_top'>Log out</a></p></div></div></body></html>";
        return;
    }

    $parent_id = @$_REQUEST['parent'];
    $parent_name = "";
    $term_name = @$_REQUEST['name'];
    $term_desc = @$_REQUEST['description'];
    $term_code = @$_REQUEST['code'];
    $return_res = @$_REQUEST['return_res']?$_REQUEST['return_res']:""; //keep added vocabulary id
    $need_close = false;

    $local_message = "";

    if(@$_REQUEST['treetype']==null){
        $local_message = "Terms domain is not defined";
    }else if($parent_id==null){
        $local_message = "Parent vocabulary is not defined";
    }else if(@$_REQUEST['process']=="action"){

        if(@$_REQUEST['name']==null || $_REQUEST['name']==""){
            $local_message = "<div style='color:red'>Term (label) is mandatory - you cannot have a term without a label representing the term</div>";
        }else{

            $mysqli = mysqli_connection_overwrite(DATABASE); //artem's
            
            $res = updateTerms(array('trm_Label','trm_Description','trm_Domain','trm_ParentTermID','trm_Status','trm_Code'), null, //$parent_id."-1",
                array($_REQUEST['name'],$_REQUEST['description'],$_REQUEST['treetype'], ($parent_id>0?$parent_id:null) ,"open",$_REQUEST['code']), $mysqli);

            $mysqli->close();

            if(is_numeric($res)){

                $local_message = "<script>top.HEURIST.terms = \n" . json_format(getTerms(),true) . ";\n</script>".
                "<span style='color:green; padding-left:50px;'>Added ".(($parent_id>0)?'term':'vocabulary').": <b>".$term_name."</b></span>";
                if($return_res==""){
                    $return_res = ($parent_id==0)?$res:"ok";
                }
                if($parent_id==0){
                    $parent_id = $res;
                }
                $term_name = "";
                $term_desc = "";
                $term_code = "";

                $need_close = (@$_REQUEST['close_on_save']==1);

            }else{
                $local_message = "<span style='color:red; padding-left:10px; font-weight:bold;'>".$res."</span>"; //error
            }
        }
    }


    if($parent_id!=0){
        $terms = getTerms(true);
        $parent_name = $terms['termsByDomainLookup'][$_REQUEST['treetype']][$parent_id][0];
    }

?>

<html>

    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Add <?=($parent_id==0?"vocabulary":"term for: ".$parent_name)?></title>

        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">

        <style type="text/css">
            .dtyField {
                padding-bottom: 10px;
                padding-top: 3px;
                display:inline-block;
            }
            .dtyLabel {
                display: inline-block;
                width: 140px;
                text-align: right;
                padding-right: 10px;
            }
        </style>
        
        <script type="text/javascript">

            var context_return_res = "<?=$return_res ?>";

            function showOtherTerms(){
                top.HEURIST.util.popupURL(top, top.HEURIST.baseURL +
                    "admin/structure/terms/editTerms.php?popup=1&vocabid=<?=$parent_id ?>&treetype=<?=$_REQUEST['treetype'] ?>&db=<?=$_REQUEST['db'] ?>",
                    {
                        "close-on-blur": false,
                        "no-resize": false,
                        height:750,     // height and width of term tree editing popup
                        width: 1200,
                        callback: function(needTreeReload) {
                            if(!(context_return_res>0)){
                                context_return_res = 'ok';
                            }
                        }
                });
            }

            setTimeout(function(){
                <?php
                 if($need_close){
                      echo 'window.close(context_return_res);';
                 }else{
                      //echo 'document.getElementById("trmName").focus();';
                 }
                 ?>
            },500);

            function submitAndClose(){
                if(top.HEURIST.util.isempty(document.getElementById("trmName").value)){
                    window.close(context_return_res);
                }else{
                    document.getElementById('close_on_save').value = 1;
                    document.forms[0].submit();
                }
            }
        </script>

        
    </head>

    <body class="popup" onload="{document.getElementById('trmName').focus();}">
        <script type="text/javascript" src="../../../common/js/utilsUI.js"></script>
        <div>
            <form name="main" action="editTermForm.php" method="post"
                onsubmit="{document.getElementById('btnPanel').style.display = 'none'}">

                <input name="process" value="action" type="hidden" />
                <input name="treetype" value="<?=@$_REQUEST['treetype']?>" type="hidden" />
                <input name="db" value="<?=HEURIST_DBNAME?>" type="hidden" />
                <input name="return_res" value="<?=$return_res?>" type="hidden" />
                <input name="parent" value="<?=$parent_id?>" type="hidden" />
                <input name="close_on_save" id="close_on_save" value="0" type="hidden" />

                <span style="float:centre; margin-left:145px;">
                <?php
                    echo $local_message; // success message or warnings about duplicate labels
                ?>
                </span>

              <div style="padding-top:10px;">
                    <?=($parent_id==0?"<b>You are definining a top-level vocabulary</b>":"Adding to: <b>".$parent_name."</b>") ?>
                </div>


                <div class="dtyField">
                    <label class="dtyLabel" style="color:red; margin-top:10px;">
                        <?=($parent_id==0?"Vocabulary name":"Term (label)") ?>
                    </label>
                    <input id="trmName" name="name" style="width:150px;" value="<?=$term_name ?>"
                        title="Enter the term or concise label for the category. Terms pulldown will be alphabetic, use 01, 02 ... to force ordering"/>
                    <div style="padding-left:155;padding-top:3px; font-size: smaller;">
                        <?php
                            if($parent_id==0){
                        echo "A name for the vocabulary being defined.<br/>
                        A vocabulary is a list of terms, typically reflecting a particular attribute<br/>
                        or categorisation. Vocabularies may contain a hierarchy of terms.";
                            }else{
                        echo "The term or label describing the category. The label is the normal<br/>
                        way of expressing the term. Dropdowns are ordered alphabetically.<br />
                        Precede terms with 01, 02, 03 ... to control order if required.";
                            }
                        ?>
                    </div>
                </div>

                <div class="dtyField">
                    <label class="dtyLabel">
                        Description of <?=($parent_id==0?"vocabulary":"term") ?>
                    </label>
                    <input name="description" style="width:350px" value="<?=$term_desc?>"
                        title="Enter a concise but comprehesive description of the category represented by this term"/>
                    <div style="padding-left:155;padding-top:3px; font-size: smaller;">
                        A concise but comprehensive description of this
                        <?php
                        if($parent_id==0){
                            echo " vocabulary";
                        }else{
                            echo " term or category.";
                        }
                        ?>
                    </div>
                </div>

                <div class="dtyField" <?=($parent_id==0?'style="display:none;"':'')?>>
                    <label class="dtyLabel">Standard code</label>
                    <input name="code" style="width:80px" value="<?=$term_code?>"
                        title="Enter a textual or numeric code (such as a domain standard or international standard code) for this term"/>
                    <div style="padding-left:155;padding-top:3px; font-size: smaller;">
                        A domain or international standard code for this term or category<br/>
                        May also be used for a local code value to be used in importing data.
                    </div>
                </div>

                <div style="padding-top: 20px;" id="btnPanel">
                    <div style="float:left; padding-left:150px;">
                        <input id="btnEditTree" type="button" value="Edit terms tree" onClick="{showOtherTerms();}"
                            title="Add, edit and rearrange terms in the overall tree view of terms defined for this database"/>
                    </div>
                    <div style="float:right; padding-left:30px;">
                        <input id="btnSaveAndClose" type="button" value="Done" onClick="{submitAndClose();}"
                            title="Close this window and return to the selection of the vocabulary and terms for this field"/> &nbsp;&nbsp;
                    </div>
                    <div style="float:right; text-align: right; padding-right:20px;">
                        <input id="btnSave" type="submit" style="font-weight:bold !important; color:black; "
                            value="&nbsp;Add <?=($parent_id==0?"vocabulary":"term")?>&nbsp;"
                            title="Add <?=($parent_id==0?"top-level vocabulary":"the term to the current vocabulary")?>"/>&nbsp;&nbsp;
                    </div>
                </div>
            </form>
        </div>
    </body>
</html>