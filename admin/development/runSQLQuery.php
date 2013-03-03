<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

?>

<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

// run_sql_query.php  - EXECUTES A DEFINED sql QUERY ON THE DATABASE AND PRINTS RESULTS
// This script is fairly generic and can be sued for any direct queries into the Heurist database
// It coudl be parameterised with a formatting string, but it was considered not worth the effort
// The first parameter indicates the formatting in the switch in print_row
// Ian Johnson and Steve White 25 Feb 2010

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

if (!is_logged_in()) {
	    header('Location: ' . HEURIST_BASE_URL . 'login.php?db='.HEURIST_DBNAME);
	    return;
        }

if (! is_admin()) {
    print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href='".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME."'>Log out</a></p></body></html>";
    return;
}

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/t1000/.ht_stdefs');
?>

<html>

<head>
 <style type="text/css"> </style>
</head>

<body>

<base target="_blank">

<?php

// Deals with all the database connections stuff

    mysql_connection_select(DATABASE);

// Page headers to explain what the listing represents, includes query for backtraqcking

      switch ($_REQUEST['f']) {

      case 'ibblank':
        print "Internet Bookmarks with blank URLs";print "<p>"; print "Query: ";
        break;
      case 'garbage':
        print "Records with garbage URLs";print "<p>"; print "Query: ";
        break;
      case 'ibdupe':
        print "Internet Bookmarks with duplicate URLs (with other IBs)";print "<br>";
      print "Only the title of the first record is shown"; print "<p>"; print "Query: ";
        break;
      case 'alldupe':
      print "Records with duplicate URLs (with any rectype)";print "<br>";
      print "Only the title of the first record is shown"; print "<p>"; print "Query: ";
        Break;
      }

    $query = @$_REQUEST['q'];
    print $query; print "<p>";

    $res = mysql_query($query);
    while ($row = mysql_fetch_assoc($res)) {
    	print_row($row);
	}
?>

</body>
</html>

<?php

function print_row($row) {

// Prints a formatted representation of the data retreived for one row in the query
// Make sure that the query you passed in generates the fields you want to print
// Specify fields with $row[fieldname] or $row['fieldname'] (in most cases the quotes
// are unecessary although perhaps syntactically proper)


      switch ($_REQUEST['f']) {  // select the output formatting

      case 'ibblank': // Internet bookmark and blank URL
        print "<a href='".HEURIST_BASE_URL."?q=ids:$row[rec_ID]'>$row[rec_ID]</a>";
        print " :  ";print $row[rec_Title];print " [ ";
        print "<a href=http://www.google.com/search?q=$row[rec_Title]>Google</a>";
        print " ]<br>";
        break;
      case 'garbage': // Garbage in the URL
        print "<a href='".HEURIST_BASE_URL."?q=ids:$row[rec_ID]'>$row[rec_ID]</a>";
        print " :  $row[rec_Title] <br>";
        print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        print "url: $row[rec_URL]";print "<br>";
        print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        print "rec type: $row[rec_RecTypeID]";print "<br>";
        break;
      case 'ibdupe': // Internet bookmark and duplicate URL with another IB
        print "<p>"; print $row[cnt]; print ": ";
        print "$row[rec_Title]<br>";
        print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        print "url: <a href='".HEURIST_BASE_URL."?q=url:$row[rec_URL]'>$row[rec_URL]</a>";
        print "<a href=http://www.google.com/search?q=url:$row[rec_URL]>Google</a>";
                break;
      case 'alldupe': // Duplicate URL with another record (all record types)
        print "<p>"; print $row[cnt]; print ": ";
        print $row[rec_Title]; print "<br>";
        print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        print "url: <a href='".HEURIST_BASE_URL."?q=url:$row[rec_URL]'>$row[rec_URL]</a> [ ";
        print "<a href=http://www.google.com/search?q=url:$row[rec_URL]>Google</a> ]";
               break;
      } // end Switch


} // end function print_row


?>
