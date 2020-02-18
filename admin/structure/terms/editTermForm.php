<?php
//@TODO use async instead of form submit
    /**
    * editTermForm.php: add individual term for given vocabulary (used in edit field type)
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
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
define('LOGIN_REQUIRED',1);   
define('PDIR','../../../');  //need for proper path to js and css    

require_once(dirname(__FILE__)."/../../../hclient/framecontent/initPageMin.php");
require_once(dirname(__FILE__).'/../saveStructureLib.php');

    $mysqli = $system->get_mysqli();

    $parent_id = @$_REQUEST['parent'];

    /*    
        print "<html><head>";
        print '<meta http-equiv="content-type" content="text/html; charset=utf-8">';
        print "<link rel=stylesheet href='../../../common/css/global.css'></head>".
        "<body><div class=wrap><div id=errorMsg>".
        "<span>You must be logged in as system administrator to modify database structure</span>".
        "<p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
        " target='_top'>Log out</a></p></div></div></body></html>";
        return;
    */

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
            $local_message = "<div class='ui-state-error'>Term (label) is mandatory - you cannot have a term without a label representing the term</div>";
        }else{

            $res = updateTerms(array('trm_Label','trm_Description','trm_Domain','trm_ParentTermID','trm_Status','trm_Code'), null, //$parent_id."-1",
                array($_REQUEST['name'],$_REQUEST['description'],$_REQUEST['treetype'], ($parent_id>0?$parent_id:null) ,"open",$_REQUEST['code']), null); //$mysqli

            if(is_numeric($res)){

                $local_message = "<script>window.hWin.HEURIST4.terms = \n" . json_encode(dbs_GetTerms($system)) . ";\n</script>".
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


    if($parent_id>0){
        $terms = dbs_GetTerms($system);
        if(@$terms['termsByDomainLookup'][$_REQUEST['treetype']][$parent_id])
            $parent_name = $terms['termsByDomainLookup'][$_REQUEST['treetype']][$parent_id][0];
    }

?>
<html>

    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Add <?=($parent_id==0?"vocabulary":"term for: ".$parent_name)?></title>

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
        
        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../../hclient/framecontent/initPageCss.php'; ?>

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

            function showOtherTerms(event){
                
                var type = '<?=$_REQUEST['treetype'] ?>';
                
                var sURL = '<?=HEURIST_BASE_URL?>admin/structure/terms/editTerms.php?popup=1&vocabid=<?=$parent_id ?>'
                    + '&treetype='+type+'&db=<?=HEURIST_DBNAME?>';
                    
                window.hWin.HEURIST4.msg.showDialog(sURL, {
                        "close-on-blur": false,
                        "no-resize": false,
                        title: (type=='enum')?'Manage Terms':'Manage Relationship types',
                        height: (type=='enum')?780:820,
                        width: 950,
                        afterclose: function() {
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
                if(window.hWin.HEURIST4.util.isempty(document.getElementById("trmName").value)){
                    window.close(context_return_res);
                }else{
                    document.getElementById('close_on_save').value = 1;
                    document.forms[0].submit();
                }
            }
            
            $(document).ready(function() {
                document.getElementById('trmName').focus();
                $('button').button();
                $('input').on("keypress",function(event){
                    var code = (event.keyCode ? event.keyCode : event.which);
                    if (code == 13) {
                        document.forms[0].submit();
                    }
                });
                
                
            });
                 
        </script>

        
    </head>

    <body class="popup">
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
                    <!-- onkeypress="top.HEURIST.util.onPreventChars(event)" -->
                    <input id="trmName" name="name" style="width:250px;" value="<?=$term_name ?>"
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
             </form>
                <div style="padding-top: 20px;" id="btnPanel">
<?php
    if ($system->is_admin()){
?>
                    <div style="float:left; padding-left:150px;">
                        <button id="btnEditTree" onClick="{showOtherTerms(event);}"  tabindex="-1"
                            title="Add, edit and rearrange terms in the overall tree view of terms defined for this database">
                            Edit terms tree
                        </button>
                    </div>
<?php        
    }
    if($local_message==''){
?>
                    <div style="float:right; padding-left:30px;">
                        <button id="btnSaveAndClose" onClick="{window.close('') }"  tabindex="0"
                            title="Close this window">
                            Cancel
                        </button>
                       &nbsp;&nbsp;
                    </div>
<?php }else{ ?>        
                    <div style="float:right; padding-left:30px;">
                        <button id="btnSaveAndClose" onClick="{submitAndClose();}"  tabindex="0"
                            title="Close this window and return to the selection of the vocabulary and terms for this field">
                            Done
                        </button>
                       &nbsp;&nbsp;
                    </div>
<?php        
    }
?>
                    <div style="float:right; text-align: right; padding-right:20px;">
                        <button id="btnSave" onClick="{document.forms[0].submit()}" style="font-weight:bold !important; color:black; "
                            title="Add <?=($parent_id==0?"top-level vocabulary":"the term to the current vocabulary")?>">
                            Add <?=($parent_id==0?"vocabulary":"term")?>
                        </button>
                        &nbsp;&nbsp;
                    </div>
                </div>
           
        </div>
    </body>
</html>