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
    $record_row = array('Name','Reg ID','Records','DB Vsn','Data updated','Structure modified','Owner','eMail','Institution');
    fputcsv($fd, $record_row, ',', '"');
}else{
    $arr_databases = array();
    
    $arr_header = array("dbname","db_regid","cnt_recs", //"cnt_vals", 
                    "db_version",
                    "date_mod", "date_struct_mod","owner");
}

$i = 0;
foreach ($dbs as $db){

    //ID  Records     Files(MB)    RecTypes     Fields    Terms     Groups    Users   Version   DB     Files     Modified    Access    Owner   Deleteable
//error_log(substr($db, 4)); 
    if(!hasTable($mysqli, 'sysIdentification',$db)) continue;

    $record_row = array (substr($db, 4),
    mysql__select_val("select cast(sys_dbRegisteredID as CHAR) from ".$db.".sysIdentification where 1"),
    mysql__select_val("select count(*) from ".$db.".Records where (not rec_FlagTemporary)"),
    //0,mysql__select_val("select count(*) from ".$db.".recDetails"),
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
    mysql__select_val("select max(rec_Modified)  from ".$db.".Records"),
    //mysql__select_val("select max(ugr_LastLoginTime)  from ".$db.".sysUGrps") );
    mysql__select_value($mysqli, "select max(rst_Modified) from ".$db.".defRecStructure") );

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
        
        $record_row[4] = strtotime($record_row[4]); 
        $record_row[4] = strtotime($record_row[5]); 
        
        //$record_row[] = $sysadmin;
       
        $aitem_quote = function($n)
        {
            return is_numeric($n) ?$n :('"'.str_replace('"','\"',$n).'"');
        };        
        
        $record_row[] = $record_row[0]; //add dbname to the end
        
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

        <!-- jQuery UI -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
        <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>

        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>

        <!-- Heurist JS -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>

    </head>

    <body class="popup">
        <div id="titleBanner" class="banner"><h2>Databases statistics</h2></div>
        <div id="page-inner">
            <?php echo "System admin: <a class='dotted-link' href='mailto:" .HEURIST_MAIL_TO_ADMIN. "'>" .HEURIST_MAIL_TO_ADMIN. "</a>"; ?>
            <?php if($is_delete_allowed) { /*&& $sysadmin*/?> <button id="deleteDatabases" onclick="deleteDatabases()">Delete selected databases</button><br><br> <?php } ?>
            

            <table style="width:98%" class="div_datatable display">
            </table>
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
            var dataSet = [
                <?php
                    foreach ($arr_databases as $db) {
                        print '['.$db.'],'.PHP_EOL;
                    }
                ?>
            ];

            
        _dataTableParams = {
            //scrollCollapse:true,
            //scrollX: false,
            //scrollY: true,
            autoWidth: false,
            //initComplete: _onDataTableInitComplete,
            dom:'ip',
            pageLength: 500,
            ordering: true,
            processing: false,
            serverSide: false,
            data: dataSet,
            columns: null
        };
    
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
            
        _dataTableParams['columns'] = [
            
                { title: "Name", sortable:true, searchable:true, className:'left'  //data: "dbname", 
                ,render: function(data, type) {
                    if (type === 'display') {
                        return "<a href='../../?db="+data+"' class='dotted-link' target='_blank'>"+data+"</a>";
                    }else{
                        return '';    
                    }
                }},
                { title: "Reg ID", sortable:true, searchable:true, className:'right'}, //data: "db_regid", 
                { title: "Records", sortable:true, searchable:false, className:'right'}, //data: "cnt_recs", 
                //{ title: "Files(MB)", sortable:true, searching:false, className:'right'}, //data: "cnt_vals", 
                { title: "DB Vsn", sortable:true, searchable:false, className:'right'},  //data: "db_version", 
                { title: "Data updated", searchable:false, sortable:true,    //data: "date_mod", 
                    render: function(data, type) {
                        return (type === 'display')?__format_date(data):'';
                    }},
                { title: "Structure modified", searchable:false, sortable:true,   //data: "date_struct_mod", 
                    render: function(data, type) {
                        return (type === 'display')?__format_date(data):'';
                    }},
                { title: "Owner", searchable:false, width:200,  //data: "owner", 
                    render: function(data, type) {
                        if (type === 'display') {
                            return "<div style='max-width:100px' class='three-lines' title='"+data+"'>"+data+"</div>";
                        }else{
                            return '';    
                        }
                    }}
                <?php if($is_delete_allowed) { /*$sysadmin && */?>
                    ,{ title: 'Delete', searchable:false, className: 'right',   //data: 'dbname', 
                    render: function(data, type) {
                        if (type === 'display') {
                            return '<input type=\"checkbox\" value=\"'+data
                                +'\" onchange="{_onSelectDeleteDb(event);return false;}"/>';
                        }else{
                            return '';    
                        }
                    }}
                <?php } ?>
            ];

            
            _dataTable = $('.div_datatable').DataTable( _dataTableParams );        
            $('.dataTables_filter').css({float:'left'});
            var ele = $('.dataTables_filter').find('input');
            ele.val(''); //attr('type','text').
            window.hWin.HEURIST4.ui.disableAutoFill( ele );
            
            

        </script>

        <?php if($is_delete_allowed) { ?>
            <!-- Delete databases scipt -->
            <script>
                var databases = [];
                var password;

                function _onSelectDeleteDb(e){
                        var sel_cnt = getSelectedDatabases();
                        if(sel_cnt>25){ //25
                            window.hWin.HEURIST4.util.stopEvent(e);
                            $(e.target).prop('checked',false);
                            window.hWin.HEURIST4.msg.showMsgDlg('Max 25 databases allowed to be deleted at one time.',
                            null,null,{position: { my: "right top", at: "right-350 top+150", of: window }});
                        }
                }
                
                /**
                * Returns the values of checkboxes that have been selected
                */
                function getSelectedDatabases() {
                    this.databases = [];
                    var checkboxes = $('input[type="checkbox"]');
                    if(checkboxes.length > 0) {
                        for(var i=0; i<checkboxes.length; i++) {
                            if($(checkboxes[i]).is(':checked')) {
                                this.databases.push($(checkboxes[i]).val());
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
                        window.hWin.HEURIST4.msg.showMsgDlg("You selected "+this.databases.length+" databases to be deleted. Max 25 allowed at one time.",null,null,
                        {position: { my: "left top", at: "left+150 top+150", of: window }});                        
                        return false;
                    }else if(this.databases.length == 0) {
                        window.hWin.HEURIST4.msg.showMsgFlash("Select at least one database to delete",null,null,
                            {position: { my: "left top", at: "left+150 top+150", of: window }});                        
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