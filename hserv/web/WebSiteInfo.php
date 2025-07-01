<?php
/**
*  WebSiteTemplate.php - basic Heurist CMS website template
*   It is included into WebSite.php. $this - instances of WebSite
*
* @project     Heurist academic knowledge management system
* @package CMS
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @since       7.0
*/
?>
<!DOCTYPE html>
<html lang="<?php $this->meta('lang');?>">
<head>
    <title><?php $this->meta('title');?></title>
    <meta name="robots" content="all">
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta http-equiv="pragma" content="no-cache">
    <meta http-equiv="cache-control" content="no-cache">    
    <meta http-equiv="Lang" content="en">

    <meta name="keywords" content="Heurist, Digital Humanities, Humanities Data, Research Data, Database Management, Academic data, Open Source, Free software, FOSS">
    <meta name="creation-date" content="<?php $this->meta('creation_data');?>">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="<?php $this->meta('favicon');?>"> <!--  type="image/x-icon" -->
    <link rel="shortcut icon" href="<?php $this->meta('favicon');?>">

    <?php    
        //includes minimal required set of heurist scripts and styles
        include_once 'WebSiteScripts.php';
    ?>
</head>
<body>

<main id="main-content" class="container-sm d-flex flex-column min-vh-100 justify-content-center">
    
    <div class="bg-secondary-subtle p-3 align-self-center">

        <div class="logo bg-primary-subtle p-1 w-100"></div>

        <div class="w-90 mx-auto p-2" style="">
            <span class="ui-icon ui-icon-info" style="float: left; margin-right:.3em;font-weight:bold"></span>
            <?php echo $this->messageError;?>
        </div>
    </div>
</main>    
    
</body>
</html>