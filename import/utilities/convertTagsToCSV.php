<?php

    /**
    * convertTagsToCSV.php: Reads records consisting of lines startign with tags, and creates a CSV file
    *                       Tags are separated from values by space(s) or tabs
    *                       Start of each record represented by specificed tag
    *                       Lines starting with specified character are treated as comment lines
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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


    // TODO: upload file for processing rather than requiring user to place on server

    // TODO: recognise sub tags identified by Tag= within lines eg.  Death:  Place=Amiens Date=1915
    //       should be rendered as Death:Place and Death:Date columns
    //       (use : separator so you can have Death:Place and Birth:Place in same datafile)


?>

<html>
    <head>
        <title>Convert tag-value files to CSV</title>
    </head>
    <body>

    <h3>Convert tag-value files to CSV</h3>

        <?php

            // -----------------------------------------------------------------------------------------------------


            function processRecord($arr,$fields) { // writes out a line of CSV for a record in $arr[]
                $first = TRUE; // no separator before first field
                foreach ($fields as $fld) {
                    if (!$first) echo ','; // separators required before field
                    echo $arr[$fld];
                    $first = FALSE;
                }
                echo '<br />';
            } // processRecord


            // -----------------------------------------------------------------------------------------------------


            // Step 1 = get filename and delimiter Step 2 = process file
            $step = @$_REQUEST['step'];

            if ($step != '2'){ // step 1 = get filename

                print "Note: This function simply operates on a text file; it is independent of any database.<br /><br />
                    The first line should contain a list of comma-separated tags, starting with the tag which will be
                    encountered at the start of each record (normally the Record ID tag). This tag marks the end of the previous record.<br />
                    Subsequent lines start with tags separated from a value by one or more space(s) or Tab.
                    Output is CSV format to the screen - copy and paste to a file.<br />
                    <br />
                    If marking up a file of free-format text in order to import as a set of fields, you may wish to prepare files in a format such as<br />
                    <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;RecordID xxxxx  F1=xxxx F2=xxxx F3=xxxx <br /><br />
                    where F1, F2 and F3 are field/column names in no particular order, then use your editor to globally<br />
                    change F1= to <newline> F1<space> etc. to create a set of tag-value lines suitable for input to this function.<br /><br />
                    <hr><br />";
                print "<form id='setupform' name='setupform' action='convertTagsToCSV.php' method='get'>";
                print "<input name='step' value='2' type='hidden'>"; // trigger step 2 when submitted and called again
                print "Comment lines start with the following character ";
                print "<select name='commentChar'>";
                print "<option value='>'>></option>";
                print "<option value='#'>#</option>";
                print "<option value='!'>!</option>";
                print "<option value='@'>@</option>";
                print "<option value='$'>$</option>";
                print "<option value='%'>%</option>";
                print "<option value='&'>&</option>";
                print "<option value='*'>*</option>";
                print "</select><br /><br />";
                print "Enter path and name of tag-value file to process <input type='text' name = 'fileName'>";
                print "<input type='submit' value='Go' style='font-weight: bold;'>";
            }

            // -----------------------------------------------------------------------------------------------------

            else

            { // step 2 = process file}

                print "Copy and paste output below to a file. See end of output for list of errors.<br />
                <hr><br />";

                $myFile = @$_GET["fileName"];
                $commentChar = @$_GET["commentChar"];

                $topOfFile = TRUE; // flag we are just starting so no record to write out

                $handle = fopen($myFile, 'r');
                if ($handle == FALSE) {
                    echo 'Unable to open "' .$myFile. '" - please check that it is readable by PHP';
                    die;
                }

                $arr = array (); // initialise array to hold data read for current record
                $errs = array (); // list of errors

                // Read first line of data which should be a list of the expected tags
                $data = strtoupper(trim(fgets($handle, 10000))); $count++; // all matching in upper case to avoid mismatch
                $fields2 = explode(",", $data); // array of tags to be represented as columns/fields
                $first = TRUE;
                foreach ($fields2 as $value) {
                    $fields[] = trim($value); // spaces will throw out matching
                    if (!$first) echo ','; // separators required before field
                    echo trim($value);
                    $first = FALSE;
                }
                echo '<br />'; // column headers in output
                $startOfRecordTag=$fields[0];
                $count = 1;

                while (!feof($handle)) {

                    $data = trim(fgets($handle, 10000)); $count++; // read a line, max 10K

                    // skip blank lines
                    while ((strlen($data) == 0) && (!feof($handle))) { // ignore blank lines
                        $data = trim(fgets($handle, 10000)); $count++; // read another line
                    } // skipping blank lines

                    // skip comment lines
                    $firstChar= substr($data,0,1); //first character
                    while (($firstChar == $commentChar) && (!feof($handle))) { // ignore lines starting with comment char
                        $data = trim(fgets($handle, 10000)); $count++; // read another line
                        $firstChar= substr($data,0,1);
                    } // skipping comments

                    // extract the tag string from the line read
                    $res = trim(strtoupper(stristr($data, ' ', true))); // first word of string
                    $val = trim(stristr($data, ' ', false)); // rest of string
                    if ($res === false ) { // not space, try tab NOTE: THIS DOES NOT WORK?
                        $res = trim(strtoupper(stristr($data, '\t', true)));
                        $val = trim(stristr($data, '\t', false)); // rest of string
                    }

                    // Detect and process start of new record
                    if ($res == $startOfRecordTag) {
                        if ($topOfFile) { // top of file = no record to process
                            $topOfFile = FALSE;
                        } else {
                            processRecord($arr,$fields); // write CSV line for previous record
                        }
                        $arr = array (); // reset for new record
                        $arr[$res] = $val; //set record ID in results array
                    } // end processing start of record

                    // data value - line with tag which is not start of record
                    else {
                        $danger = "'" . '"' . ','; // characters requiring enclosure in ""
                        if (strpbrk ($val , $danger) == FALSE) { // false if $danger not matched
                            $needsQuotes = FALSE;
                        } else {
                            $needsQuotes = TRUE;
                        }
                        $val = str_replace ( '"' ,'""' , $val); // escape double quotes with doubled up
                        if ($needsQuotes) { // if string contains quotes or commas, enclose in double quotes
                            $val = '"'.$val.'"';
                        }
                        if ($arr[$res]) {$sep='|';} else {$sep='';} // avoid | before first value in array element
                        $arr[$res] = $arr[$res].$sep.$val; // adds value to previously read value(s), if any
                        if (!in_array($res, $fields)) { // check if tag is in the valid set of tags/fields
                            array_push($errs,'Line '.$count.' Bad tag or no data: '.$data); // add this line as an error
                        }
                    } // end data value


                } // end loop through lines

                fclose($handle);

                // report errors encountered
                echo '<br /><hr><br />';
                foreach ($errs as $err) {
                    echo $err.'<br />';
                }

            } // end step 2 - process file

        ?>

    </body>
 </html>
