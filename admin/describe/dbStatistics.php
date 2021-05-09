<?php
//@TODO replace YUI dataTable to jQuery 

/**
* dbStatistics: shows a sortable list of databases on the server and their usage (record counts, access dates etc.)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

define('MANAGER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    
    
require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/utilities/utils_file.php');

$is_csv = (@$_REQUEST['csv']==1);

$starts_with = @$_REQUEST['start'];

if( $system->verifyActionPassword( @$_REQUEST['pwd'], $passwordForServerFunctions) ){
    $response = $system->getError();
    print $response['message'];
    exit();
}


$is_delete_allowed = (strlen(@$passwordForDatabaseDeletion) > 14);

set_time_limit(0); //no limit

$mysqli = $system->get_mysqli();
$dbs = mysql__getdatabases4($mysqli, true, $starts_with);

$sysadmin = $system->is_system_admin();

// Force system admin rights
/*
if($sysadmin){
    startMySession();
    $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_systemadmin'] = '1';
    session_write_close();
}
*/

/**
* Selects the value after a query
* @param mixed $query Query to execute
*/
function mysql__select_val($query) {
    global $mysqli;
    
    $res = mysql__select_value($mysqli, $query);
    if ($res==null) $res = 0;
    
    return $res;
}

/**
* NOT USED HERE
* 
* Calculates the directory size
* @param mixed $dir Directory to check
* 
* @todo move to utilities/utils_file.php
*/
function dirsize($dir)
{
    global $mysqli;
    
    @$dh = opendir($dir);
    $size = 0;
    while ($file = @readdir($dh))
    {
        if ($file != "." and $file != "..")
        {
            $path = $dir."/".$file;
            if (is_dir($path))
            {
                $size += dirsize($path); // recursive in sub-folders
            }
            elseif (is_file($path))
            {
                $size += filesize($path); // add file
            }
        }
    }
    @closedir($dh);
    return $size;
}

if($is_csv){                                                  
    $fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
    if (false === $fd) {
        die('Failed to create temporary file');
    } 
    $record_row = array('Name','Reg ID','Records','Files(MB)','DB Vsn','Data updated','Structure modified','Owner','eMail','Institution');
    fputcsv($fd, $record_row, ',', '"');
}else{
    $arr_databases = array();
}

$i = 0;
foreach ($dbs as $db){

    //ID  Records     Files(MB)    RecTypes     Fields    Terms     Groups    Users   Version   DB     Files     Modified    Access    Owner   Deleteable
//error_log(substr($db, 4)); 
    if(!hasTable($mysqli, 'sysIdentification',$db)) return;

    $record_row = array (substr($db, 4),
    mysql__select_val("select cast(sys_dbRegisteredID as CHAR) from ".$db.".sysIdentification where 1"),
    mysql__select_val("select count(*) from ".$db.".Records where (not rec_FlagTemporary)"),
    0,//mysql__select_val("select count(*) from ".$db.".recDetails"),
    /* Removed Ian 10/12/16 to speed up - very slow on USyd server with very large # of DBs. See additional comment-outs below
    mysql__select_val("select count(*) from ".$db.".defRecTypes").",".
    mysql__select_val("select count(*) from ".$db.".defDetailTypes").",".
    mysql__select_val("select count(*) from ".$db.".defTerms").",".
    mysql__select_val("select count(*) from ".$db.".sysUGrps where ugr_Type='workgroup'").",".
    mysql__select_val("select count(*) from ".$db.".sysUGrps where ugr_Type='user'").",".
    */
    mysql__select_val("select concat_ws('.',cast(sys_dbVersion as char),cast(sys_dbSubVersion as char)) "
        ." from ".$db.".sysIdentification where 1"),
    /*
    mysql__select_val("SELECT Round(Sum(data_length + index_length) / 1024 / 1024, 1)"
    ." FROM information_schema.tables where table_schema='".$db."'").",".
    round( (dirsize(HEURIST_FILESTORE_ROOT . substr($db, 4) . '/')/ 1024 / 1024), 1).",".
    */
    strtotime(mysql__select_val("select max(rec_Modified)  from ".$db.".Records")),
    //mysql__select_val("select max(ugr_LastLoginTime)  from ".$db.".sysUGrps") );
    strtotime(mysql__select_value($mysqli, "select max(rst_Modified) from ".$db.".defRecStructure")) );

    $owner = mysql__select_row($mysqli, "SELECT concat(ugr_FirstName,' ',ugr_LastName),ugr_eMail,ugr_Organisation ".
        "FROM ".$db.".sysUGrps where ugr_id=2");
        
    //$sz = folderSize( HEURIST_FILESTORE_ROOT.substr($db, 4).'/');
    //$record_row[3] = $sz>0?round($sz/1048576):0;
        
    if($is_csv){    
        $record_row[] = $owner[0];
        $record_row[] = $owner[1];
        $record_row[] = $owner[2];
        fputcsv($fd, $record_row, ',', '"');
    }else{
        $record_row[] = implode(' ', $owner);
        $record_row[] = $sysadmin;
       
        $aitem_quote = function($n)
        {
            return is_numeric($n) ?$n :('"'.str_replace('"','\"',$n).'"');
        };        
        
        $record_row = array_map($aitem_quote, $record_row);
        $arr_databases[] = implode(',',$record_row);//'"'.implode('","',  str_replace('"','',$record_row)   ).'"';
    }
    
    $i++;
    //if($i>10) break;
}//foreach

