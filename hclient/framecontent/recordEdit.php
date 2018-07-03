<?php

    /**
    *  Standalone record edit page. It may be used separately or within widget (in iframe)
    * 
    *  Paramters
    *  q or recID - edit set of records defined by q(uery) or one record defiend by recID 
    *  
    *  otherwise it adds new record with 
    *  rec_rectype, rec_owner, rec_visibility, tag, t -  title, u - url, d - description
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2018 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    require_once(dirname(__FILE__)."/initPage.php");
    
/*    
// preprocess any description 
if (@$_REQUEST['bkmrk_bkmk_description']) {
    $description = $_REQUEST['bkmrk_bkmk_description'];

// use UNIX-style lines
    $description = str_replace("\r\n", "\n", $description);
    $description = str_replace("\r", "\n", $description);

// liposuction away those unsightly double, triple and quadruple spaces 
    $description = preg_replace('/ +/', ' ', $description);

// trim() each line 
    $description = preg_replace('/^[ \t\v\f]+|[ \t\v\f]+$/m', '', $description);
    $description = preg_replace('/^\s+|\s+$/s', '', $description);

// reduce anything more than two newlines in a row 
    $description = preg_replace("/\n\n\n+/s", "\n\n", $description);

    if (@$_REQUEST['version']) {
        $description .= ' [source: web page ' . date('Y-m-d') . ']';
    }
} else {
    $description = NULL;
}
// extract all id from descriptions for bibliographic references 
$dois = array();
if (preg_match_all('!DOI:\s*(10\.[-a-zA-Z.0-9]+/\S+)!i', $description, $matches, PREG_PATTERN_ORDER))
    $dois = array_unique($matches[1]);

$isbns = array();
if (preg_match_all('!ISBN(?:-?1[03])?[^a-z]*?(97[89][-0-9]{9,13}[0-9]|[0-9][-0-9]{7,10}[0-9X])\\b!i', $description, $matches, PREG_PATTERN_ORDER)) {
    $isbns = array_unique($matches[1]);
    if (! @$_REQUEST['rec_rectype']) $_REQUEST['rec_rectype'] = (defined('RT_BOOK')?RT_BOOK:0);
}

$issns = array();
if (preg_match_all('!ISSN(?:-?1[03])?[^a-z]*?([0-9]{4}-?[0-9]{3}[0-9X])!i', $description, $matches, PREG_PATTERN_ORDER)) {
    $issns = array_unique($matches[1]);
    if (! @$_REQUEST['rec_rectype']) $_REQUEST['rec_rectype'] = (defined('RT_JOURNAL_ARTICLE')?RT_JOURNAL_ARTICLE:0);
}
*/

