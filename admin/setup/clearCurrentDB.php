<?php
    /**
    * File: clearCurrentDB.php Deletes all records/details/bookmarks from the current database (owner group admins only)
    *  Does not affect definitions. Resets record counter to 0.
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
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to clear a database</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
        return;
    }

    $dbname = $_REQUEST['db'];

    if(!array_key_exists('mode', $_REQUEST)) {
        print "<html>";
        print "<head><meta content='text/html; charset=ISO-8859-1' http-equiv='content-type'>";
        print "<title>Clear Records from Current Heurist Database</title>";
        print "<link rel='stylesheet' type='text/css' href='../../common/css/global.css'>";
        print "<link rel='stylesheet' type='text/css' href='../../common/css/edit.css'>";
        print "<link rel='stylesheet' type='text/css' href='../../common/css/admin.css'>";
        print "</head>";
        print "<body class='popup'>";
        print "<div class='banner'><h2>Clear Records from Current Heurist database</h2></div>";
        print "<div id='page-inner' style='overflow:auto'>";
        print "<h4 style='display:inline-block; margin:0 5px 0 0'>".
        "<span><img src='../../common/images/url_error.png' /> DANGER <img src='../../common/images/url_error.png' /></span>".
        "</h4><h1 style='display:inline-block'>CLEAR ALL RECORDS FROM CURRENT DATABASE</h1><br>";
        print "<h3>This will clear (delete) all records and reset counter to 1 for the current database: </h3> <b>$dbname</b>";
        print "<form name='deletion' action='clearCurrentDB.php' method='get'>";
        print "<p>Database definitions - record types, fields, terms, tags, users etc. - are not affected. ";
        print "Uploaded files are not deleted. Bookmarks and tags on specific records are deleted.<p>";
        print "This operation may take some minutes on a large database<br>";
        print "<p>Enter the words CLEAR ALL RECORDS in all-capitals to confirm that you want to clear all records from the current database".
        "<p>Type the words above to confirm deletion: <input type='input' maxlength='20' size='20' name='del' id='del'>".
        "&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='OK to Clear' style='font-weight: bold;' >".
        "<input name='mode' value='2' type='hidden'>".
        "<input name='db' value='$dbname' type='hidden'>";
        print "</form></body></html>";
    }

    if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2') {
        if (array_key_exists('del', $_REQUEST) && $_REQUEST['del']=='CLEAR ALL RECORDS') {
            print "<p><hr>";
            if ($dbname=='') {
                print "<p>Undefined database name"; // shouldn't get here
            } else {
                // This is a bit inelegant but it does the job effectively. Delete all the related records first because
                // otherwise referential integrity will stop you deleting the recordss and/or bookmarks
                $fulldbname = HEURIST_DB_PREFIX.$dbname;
                $res2=0;

                // set parents to null to avoid referential integrity checks on deletion of parent before child
                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'update recThreadedComments set cmt_ParentCmtID = NULL' ";
                    echo ("Setting parents of threaded comments to null</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to set parent IDs to null for comments in <b>$dbname</b> - SQL error on update recThreadedComments set cmt_ParentCmtID = NULL<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM recThreadedComments' ";
                    echo ("Deleting threaded comments</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete recThreadedComments from <b>$dbname</b> - SQL error on DELETE FROM recThreadedComments<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM recForwarding' ";
                    echo ("Deleting record forwards</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete recForwarding from <b>$dbname</b> - SQL error on DELETE FROM recForwarding<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM recRelationshipsCache' ";
                    echo ("Deleting record relationships cache</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete recRelationshipsCache from <b>$dbname</b> - SQL error on DELETE FROM recRelationshipsCache<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM recSimilarButNotDupes' ";
                    echo ("Deleting list of similar but not dupe records</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete recSimilarButNotDupes from <b>$dbname</b> - SQL error on DELETE FROM recSimilarButNotDupes<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM usrRecentRecords' ";
                    echo ("Deleting user's recent records list</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete usrRecentRecords from <b>$dbname</b> - SQL error on DELETE FROM usrRecentRecords<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM usrRecTagLinks' ";
                    echo ("Deleting record to tag links</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete usrRecTagLinks from <b>$dbname</b> - SQL error on DELETE FROM usrRecTagLinks<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM usrReminders' ";
                    echo ("Deleti9ng user reminders</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete usrReminders from <b>$dbname</b> - SQL error on DELETE FROM usrReminders<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM usrRemindersBlockList' ";
                    echo ("Deleting user reminders block list</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete usrRemindersBlockList from <b>$dbname</b> - SQL error on DELETE FROM usrRemindersBlockList<br>");
                        echo($output2);
                    }
                }

                // Now delete the main data tables

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM recDetails' ";
                    echo ("deleting record details (fields)</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2 != 0 ) {
                        echo ("<br>Warning: Unable to delete recDetails from <b>$dbname</b> - SQL error on: DELETE FROM recDetails<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM usrBookmarks' ";
                    echo ("Deleting user bookmarks</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete usrBookmarks from <b>$dbname</b> - SQL error on DELETE FROM usrBookmarks<br>");
                        echo($output2);
                    }
                }

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'DELETE FROM Records' ";
                    echo ("Deleting Records</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to delete Records from <b>$dbname</b> - SQL error on DELETE FROM Records<br>");
                        echo($output2);
                    }
                }

                // Reset the record counter to zero

                if ($res2==0) {
                    $cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".$fulldbname." -e'ALTER TABLE Records AUTO_INCREMENT = 0' ";
                    echo ("Resetting record counter to zero</br>");
                    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
                    if ($res2!= 0 ) {
                        echo ("<br>Warning: Unable to reset record IDs to start at 1 for <b>$dbname</b> - SQL error on ALTER TABLE Records AUTO_INCREMENT = 0<br>");
                        echo($output2);
                    }
                }
                if ($res2 != 0 ) {
                    echo ("<h2>Warning:</h2> Unable to fully delete records from <b>".HEURIST_DB_PREFIX.$dbname."</b>");
                    print "<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to Heurist</a>";
                } else {
                    print "<h2>Record data, bookmarks and tags have been deleted from <b>$dbname</b></h2>";
                    print "<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to the database home page</a>";
                }
            }
        }
        else { // didn't request properly
            print "<p><h2>Request disallowed</h2>** FAILED **<p/>&nbsp;<p/>Incorrect challenge words entered. Data was note deleted from $dbname ".
            "<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to the database home page</a>";
        }
    }

?>