if($is_csv){

        /*
        $filename = 'ServerUsageStatistics.csv';
        
        rewind($fd);
        $out = stream_get_contents($fd);
        fclose($fd);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.$filename);
        header('Content-Length: ' . strlen($out));
        exit($out);
        */
        
        $zipname = 'ServerUsageStatistics.zip';
        $destination = tempnam("tmp", "zip");
        
        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::OVERWRITE)) {
            print "Cannot create zip $destination";    
        }else{
            // return to the start of the stream
            rewind($fd);
            $content = stream_get_contents($fd);
            // add the in-memory file to the archive, giving a name
            $zip->addFromString('ServerUsageStatistics.csv',  $content);
            //close the file
            fclose($fd);
            // close the archive
            $zip->close();
        }
        
        if(@file_exists($destination)>0){
        
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename='.$zipname);
            header('Content-Length: ' . filesize($destination));
            readfile($destination);
            // remove the zip archive
            unlink($destination);    
        }else{
            print "Zip archive ".$destination." doesn't exist";
        }
        
    
}else{
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Database statistics for this server</title>

        <link rel="icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

        <!-- YUI -->
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/yui/2.8.2r1/build/fonts/fonts-min.css" />
        <script type="text/javascript" src="<?php echo PDIR;?>external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/yui/2.8.2r1/build/element/element-min.js"></script>

        <!-- DATATABLE DEFS -->
        <link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/yui/2.8.2r1/build/datatable/assets/skins/sam/datatable.css">
        <!-- datatable Dependencies -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/yui/2.8.2r1/build/datasource/datasource-min.js"></script>
        <!-- Source files -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/yui/2.8.2r1/build/datatable/datatable-min.js"></script>
        <!-- END DATATABLE DEFS-->

        <!-- TOOLTIP -->
        <!-- <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.9.0/build/container/assets/container.css"> -->
        <script src="<?php echo PDIR;?>external/yui/2.8.2r1/build/container/container-min.js"></script>

        <!-- jQuery UI -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
        
        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>

        <!-- Heurist JS -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>

    </head>

    <body class="popup yui-skin-sam">
        <div id="titleBanner" class="banner"><h2>Databases statistics</h2></div>
        <div id="page-inner">
            <?php echo "System admin: <a class='dotted-link' href='mailto:" .HEURIST_MAIL_TO_ADMIN. "'>" .HEURIST_MAIL_TO_ADMIN. "</a>"; ?>
            <?php if($is_delete_allowed) { /*&& $sysadmin*/?> <button id="deleteDatabases" onclick="deleteDatabases()">Delete selected databases</button><br><br> <?php } ?>
            <div id="tabContainer"></div>
        </div>

        <?php
         if(true || $sysadmin) { 
         ?>
            <!-- Database verification dialog -->
            <div id="db-verification" title="Verification" style="display: none">
                <div id="div-pw">
                    <span>Deletion password:</span>
                    <input id="db-password" type="password" placeholder="password">
                    <button id="pw-check" onclick="checkPassword()">Submit</button>
                </div>

                <div id="authorized" style="display: none">
                    <div>Correct password</div>
                    <div>Starting to delete selected databases</div>

                    <div class="progress-bar">
                        <span class="progress">0/0</span>
                        <div class="bar" value="0" max="100"></div>
                    </div>
                </div>

            </div>
         <?php } ?>

        <!-- Table generation script -->
        <script type="text/javascript">
            //v2
            var showTimer,hideTimer;
            var arr = [
                <?php
                    foreach ($arr_databases as $db) {
                        print '['.$db.'],'.PHP_EOL;
                    }
                ?>
            ];

            var myDataSource = new YAHOO.util.LocalDataSource(arr, {
                responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
                responseSchema : {
                    fields: ["dbname","db_regid","cnt_recs", "cnt_vals", 
//removed by Ian                    "cnt_rectype","cnt_fields", "cnt_terms", "cnt_groups", "cnt_users", 
                    "db_version",
//removed by Ian                    "size_db", "size_file", 
                    "date_mod", "date_login","owner","deleteable"]
            }});

            function __format_date(d){
                var val = parseInt(d);
                if(val>0){
                    var epoch = new Date(0);
                    epoch.setSeconds(val);
                    return epoch.toISOString().replace('T', ' ').replace('.000Z','');                        
                    redate;
                }else{
                    return '';
                }
            }
            
            
            var myColumnDefs = [
                { key: "dbname", label: "Name", sortable:true, className:'left', formatter: function(elLiner, oRecord, oColumn, oData) {
                    elLiner.innerHTML = "<a href='../../?db="+oRecord.getData('dbname')+"' class='dotted-link' target='_blank'>"+oRecord.getData('dbname')+"</a>";
                }},
                { key: "db_regid", label: "Reg ID", sortable:true, className:'right'},
                { key: "cnt_recs", label: "Records", sortable:true, className:'right'},
                { key: "cnt_vals", label: "Files(MB)", sortable:true, className:'right'},
                /* Removed to increase speed - see equivalent section commented out above
                { key: "cnt_rectype", label: "RecTypes", sortable:true, className:'right'},
                { key: "cnt_fields", label: "Fields", sortable:true, className:'right'},
                { key: "cnt_terms", label: "Terms", sortable:true, className:'right'},
                { key: "cnt_groups", label: "Groups", sortable:true, className:'right'},
                { key: "cnt_users", label: "Users", sortable:true, className:'right'},
                */
                { key: "db_version", label: "DB Vsn", sortable:true, className:'right'},
                /* removed by Ian
                { key: "size_db", label: "DB (MB)", sortable:true, className:'right'},
                { key: "size_file", label: "Files (MB)", sortable:true, className:'right'},
                */
                { key: "date_mod", label: "Data updated", sortable:true, 
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        elLiner.innerHTML = __format_date(oRecord.getData('date_mod'));
                }},
                { key: "date_login", label: "Structure modified", sortable:true,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        elLiner.innerHTML = __format_date(oRecord.getData('date_login'));
                }},
                { key: "owner", label: "Owner", width:200, formatter: function(elLiner, oRecord, oColumn, oData){
                    elLiner.innerHTML = "<div style='max-width:100px' class='three-lines' title='"+oRecord.getData('owner')+"'>"+oRecord.getData('owner')+"</div>";
                }}
                <?php if($is_delete_allowed) { /*$sysadmin && */?>
                    ,{ key: 'deleteable', label: 'Delete', className: 'right', formatter: function(elLiner, oRecord, oColumn, oData) {
                        elLiner.innerHTML = '<input type=\"checkbox\" value=\"'+oRecord.getData('dbname')+'\">';
                        
                        $(elLiner).click(function(e){
                                    var sel_cnt = getSelectedDatabases();
                                    if(sel_cnt>25){
                                        e.cancelBubble = true;
                                        if (e.stopPropagation) e.stopPropagation();
                                        e.preventDefault();
                                        alert('Max 25 databases allowed to be deleted at one time.');
                                    }
                            });
                    }}
                <?php } ?>
            ];

            var dt = new YAHOO.widget.DataTable("tabContainer", myColumnDefs, myDataSource);
            var tt = new YAHOO.widget.Tooltip("myTooltip");

            dt.on('cellMouseoverEvent', function (oArgs) {
                if (showTimer) {
                    window.clearTimeout(showTimer);
                    showTimer = 0;
                }

                var target = oArgs.target;
                var column = this.getColumn(target);
                if (column.key == 'dbname') {
                    var record = this.getRecord(target);
                    var description = record.getData('owner') || '';
                    if(description!=''){
                        var xy = [parseInt(oArgs.event.clientX,10) + 10 ,parseInt(oArgs.event.clientY,10) + 10 ];

                        showTimer = window.setTimeout(function() {
                            tt.setBody(description);
                            tt.cfg.setProperty('xy',xy);
                            tt.show();
                            hideTimer = window.setTimeout(function() {
                                tt.hide();
                                },5000);
                            },500);
                    }
                }
            });
            dt.on('cellMouseoutEvent', function (oArgs) {
                if (showTimer) {
                    window.clearTimeout(showTimer);
                    showTimer = 0;
                }
                if (hideTimer) {
                    window.clearTimeout(hideTimer);
                    hideTimer = 0;
                }
                tt.hide();
            });
        </script>

        <?php if($is_delete_allowed) { ?>
            <!-- Delete databases scipt -->
            <script>
                var databases = [];
                var password;

                /**
                * Returns the values of checkboxes that have been selected
                */
                function getSelectedDatabases() {
                    this.databases = [];
                    var checkboxes = document.getElementsByTagName("input");
                    if(checkboxes.length > 0) {
                        for(var i=0; i<checkboxes.length; i++) {
                            if(checkboxes[i].checked) {
                                this.databases.push(checkboxes[i].value);
                            }
                        }
                    }
                    return this.databases.length;
                }

                /**
                * Makes an API call to delete each selected database
                */
                function deleteDatabases() {
                    // Determine selected databases
                    getSelectedDatabases();
                    if(this.databases.length>25){
                        alert("You selected "+this.databases.length+" databases to be deleted. Max 25 allowed at one time.");
                        return false;
                    }else if(this.databases.length == 0) {
                        alert("Select at least one database to delete");
                        return false;
                    }
                    $("#div-pw").show();
                    $("#authorized").hide();
                    var submit = document.getElementById("pw-check");
                    submit.disabled = false;

                    // Verify user
                    var $dlg = $("#db-verification").dialog({
                        autoOpen: false,
                        modal: true,
                        width: '550px',
                        position: { my: "left top", at: "left+150 top+150", of: window }
                    })
                    .dialog("open");

                    //$dlg.parent('.ui-dialog').css({top:150,left:150});
                    
                    //$(document.body).scrollTop(0);

                }

                /**
                * Checks if the database deletion password is correct
                */
                function checkPassword() {
                    var submit = document.getElementById("pw-check");
                    submit.disabled = true;
                    
                    this.password = document.getElementById("db-password").value;
                    
                    var url = '<?php echo HEURIST_BASE_URL; ?>admin/setup/dboperations/deleteDB.php';
                    var request = {pwd: password, db:'<?php echo HEURIST_DBNAME;?>' };
                    
                    // Authenticate user
                    window.hWin.HEURIST4.util.sendRequest(url, request, null,
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //submit.parentNode.removeChild(submit);
                                $("#div-pw").hide();
                                var ele = $("#authorized");
                                ele.find('div.reps').remove();
                                ele.find('div.ui-state-error').remove();
                                ele.show();
                                updateProgress(0);
                                postDeleteRequest(0); //start deletion
                            }else{
                                submit.disabled = false;
                                response.sysmsg = 1;
                                window.hWin.HEURIST4.msg.showMsgErr(response, false,
                                {position: { my: "left top", at: "left+150 top+150", of: window }});
                            }
                        }
                    );
                }
                /**
                * Posts a delete request to the server for the database at the given index
                */
                function postDeleteRequest(i) {
                    if(i < databases.length) {
                        // Delete database
                        if('<?php echo HEURIST_DBNAME;?>'==databases[i]){

                            $("#authorized").append("<div>Current db "+databases[i] 
                            +" is skipped</div><div style='margin-top: 5px; width: 100%; border-bottom: 1px solid black; '></div>");
                            postDeleteRequest(i+1);
                            updateProgress(i+1);
                            
                        }else{
                        
                            var url = '<?php echo HEURIST_BASE_URL; ?>admin/setup/dboperations/deleteDB.php';
                            var request = {pwd: password, 
                                           create_archive: 1,
                                           db: '<?php echo HEURIST_DBNAME;?>',
                                           database: databases[i]};
                        
                            window.hWin.HEURIST4.util.sendRequest(url, request, null,
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        $("#authorized").append('<div class="reps">'+databases[i]
                                        +"</div><div style='margin-top: 5px; width: 100%; border-bottom: 1px solid black; '></div>");
                                        postDeleteRequest(i+1);
                                        updateProgress(i+1);
                                    }else{
                                        
                                        var msg = window.hWin.HEURIST4.msg.showMsgErr(response, false,
                                            {position: { my: "left top", at: "left+150 top+150", of: window }});
                                        
                                        $("#authorized").append('<div class="ui-state-error" style="padding:4px;">'
                                                    +databases[i]+' '+msg +"</div>");
                                        
                                    }
                                }
                            );
                        }
                        
                    }else{
                        // All post-requests have finished.
                        $("#authorized").append("<div style='margin-top: 10px'>The selected databases have been deleted!</div>");
                        $("#authorized").append("<div style='font-weight: bold'>Please reload the page if you want to do delete more databases</div>");
                    }
                }

                /**
                * Updates the progress bar
                */
                function updateProgress(count) {
                    $(".progress").text(count+"/"+databases.length);
                    $(".bar").attr("value", (count*100/databases.length));
                }
                
                $(document).ready(function() {
                    
                    if(!window.hWin.HR){
                        window.hWin.HR = function(token){return token};
                    }
                   $('button').button();
                });                
            </script>
            <?php } ?>
    </body>
</html>
<?php 
}
?>