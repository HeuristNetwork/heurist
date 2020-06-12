<?php
/**
* utils_db_load_script.php: Executes SQL script. Heavily modified from bigdump.php (ozerov.de/bigdump)
*                           allowing processing of very large MySQL dump files
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


//error_reporting(E_ALL);

// BigDump ver. 0.35b from 2012-12-26
// Staggered import of an large MySQL Dump (like phpMyAdmin 2.x Dump)
// Even through the webservers with hard runtime limit and those in safe mode
// Works fine with Internet Explorer 7.0 and Firefox 2.x

// Author:       Alexey Ozerov (alexey at ozerov dot de)
//               AJAX & CSV functionalities: Krzysiek Herod (kr81uni at wp dot pl)
// Copyright:    GPL (C) 2003-2013
// More Infos:   http://www.ozerov.de/bigdump

// This program is free software; you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software Foundation;
// either version 2 of the License, or (at your option) any later version.

// THIS SCRIPT IS PROVIDED AS IS, WITHOUT ANY WARRANTY OR GUARANTEE OF ANY KIND

// USAGE

// 1. Adjust the database configuration and charset in this file
// 2. Remove the old tables on the target database if your dump doesn't contain "DROP TABLE"
// 3. Create the working directory (e.g. dump) on your web server
// 4. Upload bigdump.php and your dump files (.sql, .gz) via FTP to the working directory
// 5. Run the bigdump.php from your browser via URL like http://www.yourdomain.com/dump/bigdump.php
// 6. BigDump can start the next import session automatically if you enable the JavaScript
// 7. Wait for the script to finish, do not close the browser window
// 8. IMPORTANT: Remove bigdump.php and your dump files from the web server

// If Timeout errors still occure you may need to adjust the $linepersession setting in this file

// LAST CHANGES

// *** First ideas about adding plugin interface
// *** Fix // delimiter bug
// *** Minor fix to avoid Undefined variable curfilename notice
// *** Handle empty delimiter setting
// *** New way to determine the upload directory
// *** Set UTF8 as default connection charset

define ('DATA_CHUNK_LENGTH',16384);  // How many chars are read per time
define ('TESTMODE', false);           // Set to true to process the file without actually accessing the database
//define ('VERSION','0.35b');
//define ('BIGDUMP_DIR',dirname(__FILE__));
//define ('PLUGIN_DIR',BIGDUMP_DIR.'/plugins/');
$error = false;
$errorScriptExecution = null;

//
//
//
function execute_db_script($system, $database_name_full, $script_file, $message){
    global $errorScriptExecution;
    
    if( db_script($database_name_full, $script_file, false) ){
        return true;
    }else{
        $system->addError(HEURIST_ERROR, $message, $errorScriptExecution);
        return false;
    }
    
}

// Database configuration
function db_script($db_name, $filename, $verbose = false){
    
global $error, $errorScriptExecution;
    
$db_server   = HEURIST_DBSERVER_NAME;
$db_username = ADMIN_DBUSERNAME;
$db_password = ADMIN_DBUSERPSWD;

// Connection charset should be the same as the dump file charset (utf8, latin1, cp1251, koi8r etc.)
// See http://dev.mysql.com/doc/refman/5.0/en/charset-charsets.html for the full list
// Change this if you have problems with non-latin letters

$db_connection_charset = 'utf8mb4';

// OPTIONAL SETTINGS

//$filename           = '';     // Specify the dump filename to suppress the file selection dialog
$ajax               = true;   // AJAX mode: import will be done without refreshing the website
$linespersession    = 3000000;   // Lines to be executed per one import session
$delaypersession    = 0;      // You can specify a sleep time in milliseconds after each session
                              // Works only if JavaScript is activated. Use to reduce server overrun

// CSV related settings (only if you use a CSV dump)

$csv_insert_table   = '';     // Destination table for CSV files
$csv_preempty_table = false;  // true: delete all entries from table specified in $csv_insert_table before processing
$csv_delimiter      = ',';    // Field delimiter in CSV file
$csv_add_quotes     = true;   // If your CSV data already have quotes around each field set it to false
$csv_add_slashes    = true;   // If your CSV data already have slashes in front of ' and " set it to false

// Allowed comment markers: lines starting with these strings will be ignored by BigDump

$comment[]='#';                       // Standard comment lines are dropped by default
$comment[]='-- ';
$comment[]='DELIMITER';               // Ignore DELIMITER switch as it's not a valid SQL statement
// $comment[]='---';                  // Uncomment this line if using proprietary dump created by outdated mysqldump
// $comment[]='CREATE DATABASE';      // Uncomment this line if your dump contains create database queries in order to ignore them
// $comment[]='/*!';                     // Or add your own string to leave out other proprietary things

// Pre-queries: SQL queries to be executed at the beginning of each import session

// $pre_query[]='SET foreign_key_checks = 0';
// $pre_query[]='Add additional queries if you want here';

// Default query delimiter: this character at the line end tells Bigdump where a SQL statement ends
// Can be changed by DELIMITER statement in the dump file (normally used when defining procedures/functions)

$delimiter = ';';

// String quotes character

$string_quotes = '\'';                  // Change to '"' if your dump file uses double qoutes for strings

// How many lines may be considered to be one query (except text lines)

$max_query_lines = 300;

// Where to put the upload files into (default: bigdump folder)

$upload_dir = dirname(__FILE__);

// *******************************************************************************************
// If not familiar with PHP please don't change anything below this line
// *******************************************************************************************

if (!$verbose || $ajax){
  ob_start();  
}

@ini_set('auto_detect_line_endings', true);
@set_time_limit(0);

if (function_exists("date_default_timezone_set") && function_exists("date_default_timezone_get"))
  @date_default_timezone_set(@date_default_timezone_get());

// Clean and strip anything we don't want from user's input [0.27b]


$error = false;
$errorScriptExecution = null;
$file  = false;

// Check PHP version

if (!$error && !function_exists('version_compare'))
{ 
    error_echo ("<p class=\"error\">PHP version 4.1.0 is required for db script read to proceed. You have PHP ".phpversion()." installed. Sorry!</p>\n");

}

// Check if mysql extension is available

if (!$error && !function_exists('mysqli_connect_error'))
{ 
    error_echo ("<p class=\"error\">There is no mySQL extension available in your PHP installation. Sorry!</p>\n");
}


do_action ('script_runs');

// Connect to the database, set charset and execute pre-queries

if (!$error && !TESTMODE)
{ 
    $mysqli = new mysqli($db_server,$db_username,$db_password);
    if (!$mysqli){

        error_echo (  
            "<p class=\"error\">Database connection failed due to ".mysqli_connect_error()."</p>\n"
            ."<p>Edit the database settings in your configuration file, or ".CONTACT_SYSADMIN.".</p>\n");

    }else{
        $success = $mysqli->select_db($db_name);

        if (!$success)
        { 
            error_echo(
                "<p class=\"error\">Database connection failed due to ".$mysqli->error."</p>\n"
                ."<p>Edit the database settings in your configuration file, or ".CONTACT_SYSADMIN.".</p>\n");

        }
    }

    if (!$error && $db_connection_charset!==''){
        $mysqli->query("SET NAMES $db_connection_charset");
    }
        

    if (!$error && isset ($pre_query) && sizeof ($pre_query)>0)
    { reset($pre_query);
        foreach ($pre_query as $pre_query_value)
        {	if (!$mysqli->query($pre_query_value))
            { 
                error_echo(
                    "<p class=\"error\">Error with pre-query.</p>\n"
                    ."<p>Query: ".trim(nl2br(htmlentities($pre_query_value)))."</p>\n"
                    ."<p>MySQL: ".$mysqli->error."</p>\n");
                break;
            }
        }
    }
}
else
{ 
    $mysqli = false;
}

do_action('database_connected');


// Single file mode

if (!$error && !isset ($_REQUEST["fn"]) && $filename!="")
{
    //    echo ("<p><a href=\"".$_SERVER["PHP_SELF"]."?start=1&amp;fn=".urlencode($filename)."&amp;foffset=0&amp;totalqueries=0\">Start Import</a> from $filename into $db_name at $db_server</p>\n");
    $_REQUEST["start"] = "1";
    $_REQUEST["fn"] = $filename;
    $_REQUEST["foffset"]=0;
    $_REQUEST["totalqueries"]=0;
    //$filename = "";
}

// Open the file

if (!$error && isset($_REQUEST["start"]))
{

    // Set current filename ($filename overrides $_REQUEST["fn"] if set)

    if ($filename!="")
        $curfilename=$filename;
    else if (isset($_REQUEST["fn"]))
        $curfilename=urldecode($_REQUEST["fn"]);
    else
        $curfilename="";

    //DEBUG error_log($curfilename);    

    // Recognize GZip filename
    $gzipmode=false;

    if ((!$gzipmode && !$file=@fopen($curfilename,"r")) || ($gzipmode && !$file=@gzopen($curfilename,"r")))   //$upload_dir.'/'.
    {    
        error_echo(
            "<p class=\"error\">Can't open sql script file ".$curfilename."</p>");
            /*
            ."<p>Please, check that your script file name contains only alphanumerical characters, and rename it accordingly, for example: $curfilename.".
            "<br>Or, specify \$filename in bigdump.php with the full filename. ".
            "<br>Or, you have to upload the $curfilename to the server first.</p>\n");
            */
    }
    // Get the file size (can't do it fast on gzipped files, no idea how)

    else if ((!$gzipmode && @fseek($file, 0, SEEK_END)==0) || ($gzipmode && @gzseek($file, 0)==0))
    { if (!$gzipmode) $filesize = ftell($file);
        else $filesize = gztell($file);                   // Always zero, ignore
    }
    else
    { 
        error_echo ("<p class=\"error\">Can't open sql script file $curfilename</p>\n");
    }

    // Stop if csv file is used, but $csv_insert_table is not set

    if (!$error && ($csv_insert_table == "") && (preg_match("/(\.csv)$/i",$curfilename)))
    { 
        error_echo ("<p class=\"error\">You have to specify \$csv_insert_table when using a CSV file. </p>\n");
    }
}

