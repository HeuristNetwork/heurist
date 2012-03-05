<?php
    /**
    * File: clearCurrentDB.php Deletes all records/details/bookmarks from the current database (owner group admins only)
    * Ian Johnson 13/2/12
    * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
    * @link: http://HeuristScholar.org
    * @license http://www.gnu.org/licenses/gpl-3.0.txt
    * @package Heurist academic knowledge management system
    * @todo
    *
    * **/

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    // User must be system administrator or admin of the owners group for this database
    if (!is_admin()) {
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to register a database</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
        return;
    }

    $dbname = $_REQUEST['db'];

    if(!array_key_exists('mode', $_REQUEST)) {
        print "<html>";
        print "<head><meta content='text/html; charset=ISO-8859-1' http-equiv='content-type'>";
        print "<title>Delete Records from Current Heurist Database</title>";
        print "<link rel='stylesheet' type='text/css' href='../../common/css/global.css'>";
        print "<link rel='stylesheet' type='text/css' href='../../common/css/edit.css'>";
        print "<link rel='stylesheet' type='text/css' href='../../common/css/admin.css'>";
        print "</head>";
        print "<body class='popup'>";
        print "<div class='banner'><h2>Delete Records from Current Heurist database</h2></div>";
        print "<div id='page-inner' style='overflow:auto'>";
        print "<h4 style='display:inline-block; margin:0 5px 0 0'><span><img src='../../common/images/url_error.png' /> DANGER <img src='../../common/images/url_error.png' /></span></h4><h1 style='display:inline-block'>DELETE ALL RECORDS FROM CURRENT DATABASE</h1><br>";
        print "<h3>This will delete all records from the current database: </h3> <b>$dbname</b>";
        print "<form name='deletion' action='clearCurrentDB.php' method='get'>";
        print "<p>Enter the words DELETE ALL RECORDS in all-capitals to confirm that you want to clear all records from the current database".
        "<p>Type the word above to confirm deletion: <input type='input' maxlength='20' size='20' name='del' id='del'>".
        "&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='OK to Delete' style='font-weight: bold;' >".
        "<input name='mode' value='2' type='hidden'>".
        "<input name='db' value='$dbname' type='hidden'>";
        print "</form></body></html>";
    }

    if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2') {
        if (array_key_exists('del', $_REQUEST) && $_REQUEST['del']=='DELETE ALL RECORDS') {
            print "<p><hr>";
            if ($dbname=='') {
                print "<p>Undefined database name"; // shouldn't get here
            } else {
                $fulldbname = HEURIST_DB_PREFIX.$dbname;
                $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM recDetails' ";
                $output2 = exec($cmdline . ' 2>&1', $output, $res2); 
                if ($res2 != 0 ) {
                    echo ("<br>Warning: Unable to delete recDetails from <b>$dbname</b> - SQL error on: DELETE FROM recDetails<br>");
                    echo($output2);
                }                 
                $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM usrBookmarks' ";
                $output2 = exec($cmdline . ' 2>&1', $output, $res3); 
                if ($res3!= 0 ) {
                    echo ("<br>Warning: Unable to delete usrBookmarks from <b>$dbname</b> - SQL error on DELETE FROM usrBookmarks<br>");
                    echo($output2);
                }                 
                $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM Records' ";
                $output2 = exec($cmdline . ' 2>&1', $output, $res4); 
                if ($res4!= 0 ) {
                    echo ("<br>Warning: Unable to delete Records from <b>$dbname</b> - SQL error on DELETE FROM Records<br>");
                    echo($output2);
                } 
                $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'ALTER TABLE Records AUTO_INCREMENT = 0' ";
                $output2 = exec($cmdline . ' 2>&1', $output, $res5); 
                if ($res5!= 0 ) {
                    echo ("<br>Warning: Unable to reset record IDs to start at 1 for <b>$dbname</b> - SQL error on ALTER TABLE Records AUTO_INCREMENT = 0<br>");
                    echo($output2);
                } 
            }
            if (($res2 != 0 ) or ($res3!= 0 ) or ($res4!= 0 )) {
                echo ("<h2>Warning:</h2> Unable to fully delete records from <b>".HEURIST_DB_PREFIX.$dbname."</b>");
                print "<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to Heurist</a>";
            } else {
                print "Data has been deleted from <b>$dbname</b>";
                print "<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to Heurist</a>";
            }
        } else { // didn't request properly
            print "<p><h2>Request disallowed</h2>Incorrect challenge words entered. Data was note deleted from $dbname ".
            "<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to Heurist</a>";
        }
    }

?>





