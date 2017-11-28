<?php
/**
* dbStatistics: shows a sortable list of databases on the server and their usage (record counts, access dates etc.)
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

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    if(isForAdminOnly("to get information on all databases on this server")){
        return;
    }

    mysql_connection_select();

    $dbs = mysql__getdatabases(true);


    function mysql__select_val($query) {
        $res = mysql_query($query);
        if (!$res) {
            return 0;
        }

        $row = mysql_fetch_array($res);
        if($row){
            return $row[0];
        }else{
            0;
        }
    }


    function dirsize($dir)
    {
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

?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Databases statistics</title>

        <link rel="icon" href="../../favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="../../favicon.ico" type="image/x-icon">

        <!-- YUI -->
        <link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
        <script type="text/javascript" src="../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
        <script type="text/javascript" src="../../external/yui/2.8.2r1/build/element/element-min.js"></script>

        <!-- DATATABLE DEFS -->
        <link type="text/css" rel="stylesheet" href="../../external/yui/2.8.2r1/build/datatable/assets/skins/sam/datatable.css">
        <!-- datatable Dependencies -->
        <script type="text/javascript" src="../../external/yui/2.8.2r1/build/datasource/datasource-min.js"></script>
        <!-- Source files -->
        <script type="text/javascript" src="../../external/yui/2.8.2r1/build/datatable/datatable-min.js"></script>
        <!-- END DATATABLE DEFS-->

        <!-- TOOLTIP -->
        <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.9.0/build/container/assets/container.css">
        <script src="../../external/yui/2.8.2r1/build/container/container-min.js"></script>

        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
    </head>

    <body class="popup yui-skin-sam">
        <div id="titleBanner" class="banner"><h2>Databases statistics</h2></div>
        <div id="page-inner">
            <div id="tabContainer"></div>
        </div>

        <script type="text/javascript">
            //v2
            var showTimer,hideTimer;
            var arr = [
                <?php
                    $com = "";
                    foreach ($dbs as $db){

                        $owner = mysql__select_val("SELECT concat(ugr_FirstName,' ',ugr_LastName,' ',ugr_eMail,', ',ugr_Department,' ',"
                            ."ugr_Organisation,' ',ugr_State,' ',ugr_Interests) FROM ".$db.".sysUGrps where ugr_id=2");
                        $owner = str_replace("'","\'",$owner);
                        $owner = str_replace("\n","",$owner);

                        //ID  Records     Values    RecTypes     Fields    Terms     Groups    Users   Version   DB     Files     Modified    Access

                        print $com."['". substr($db, 4) ."',".
                        mysql__select_val("select cast(sys_dbRegisteredID as CHAR) from ".$db.".sysIdentification where 1").",".
                        mysql__select_val("select count(*) from ".$db.".Records").",".
                        mysql__select_val("select count(*) from ".$db.".recDetails").",".
                        mysql__select_val("select count(*) from ".$db.".defRecTypes").",".
                        mysql__select_val("select count(*) from ".$db.".defDetailTypes").",".
                        mysql__select_val("select count(*) from ".$db.".defTerms").",".
                        mysql__select_val("select count(*) from ".$db.".sysUGrps where ugr_Type='workgroup'").",".
                        mysql__select_val("select count(*) from ".$db.".sysUGrps where ugr_Type='user'").",".
                        mysql__select_val("select concat_ws('.',cast(sys_dbVersion as char),cast(sys_dbSubVersion as char)) "
                            ." from ".$db.".sysIdentification where 1").",".
                        mysql__select_val("SELECT Round(Sum(data_length + index_length) / 1024 / 1024, 1)"
                            ." FROM information_schema.tables where table_schema='".$db."'").",".
                        round( (dirsize(HEURIST_UPLOAD_ROOT . substr($db, 4) . '/')/ 1024 / 1024), 1).",'".
                        mysql__select_val("select max(rec_Modified)  from ".$db.".Records")."','".
                        mysql__select_val("select max(ugr_LastLoginTime)  from ".$db.".sysUGrps")."','".
                        $owner."']";

                        $com = ",\n";

                    }//foreach
                ?>
            ];


            var myDataSource = new YAHOO.util.LocalDataSource(arr, {
                responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
                responseSchema : {
                    fields: ["dbname","db_regid","cnt_recs", "cnt_vals", "cnt_rectype",
                        "cnt_fields", "cnt_terms", "cnt_groups", "cnt_users", "db_version",
                        "size_db", "size_file", "date_mod", "date_login","owner"]
            }});

            var myColumnDefs = [
                { key: "dbname", label: "Name", sortable:true, className:'left'},
                { key: "db_regid", label: "Reg ID", sortable:true, className:'right'},
                { key: "cnt_recs", label: "Records", sortable:true, className:'right'},
                { key: "cnt_vals", label: "Values", sortable:true, className:'right'},
                { key: "cnt_rectype", label: "RecTypes", sortable:true, className:'right'},
                { key: "cnt_fields", label: "Fields", sortable:true, className:'right'},
                { key: "cnt_terms", label: "Terms", sortable:true, className:'right'},
                { key: "cnt_groups", label: "Groups", sortable:true, className:'right'},
                { key: "cnt_users", label: "Users", sortable:true, className:'right'},
                { key: "db_version", label: "DB Vsn", sortable:true, className:'right'},
                { key: "size_db", label: "DB (MB)", sortable:true, className:'right'},
                { key: "size_file", label: "Files (MB)", sortable:true, className:'right'},
                { key: "date_mod", label: "Modified", sortable:true},
                { key: "date_login", label: "Access", sortable:true}
                /*{ key: "owner", label: "Owner", formatter: function(elLiner, oRecord, oColumn, oData){
                elLiner.innerHTML = "<div style='max-width:100px' title='"+oRecord.getData('owner')+"'>"+oRecord.getData('owner')+"</div>";
                }
                }*/
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

    </body>
</html>