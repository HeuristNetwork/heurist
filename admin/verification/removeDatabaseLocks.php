<?php

    /**
    * removeDatabaseLocks.php, Removes all locks on the database. Ian Johnson 20/9/12
    * We can get away with no checkiong b/c locks are administrative and collisons are almost inconceivable
    * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
    * @link: http://HeuristScholar.org
    * @license http://www.gnu.org/licenses/gpl-3.0.txt
    * @package Heurist academic knowledge management system
    * @todo
    **/

?>

<?php

    define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
    /* require_once(dirname(__FILE__)."/../../common/config/initialise.php"); */
    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    if (! is_admin()) {
    ?>
    You must be a HEURIST administrator to use this page.
    <?php
        return;
    }

    mysql_connection_db_overwrite(DATABASE);

    $query="delete from sysLocks";
    $res = mysql_query($query);
    if (!$res) {
        die('<p>Invalid query, please report to developers: '.$query.'  Error: '.mysql_error());
    }

    if (mysql_affected_rows()==0) {
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body class='popup'>
        <h2> There were no database locks to remove</h2>";
    }
    else {
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body class='popup'>
        <h2>Database locks have been removed </h2>";
    }

    print "</body>";
    print "</html>";

?>