// *******************************************************************************************
// START IMPORT SESSION HERE
// *******************************************************************************************

if (!$error && isset($_REQUEST["start"]) && isset($_REQUEST["foffset"]) && preg_match("/(\.(sql|gz|csv))$/i",$curfilename))
{

  do_action('session_start');

// Check start and foffset are numeric values

  if (!is_numeric($_REQUEST["start"]) || !is_numeric($_REQUEST["foffset"]))
  { error_echo ("<p class=\"error\">UNEXPECTED: Non-numeric values for start and foffset</p>\n");
  }
  else
  {	$_REQUEST["start"]   = floor($_REQUEST["start"]);
    $_REQUEST["foffset"] = floor($_REQUEST["foffset"]);
  }

// Set the current delimiter if defined

  if (isset($_REQUEST["delimiter"]))
    $delimiter = $_REQUEST["delimiter"];

// Empty CSV table if requested

  if (!$error && $_REQUEST["start"]==1 && $csv_insert_table != "" && $csv_preempty_table)
  {
    $query = "DELETE FROM `$csv_insert_table`";
    if (!TESTMODE && !$mysqli->query(trim($query)))
    { 
        error_echo ("<p class=\"error\">Error when deleting entries from $csv_insert_table.</p>\n"
            ."<p>Query: ".trim(nl2br(htmlentities($query)))."</p>\n"
            ."<p>MySQL: ".$mysqli->error."</p>\n");
    }
  }

// Print start message

  if (!$error && TESTMODE)
  {
    skin_open();
    echo ("<p class=\"centr\">TEST MODE ENABLED</p>\n");
    echo ("<p class=\"centr\">Processing file: <b>".$curfilename."</b></p>\n");
    echo ("<p class=\"smlcentr\">Starting from line: ".$_REQUEST["start"]."</p>\n");
    skin_close();
  }

// Check $_REQUEST["foffset"] upon $filesize (can't do it on gzipped files)

  if (!$error && !$gzipmode && $_REQUEST["foffset"]>$filesize)
  { error_echo ("<p class=\"error\">UNEXPECTED: Can't set file pointer behind the end of file</p>\n");
  }

// Set file pointer to $_REQUEST["foffset"]

  if (!$error && ((!$gzipmode && fseek($file, $_REQUEST["foffset"])!=0) || ($gzipmode && gzseek($file, $_REQUEST["foffset"])!=0)))
  { error_echo ("<p class=\"error\">UNEXPECTED: Can't set file pointer to offset: ".$_REQUEST["foffset"]."</p>\n");
  }

// Start processing queries from $file

  if (!$error)
  { $query="";
    $queries=0;
    $totalqueries=$_REQUEST["totalqueries"];
    $linenumber=$_REQUEST["start"];
    $querylines=0;
    $inparents=false;

// Stay processing as long as the $linespersession is not reached or the query is still incomplete

    while ($linenumber<$_REQUEST["start"]+$linespersession || $query!="")
    {

// Read the whole next line

      $dumpline = "";
      while (!feof($file) && substr ($dumpline, -1) != "\n" && substr ($dumpline, -1) != "\r")
      { if (!$gzipmode)
          $dumpline .= fgets($file, DATA_CHUNK_LENGTH);
        else
          $dumpline .= gzgets($file, DATA_CHUNK_LENGTH);
      }
      if ($dumpline==="") break;

// Remove UTF8 Byte Order Mark at the file beginning if any

      if ($_REQUEST["foffset"]==0)
        $dumpline=preg_replace('|^\xEF\xBB\xBF|','',$dumpline);

// Create an SQL query from CSV line

      if (($csv_insert_table != "") && (preg_match("/(\.csv)$/i",$curfilename)))
      {
        if ($csv_add_slashes)
          $dumpline = addslashes($dumpline);
        $dumpline = explode($csv_delimiter,$dumpline);
        if ($csv_add_quotes)
          $dumpline = "'".implode("','",$dumpline)."'";
        else
          $dumpline = implode(",",$dumpline);
        $dumpline = 'INSERT INTO '.$csv_insert_table.' VALUES ('.$dumpline.');';
      }

// Handle DOS and Mac encoded linebreaks (I don't know if it really works on Win32 or Mac Servers)

      $dumpline=str_replace("\r\n", "\n", $dumpline);
      $dumpline=str_replace("\r", "\n", $dumpline);

// DIAGNOSTIC
// echo ("<p>Line $linenumber: $dumpline</p>\n");

// Recognize delimiter statement

      if (!$inparents && strpos ($dumpline, "DELIMITER ") === 0)
        $delimiter = str_replace ("DELIMITER ","",trim($dumpline));

// Skip comments and blank lines only if NOT in parents

      if (!$inparents)
      { $skipline=false;
        reset($comment);
        foreach ($comment as $comment_value)
        {

// DIAGNOSTIC
//          echo ($comment_value);
          if (trim($dumpline)=="" || strpos (trim($dumpline), $comment_value) === 0)
          { $skipline=true;
            break;
          }
        }
        if ($skipline)
        { $linenumber++;

// DIAGNOSTIC
// echo ("<p>Comment line skipped</p>\n");

          continue;
        }
      }

// Remove double back-slashes from the dumpline prior to count the quotes ('\\' can only be within strings)

      $dumpline_deslashed = str_replace ("\\\\","",$dumpline);

// Count ' and \' (or " and \") in the dumpline to avoid query break within a text field ending by $delimiter

      $parents=substr_count ($dumpline_deslashed, $string_quotes)-substr_count ($dumpline_deslashed, "\\$string_quotes");
      if ($parents % 2 != 0)
        $inparents=!$inparents;

// Add the line to query

      $query .= $dumpline;

// Don't count the line if in parents (text fields may include unlimited linebreaks)

      if (!$inparents)
        $querylines++;

// Stop if query contains more lines as defined by $max_query_lines

      if ($querylines>$max_query_lines)
      {
        error_echo ("<p class=\"error\">Stopped at the line $linenumber. </p>"
        ."<p>At this place the current query includes more than ".$max_query_lines." dump lines. That can happen if your dump file was "
        ."created by some tool which doesn't place a semicolon followed by a linebreak at the end of each query, or if your dump contains "
        ."extended inserts or very long procedure definitions.</p>\n");
        break;
      }

// Execute query if end of query detected ($delimiter as last character) AND NOT in parents

// DIAGNOSTIC
// echo ("<p>Regex: ".'/'.preg_quote($delimiter).'$/'."</p>\n");
// echo ("<p>In Parents: ".($inparents?"true":"false")."</p>\n");
// echo ("<p>Line: $dumpline</p>\n");

      if ((preg_match('/'.preg_quote($delimiter,'/').'$/',trim($dumpline)) || $delimiter=='') && !$inparents)
      {

// Cut off delimiter of the end of the query

        $query = substr(trim($query),0,-1*strlen($delimiter));

// DIAGNOSTIC "<p>Query: ".."</p>\n"
/*
if($linenumber>2999){
    echo '<p>'.substr(trim(nl2br(htmlentities($query))),0,50).'</p>';
    error_log (substr(trim(nl2br(htmlentities($query))),0,50));
}
*/
        if (!TESTMODE && !$mysqli->query($query))
        { 
            $errorMsg = $mysqli->error;
            
            if(strpos($errorMsg,'Cannot get geometry object')!==false){
                error_log( $linenumber.'   '. trim($dumpline) );                
            }else{
                error_echo ("<p class=\"error\">Error at the line $linenumber: ". trim($dumpline)."</p>\n"
                ."<p>Query: ".trim(nl2br(htmlentities($query)))."</p>\n"
                ."<p>MySQL: ".$errorMsg."</p>\n");
                //ART:why it was reset? $error = false;
                if(strpos($errorMsg,'Duplicate column')===false){
                    $error = true;
                }
                break;
            }
        }
        $totalqueries++;
        $queries++;
        $query="";
        $querylines=0;
      }
      $linenumber++;
    }
  }

// Get the current file position

  if (!$error)
  { if (!$gzipmode)
      $foffset = ftell($file);
    else
      $foffset = gztell($file);
    if (!$foffset)
    { error_echo ("<p class=\"error\">UNEXPECTED: Can't read the file pointer offset</p>\n");
    }
  }

// Print statistics
if (TESTMODE){

skin_open();

// echo ("<p class=\"centr\"><b>Statistics</b></p>\n");

  if (!$error)
  {
    $lines_this   = $linenumber-$_REQUEST["start"];
    $lines_done   = $linenumber-1;
    $lines_togo   = ' ? ';
    $lines_tota   = ' ? ';

    $queries_this = $queries;
    $queries_done = $totalqueries;
    $queries_togo = ' ? ';
    $queries_tota = ' ? ';

    $bytes_this   = $foffset-$_REQUEST["foffset"];
    $bytes_done   = $foffset;
    $kbytes_this  = round($bytes_this/1024,2);
    $kbytes_done  = round($bytes_done/1024,2);
    $mbytes_this  = round($kbytes_this/1024,2);
    $mbytes_done  = round($kbytes_done/1024,2);

    if (!$gzipmode)
    {
      $bytes_togo  = $filesize-$foffset;
      $bytes_tota  = $filesize;
      $kbytes_togo = round($bytes_togo/1024,2);
      $kbytes_tota = round($bytes_tota/1024,2);
      $mbytes_togo = round($kbytes_togo/1024,2);
      $mbytes_tota = round($kbytes_tota/1024,2);

      $pct_this   = ceil($bytes_this/$filesize*100);
      $pct_done   = ceil($foffset/$filesize*100);
      $pct_togo   = 100 - $pct_done;
      $pct_tota   = 100;

      if ($bytes_togo==0)
      { $lines_togo   = '0';
        $lines_tota   = $linenumber-1;
        $queries_togo = '0';
        $queries_tota = $totalqueries;
      }

      $pct_bar    = "<div style=\"height:15px;width:$pct_done%;background-color:#000080;margin:0px;\"></div>";
    }
    else
    {
      $bytes_togo  = ' ? ';
      $bytes_tota  = ' ? ';
      $kbytes_togo = ' ? ';
      $kbytes_tota = ' ? ';
      $mbytes_togo = ' ? ';
      $mbytes_tota = ' ? ';

      $pct_this    = ' ? ';
      $pct_done    = ' ? ';
      $pct_togo    = ' ? ';
      $pct_tota    = 100;
      $pct_bar     = str_replace(' ','&nbsp;','<tt>[         Not available for gzipped files          ]</tt>');
    }

    echo ("
    <center>
    <table width=\"520\" border=\"0\" cellpadding=\"3\" cellspacing=\"1\">
    <tr><th class=\"bg4\"> </th><th class=\"bg4\">Session</th><th class=\"bg4\">Done</th><th class=\"bg4\">To go</th><th class=\"bg4\">Total</th></tr>
    <tr><th class=\"bg4\">Lines</th><td class=\"bg3\">$lines_this</td><td class=\"bg3\">$lines_done</td><td class=\"bg3\">$lines_togo</td><td class=\"bg3\">$lines_tota</td></tr>
    <tr><th class=\"bg4\">Queries</th><td class=\"bg3\">$queries_this</td><td class=\"bg3\">$queries_done</td><td class=\"bg3\">$queries_togo</td><td class=\"bg3\">$queries_tota</td></tr>
    <tr><th class=\"bg4\">Bytes</th><td class=\"bg3\">$bytes_this</td><td class=\"bg3\">$bytes_done</td><td class=\"bg3\">$bytes_togo</td><td class=\"bg3\">$bytes_tota</td></tr>
    <tr><th class=\"bg4\">KB</th><td class=\"bg3\">$kbytes_this</td><td class=\"bg3\">$kbytes_done</td><td class=\"bg3\">$kbytes_togo</td><td class=\"bg3\">$kbytes_tota</td></tr>
    <tr><th class=\"bg4\">MB</th><td class=\"bg3\">$mbytes_this</td><td class=\"bg3\">$mbytes_done</td><td class=\"bg3\">$mbytes_togo</td><td class=\"bg3\">$mbytes_tota</td></tr>
    <tr><th class=\"bg4\">%</th><td class=\"bg3\">$pct_this</td><td class=\"bg3\">$pct_done</td><td class=\"bg3\">$pct_togo</td><td class=\"bg3\">$pct_tota</td></tr>
    <tr><th class=\"bg4\">% bar</th><td class=\"bgpctbar\" colspan=\"4\">$pct_bar</td></tr>
    </table>
    </center>
    \n");

// Finish message and restart the script

    if ($linenumber<$_REQUEST["start"]+$linespersession)
    { echo ("<p class=\"successcentr\">Congratulations: End of file reached, assuming OK</p>\n");

      do_action('script_finished');
      $error=true; // This is a semi-error telling the script is finished
    }
    else
    { if ($delaypersession!=0)
        echo ("<p class=\"centr\">Now I'm <b>waiting $delaypersession milliseconds</b> before starting next session...</p>\n");
      if (!$ajax)
        echo ("<script language=\"JavaScript\" type=\"text/javascript\">window.setTimeout('location.href=\"".$_SERVER["PHP_SELF"]."?start=$linenumber&fn=".urlencode($curfilename)."&foffset=$foffset&totalqueries=$totalqueries&delimiter=".urlencode($delimiter)."\";',500+$delaypersession);</script>\n");

      echo ("<noscript>\n");
      echo ("<p class=\"centr\"><a href=\"".$_SERVER["PHP_SELF"]."?start=$linenumber&amp;fn=".urlencode($curfilename)."&amp;foffset=$foffset&amp;totalqueries=$totalqueries&amp;delimiter=".urlencode($delimiter)."\">Continue from the line $linenumber</a> (Enable JavaScript to do it automatically)</p>\n");
      echo ("</noscript>\n");

      echo ("<p class=\"centr\">Press <b><a href=\"".$_SERVER["PHP_SELF"]."\">STOP</a></b> to abort the import <b>OR WAIT!</b></p>\n");
    }
  }
  else
    echo ("<p class=\"error\">Stopped on error</p>\n");

skin_close();

}  //end TESTMODE statistics

}


if ($error && TESTMODE)
  echo ("<p class=\"centr\"><a href=\"".$_SERVER["PHP_SELF"]."\">Start from the beginning</a> (DROP the old tables before restarting)</p>\n");

if ($mysqli) $mysqli->close();
if ($file && !$gzipmode) fclose($file);
else if ($file && $gzipmode) gzclose($file);

// If error or finished put out the whole output from above and stop

if ($verbose) //ART $error && 
{
  $out1 = ob_get_contents();
  ob_end_clean();
  echo $out1;
}else{
   ob_clean();
}


// Anyway put out the output from above
//ob_flush();

  return !$error; //was !$error
}//END MAIN FUNCTIONS

// THE MAIN SCRIPT ENDS HERE

// *******************************************************************************************
// Plugin handling (EXPERIMENTAL)
// *******************************************************************************************

function do_action($tag)
{ global $plugin_actions;

  if (isset($plugin_actions[$tag]))
  { reset ($plugin_actions[$tag]);
    foreach ($plugin_actions[$tag] as $action)
      call_user_func_array($action, array());
  }
}

function add_action($tag, $function)
{
	global $plugin_actions;
	$plugin_actions[$tag][] = $function;
}

function skin_open()
{
  echo ('<div class="skin1">');
}

function skin_close()
{
  echo ('</div>');
}

function error_echo($msg){
    global $error, $errorScriptExecution;
    
    error_log($msg);
    
    $error = true;
    
    $errorScriptExecution = $msg;
    //echo ($msg);
}
?>