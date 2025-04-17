<?php
/**
*  WebSiteTemplate.php - basic Heurist CMS website template
*   It is included into WebSite.php. $this - instances of WebSite
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     7.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
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
    <meta http-equiv="Lang" content="<?php $this->meta('lang');?>">

    <meta name="keywords" content="<?php echo $this->meta('keywords');?>,Heurist, Digital Humanities, Humanities Data, Research Data, Database Management, Academic data, Open Source, Free software, FOSS">
    <meta name="description" content="<?php $this->meta('description');?>">
    <meta name="author" content="<?php $this->meta('author');?>">
    <meta name="creation-date" content="<?php $this->meta('creation_data');?>">
    <!-- <meta name="revisit-after" content="15 days"> -->

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="<?php $this->meta('favicon');?>"> <!--  type="image/x-icon" -->
    <link rel="shortcut icon" href="<?php $this->meta('favicon');?>">

    <?php    
        //includes minimal required set of heurist scripts and styles
        include_once 'WebSiteScripts.php';
    ?>
</head>
<body>

<?php echo $this->getPageMargin('header');?>

<main> <!--  class="container mt-3 pt-5" -->
<?php $this->getPageContent();?>
</main>

<?php echo $this->getPageMargin('footer');?>
</body>
</html>