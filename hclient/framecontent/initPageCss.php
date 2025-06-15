<?php
/**
* initPageCss.php - Generates and outputs CSS styles for Heurist pages
* 
* Includes the minimum set of CSS for Heurist pages.
* This file links base jQuery UI CSS, core Heurist styles (h4styles.css),
* potentially h6styles.css based on the layout, and includes dynamic theme-specific CSS.
*
* @package     Heurist academic knowledge management system
* @subpackage  hclient\framecontent
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
?>
<!-- jQuery UI CSS -->
<link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<!-- Heurist CSS -->
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
<?php
    $lt = @$_REQUEST['ll'];
    if($lt!='H5Default'){

//special webfont for database
if(isset($system) && $system->isInited()){
    $font_styles = $system->settings->getWebFontsLinks('ui-sans-serif');
    if(!isEmptyStr($font_styles)){
         echo "<style> $font_styles </style>";
    }
}
?>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h6styles.css" />
<?php } ?>
<!-- Heurist Color Themes -->
<style id="heurist_color_theme">
<?php
//was PDIR.
    include_once dirname(__FILE__).'/initPageTheme.php';
?>
</style>