/*
//  fix url to be complete with protocol and remove any trailing slash
if (@$_REQUEST['bkmrk_bkmk_url']  &&  ! preg_match('!^[a-z]+:!i', $_REQUEST['bkmrk_bkmk_url']))
    // prefix http:// if no protocol specified
    $_REQUEST['bkmrk_bkmk_url'] = 'http://' . $_REQUEST['bkmrk_bkmk_url'];

if (@$_REQUEST['bkmrk_bkmk_url']) {
    $burl = $_REQUEST['bkmrk_bkmk_url'];
    if (substr($burl, -1) == '/') $burl = substr($burl, 0, strlen($burl)-1);

    // look up the user's bookmark (usrBookmarks) table, see if they've already got this URL bookmarked -- if so, just edit it 
    $res = mysql_query('select bkm_ID, rec_ID from usrBookmarks left join Records on rec_ID=bkm_recID where bkm_UGrpID="'.mysql_real_escape_string($usrID).'"
                            and (rec_URL="'.mysql_real_escape_string($burl).'" or rec_URL="'.mysql_real_escape_string($burl).'/")');
    if (mysql_num_rows($res) > 0) {
        $bkmk = mysql_fetch_assoc($res);
        $bkm_ID = $bkmk['bkm_ID'];
        $rec_ID = $bkmk['rec_ID'];
        
        $url = HEURIST_BASE_URL . '?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$rec_ID;
        header('Location: ' . $url);    
        
        return;
    }

    $url = $_REQUEST['bkmrk_bkmk_url'];
}

//  Preprocess tags for workgroups ensuring that the user is a member of the workgroup
if (@$_REQUEST['tag']  &&  strpos($_REQUEST['tag'], "\\")) {
    // workgroup tag
    // workgroup is ...
    $tags = explode(',', $_REQUEST['tag']);
    $outTags = array();
    foreach ($tags as $tag) {
        $pos = strpos($tag, "\\");
        if ($pos !== false) {
            $grpName = substr($tag, 0, $pos);    //extract the name of the workgroup
            $res = mysql_query("select grp.ugr_ID from ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks where grp.ugr_Name='".mysql_real_escape_string($grpName)."' and ugl_GroupID=grp.ugr_ID and ugl_UserID=$usrID");
            if (mysql_num_rows($res) == 0) { //if the user is not a member
                $wg .= '&wgkwd=' . urlencode($tag);
                array_push($outTags, str_replace("\\", "", $tag));    //this removes the \ from wgname\tagname to create a personal tag of wgnametagname
            }else { // put the workgroup tag as is into the output tags
                array_push($outTags, $tag);
            }
        }
        else {
            array_push($outTags, $tag);
        }
    }
    if (count($outTags)) { //reset tag request param
        $_REQUEST['tag'] = join(',', $outTags);
    }
    else {
        unset($_REQUEST['tag']);
    }
}
*/    
?>
<!-- <?php echo PDIR;?> -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/rec_relation.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>ext/layout/jquery.layout-latest.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
        <!--script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script-->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/media_viewer.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>ext/yoxview/yoxview-init.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>common/js/temporalObjectLibrary.js"></script>
        
        
        <script type="text/javascript">
            var $container;
            // Callback function on page initialization
            function onPageInit(success){
                if(success){
                    
                    $container = $('<div>').appendTo($("body"));
                    
                    var isPopup = (window.hWin.HEURIST4.util.getUrlParameter('popup', window.location.search)==1);
                    
                    function __param(pname){
                        return window.hWin.HEURIST4.util.getUrlParameter(pname, window.location.search);
                    }
                    
                    //some values for new record can be passed as url parameters   
                    var rec_rectype = __param('rec_rectype');
                    var new_record_params = {};
                    if(rec_rectype>0){
                        new_record_params['RecTypeID'] = rec_rectype;
                        new_record_params['OwnerUGrpID'] = __param('rec_owner');
                        new_record_params['NonOwnerVisibility'] = __param('rec_visibility');
                        new_record_params['tag'] = __param('tag');
                        
                        new_record_params['Title'] = __param('t');
                        new_record_params['URL']   = __param('u');
                        
                        /*
                        $details = array();
                        new_record_params['title'] = __param('d');
                        new_record_params['title'] = __param('f');
                        
                        if(count($details)>0)
                            new_record_params['details'] = $details;
                        */
                        
                    }

//todo use ui.openRecordEdit                    
                    //hidden result list, inline edit form
                    var options = {
                        select_mode: 'manager',
                        edit_mode: 'editonly',
                        in_popup_dialog: isPopup,
                        new_record_params: new_record_params,
                        layout_mode:'<div class="ent_wrapper editor">'
                            + '<div class="ent_content_full recordList"  style="display:none;"/>'

                            + '<div class="ent_header editHeader"></div>'
                            + '<div class="editFormDialog ent_content">'
                                    + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                                    //+ '<div class="ui-layout-south><div class="editForm-toolbar"/></div>'
                            + '</div>'
                            + '<div class="ent_footer editForm-toolbar"/>'
                        +'</div>',
                        onInitFinished:function(){
                            
                            var q = __param('q');
                            var recID = __param('recID');
                            
                            if(!q && recID>0){
                                q = 'ids:'+recID;
                            }
                            
                            if(q){
                            
                                window.hWin.HAPI4.RecordMgr.search({q: q, w: "e",  //all records including temp
                                                limit: 100,
                                                needall: 1, //it means return all recors - no limits
                                                detail: 'ids'}, 
                                function( response ){
                                    //that.loadanimation(false);
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        
                                        var recset = new hRecordSet(response.data);
                                        if(recset.length()>0){
                                            $container.manageRecords('updateRecordList', null, {recordset:recset});
                                            $container.manageRecords('addEditRecord', recset.getOrder()[0]);
                                            //since recID may be resolved via recForwarding  (recID>0)?recID:recset.getOrder()[0]);
                                        }else{ // if(isPopup){
                                        
                                            var sMsg = ' does not exist in database or has status "hidden" for non owners';
                                            if(recID>0){
                                                sMsg = 'Record id#'+recID + sMsg;
                                            }else{
                                                sMsg = 'Record '+ sMsg;                                                    
                                            }
                                            
                                            window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, 
                                                {ok:'Close', title:'Record not found or hidden'}, 
                                                    {options:{close:function(){ window.close(); }}});
                                            
                                        }
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response, false, 
                                            {options:{close:function(){ if(isPopup){ window.close(); } }}});
                                    }

                                });
                            
                            }else{
                                
                                $container.manageRecords('addEditRecord',-1); //call widget method
                            }                            
                            
                        }
                    }
                    
                    $container.manageRecords( options ).addClass('ui-widget');
                }
            }
            
            function onBeforeClose(){
                $container.manageRecords('saveUiPreferences');
            }            
        </script>
    </head>
    <body>
    
    </body>
</html>
