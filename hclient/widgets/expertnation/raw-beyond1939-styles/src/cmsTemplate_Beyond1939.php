<?php

/*
    * CMS website template

    * Beyond 1939: The University of Sydney and World War II

    * This is a Heurist CMS website template. As such, it contains two compulsory elements:
    * #main-menu - used by the navigation system to create and operate the site menu
    * #main-content - used by the navigation system to load content from the site menu

    * Available variables:

    * $website_title
    * $meta_keywords
    * $meta_description
    * $image_icon
    
    * $home_page_record_id - record id of website home page
    * $open_page_on_init - record id for cms menu/page to be loaded on init
    
    * $image_banner - header background banner image
    * $page_header_menu - code to define main menu widget, leave it unchanged as content of main-menu div
    * $page_header - custom content for main-header defined in website home page record
    * $page_footer - custom content for footer

    * Available constants

    * HEURIST_BASE_URL
    
    * @package Heurist academic knowledge management system
    * @link http://HeuristNetwork.org
    * @copyright (C) 2005-2020 University of Sydney
    * @author Michael Falk <michael.falk@sydney.edu.au>
    * @author Artem Osmakov <artem.osmakov@sydney.edu.au>
    * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version 6
    */

/*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

?>

<html>

<head>
    <title><?php print htmlspecialchars(strip_tags($website_title)); ?></title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="keywords" content="Heurist, Digital Humanities, Humanities Data, Research Data, Database Management, Academic data, Open Source, Free software, FOSS, University of Sydney,<?php echo $meta_keywords; ?>">
    <meta name="description" content="<?php echo $meta_description; ?>">
    <link rel="icon" href="<?php echo $image_icon; ?>"> <!--  type="image/x-icon" -->
    <link rel="shortcut icon" href="<?php echo $image_icon; ?>">

    <?php
    include $websiteScriptAndStyles_php;  //include heurist scripts and styles
    $faq_page_id = 121976; // Presumably this won't change, but if it does, then they will need to be edited here.
    ?>
</head>

<body>
    <?php
    if (!$edit_OldEditor && $system->is_member(1)) {
        print '<a href="' . HEURIST_BASE_URL . '?db=' . $system->dbname() . '&cms=' . $home_page_record_id . '" id="btn_editor" target="_blank" '
            . 'style="position:absolute;right:70px; top:5px; z-index: 9999;" class="cms-button">Heurist interface</a>'
            . '<a href="#" id="btnOpenCMSeditor" onclick="_openCMSeditor(event); return false;" '
            . 'style="position:absolute;right:10px; top:5px; z-index: 9999;" class="cms-button">CMS</a>';
    }

    if ($isWebPage) { //set in websiteRecord.php 
        //WEB PAGE - EMBED – TODO
    } else {
    ?>
        <nav class="navbar navbar-default bor-utility-nav">
            <div class="container">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bor-navbar-collapse">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="fa fa-bars"></span>
                    </button>
                    <a class="navbar-brand bor-sydney-logo" href="<?= $home_page_record_id?>">
                        Beyond 1939</a>
                </div>
                <div id="main-menu" class="collapse navbar-collapse bor-nav"></div>
            </div>
        </nav>
        <div id="main-content-container" class="container">
            <header class="bor-header">
                <a href="<?= $home_page_record_id ?>" class="bor-logo">
                    <h1 class="bor-header-title sr-only">Beyond 1939</h1>
                    <div class="bor-header-sub-title sr-only">The University of Sydney and World War II</div>
                </a>
                <div class="horizontal bor-header-search" role="search">
                    <fieldset>
                        <div class="form-group">
                            <label class="col-lg-12 control-label sr-only" for="query">Enter your search terms to search the Beyond 1939 database</label>
                            <div class="col-lg-12 bor-search-body">
                                <input id="search_query" type="search" aria-label="Searchbox for finding people in the database" placeholder="Search for people in the database" style="min-width: 40ex;" class="form-control input-lg" value="">
                                <button id="search_button" class="submit fa-search ui-icon ui-icon-search" style="height:30px"><i>&nbsp;</i></button>
                                <div class="col-lg-12">
                                    <a style="color:white;text-decoration:underline" id="help_link" href="<?= $faq_page_id?>">How do I use this?</a>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>
            </header>
            <div id="main-content"></div>
            <footer class="bor-footer">
                <div>
                    <hr class="bor-footer-separator">
                    <span class="bor-footer-separator-icon">❉</span>
                    <hr class="bor-footer-separator">
                </div>
                <div class="bor-sponsors">
                    <div>Generously supported by:</div>
                    <div>
                        <!-- was p -->
                        <div class="lead">The University of Sydney Chancellor’s Committee</div>
                        <div>with additional support from</div>
                        <img class="bor-sponsors-logo" src="hclient/widgets/expertnation/assets/beyond1914/images/common/sponsors/SAC.png" alt="Saint Andrew's College">
                        <img class="bor-sponsors-logo" src="hclient/widgets/expertnation/assets/beyond1914/images/common/sponsors/StJohns.png" alt="St John's College">
                        <img class="bor-sponsors-logo" src="hclient/widgets/expertnation/assets/beyond1914/images/common/sponsors/st-pauls-college.png" alt="Saint Paul's College">
                        <img class="bor-sponsors-logo" src="hclient/widgets/expertnation/assets/beyond1914/images/common/sponsors/WC.png" alt="The Women's College">
                    </div>
                </div>
                <div class="bor-photographer">
                    <div>Background photo credit TBC</div>
                </div>
                <div>
                    <hr class="bor-footer-separator">
                    <span class="bor-footer-separator-icon">❉</span>
                    <hr class="bor-footer-separator">
                </div>
                <div class="bor-legal">
                    <small>© 2022- The University of Sydney. ABN: 15 211 513 464. CRICOS Number: 00026A.</small>
                </div>
            </footer>
        </div>
    <?php } ?>
</body>