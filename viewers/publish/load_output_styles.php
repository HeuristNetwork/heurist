<?php
/**
 * load_output_styles.php - to  load stylesheet names into the dropdown list for heurist publishing wizard
 *
 * @package load_output_styles.php
 * @version 2007-06-13
 * @author Maria Shvedova
 * (c) 2007 Archaeological Computing Laboratory, University of Sydney
 */

 /**
  *constants
  */

define('DIR', 'xsl');

/**
 * This function loads stylesheet names into the dropdown list for heurist publishing wizard
 * @return  array  - array of stylesheet [stylesheetname]=>Name of Style
 */

function load_output_styles(){

$arr_files = array();
$arr_outputs = array();

//open directory and read in file names
if (is_dir(DIR)) {
    if ($dh = opendir(DIR)) {
        while (($file = readdir($dh)) !== false) {
          $arr_files[] = $file;

        }
        closedir($dh);
    }
}

foreach($arr_files as $filename){
//if file is a stylesheet file
 if (eregi ('.xsl', $filename)){
 //read the required contents of the file.
  $handle = fopen(DIR."/".$filename, "rb");
  $contents = fread($handle, filesize(DIR."/".$filename));
  fclose($handle);

    if (eregi('<xsl:comment>', $contents)){

    $out1 = explode('[output]', $contents);
    $out = explode ('[/output]', $out1[1]);

    $name1 = explode('[name]', $contents);
    $name = explode('[/name]', $name1[1]);

    //if not empty, read in the styles
    if ($out[0] && $name[0]){
      $styles[$out[0]] = $name[0];
    }
   }

  }
}

return $styles;

}
?>
