<?php

    /**
    *  CMS website template 
    * 
    *  You may use several templates per server (for specific websites).
    *  We encourage the following of naming convention cmsTemplate_OrganisationName.php
    *  Copy to /HEURIST root folder and specify this name in the field "Website Template" (field 2-922) 
    *  in the Advanced tab of the CMS home page record.
    * 
    *  The template can also be specified as a relative path hclient/widgets/cms/<template name> but this
    *  should ONLY be used for development as it uses a path which might change and local changes could
    *  get overwritten by code updates.
    * 
    *  The template must contain at least two html elements main-header with main-menu and
    *  main-content
    * 
                <div id="main-header" style="width:100%;min-height:40px;">
                    <div id="main-menu" class="mceNonEditable header-element" 
                        style="width:100%;min-height:40px;color:black;font-size:1.1em;" 
                        data-heurist-app-id="heurist_Navigation" data-generated="1">
                        <?php print $page_header_menu; ?>
</div>
</div>
<div id="main-content" data-homepageid="<?php print $home_page_record_id;?>"
    <?php print ($open_page_on_init>0)?' data-initid="'.$open_page_on_init.'"':''; ?>
    data-viewonly="<?php print ($hasAccess)?0:1;?>">
</div>
*
* besides main-menu main-header may have main-logo, main-logo-alt, main-host, main-pagetitle divs
* These divs will be filled with images and text defined in website home record.
*
* main-content - is the target div for content of particular page to be loaded
*
* There are following variables (their values are defined in website home record) that can be used in html header
* $website_title
* $meta_keywords
* $meta_description
* $image_icon
*
* Other variables are
* $home_page_record_id - record id of website home page
* $open_page_on_init - record id for cms menu/page to be loaded on init
*
* $image_banner - header background banner image
* $page_header_menu - code to define main menu widget, leave it unchanged as content of main-menu div
* $page_header - custom content for main-header defined in website home page record
* $page_footer - custom content for footer
*
* @package Heurist academic knowledge management system
* @link http://HeuristNetwork.org
* @copyright (C) 2005-2020 University of Sydney
* @author Artem Osmakov <artem.osmakov@sydney.edu.au>
    * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version 4.0
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
        <title><?php print htmlspecialchars(strip_tags($website_title));?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="keywords"
            content="Heurist, Digital Humanities, Humanities Data, Research Data, Database Management, Academic data, Open Source, Free software, FOSS, University of Sydney,<?php echo $meta_keywords;?>">
        <meta name="description" content="<?php echo $meta_description;?>">
        <link rel="icon" href="<?php echo $image_icon;?>"> <!--  type="image/x-icon" -->
        <link rel="shortcut icon" href="<?php echo $image_icon;?>">

        <?php
        include $websiteScriptAndStyles_php;  //include heurist scripts and styles
    ?>


        <style>
            /* page (menu) title it is added to main-pagetitle */
            #main-content {
                display: none;
            }
            .cms-button {
                font-size: 0.8em;
                font-weight: bold;
                color: blue;
            }
        </style>
    </head>

    <body>
        <?php
/*
default content consists of 
    #main-header - header width logo, banner, hostinfo and main menu
        #main-logo, #main-logo-alt, #main-host, #main-menu, #main-pagetitle
        
    #main-content-container > #main-content - target the content of particular page will be loaded  
*/
if(!$edit_OldEditor && $system->is_member(1)){
        print '<a href="'.HEURIST_BASE_URL.'?db='.$system->dbname().'&cms='.$home_page_record_id.'" id="btn_editor" target="_blank" '
        .'style="position:absolute;right:70px; top:5px; z-index: 9999;" class="cms-button">Heurist interface</a>'
        .'<a href="#" id="btnOpenCMSeditor" onclick="_openCMSeditor(event); return false;" '
        .'style="position:absolute;right:10px; top:5px; z-index: 9999;" class="cms-button">CMS</a>';
    
    }

if($isWebPage){ //set in websiteRecord.php 
//WEB PAGE - EMBED
?>
        <div class="ent_wrapper">
            <?php
    if($showWarnAboutPublic){
        print '<div style="top:0;height:20px;position:absolute;text-align:center;width:100%;color:red;">Web page record is not public. It will not be visible to the public</div>';  
    }
?>
            <div class="ent_content_full ui-heurist-bg-light" style="top:<?php echo ($showWarnAboutPublic)?20:0; ?>px"
                id="main-content-container">
                <div id="main-content" data-homepageid="<?php print $home_page_record_id;?>"
                    data-viewonly="<?php print ($hasAccess)?0:1;?>">
                </div>
            </div>
        </div>
        <?php
        
//WEB SITE      
}else{
?>

        <div class="ent_wrapper">
            <div id="main-header" role="banner"
                class="mceNonEditable header-element ui-layout-main-menu ui-widget-content navbar navbar-default navbar-fixed-top affix-top">
                <div class="container">
                    <div class="navbar-title">Manuscripta Bibliae Hebraicae</div>
                    <div id="custom-logo" class="navbar-header"><a class="logo navbar-btn pull-left" href="#"
                            title="Manuscripta Bibliae Hebraicae" style="background-image: none!important;"> 
                            <!-- Need to override default Heurist styles, which impose the Heurist logo on anything classed 'logo' TODO: Future-proof MBH stylesheet with new class -->
                            <img src="<?php echo HEURIST_BASE_URL?>?db=MBH_Manuscripta_Bibliae_Hebraicae&file=dada5f621fea794510e1abf27f8799783f1a1684"
                                alt="Manuscripta Bibliae Hebraicae">
                        </a></div>
                    <div class="navbar-slogan">
                        Les manuscrits de la Bible hébraïque en Europe occidentale (Angleterre, France, Allemagne,
                        Italie du Nord) au XIIe et XIIIe siècle&nbsp;: une approche matérielle, culturelle et sociale
                    </div>
                    <div class="navbar-social-network">
                        <a href="https://twitter.com/projetMBH" class="tw" target="_blank"><img
                                src="<?php echo HEURIST_BASE_URL?>?db=MBH_Manuscripta_Bibliae_Hebraicae&file=1be81f1bc799e511f9f766da4119a297c0383240"
                                width="24" alt="Twitter"></a>&nbsp;<a
                            href="https://www.facebook.com/manuscriptabibliaehebraicae/" class="fb" target="_blank"><img
                                src="<?php echo HEURIST_BASE_URL?>?db=MBH_Manuscripta_Bibliae_Hebraicae&file=082682853f46e477acd87c3c8e55cabd1aeb9bbc"
                                width="24" alt="Facebook"></a>
                    </div>

                    <div class="region region-language-switcher">
                        <section id="block-locale-language" class="block block-locale clearfix">


                            <ul class="language-switcher-locale-url">
                                <li class="fr first active"><a href="#" class="language-link active" xml:lang="fr"
                                        title="Le projet Manuscripta Bibliae Hebraicae">FR</a></li>
                                <li class="en last"><a href="#" class="language-link" xml:lang="en"
                                        title="The Manuscripta Bibliae Hebraicae Project">EN</a></li>
                            </ul>
                        </section>
                    </div>

                    <div id="main-menu" class="navbar-collapse collapse" data-heurist-app-id="heurist_Navigation"
                        data-generated="1"></div>
                </div>
            </div>

            <?php        
    }//header

    
    ?>
        </div>
        <div class="ent_content_full  ui-heurist-bg-light" id="main-content-container">
            <div id="main-content" data-homepageid="<?php print $home_page_record_id;?>"
                <?php print ($open_page_on_init>0)?'data-initid="'.$open_page_on_init.'"':''; ?>
                data-viewonly="<?php print ($hasAccess)?0:1;?>">
            </div>
            <footer class="footer">
                <div class="open-book-effect-sym">
                    <span class="b"></span>
                    <span class="b"></span>
                    <span class="b"></span>
                    <span class="b"></span>
                    <span class="b"></span>
                    <span class="b"></span>
                </div>
                <div class="footer-content">
                    <div class="container">
                        <div class="row">
                            <div class="col-md-2"><img class="logo"
                                    src="https://heurist.huma-num.fr/h6-alpha/?db=MBH_Manuscripta_Bibliae_Hebraicae&file=791d3bd1e6a1bce0dd6292610c1c1bde4882d637"
                                    alt="Manuscripta Bibliae Hebraicae"></div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-6 address">
                                        <address>Maison Méditerranéenne des Sciences de l'Homme<br>
                                            5 rue du château de l'horloge, BP 647<br>
                                            13094 Aix-en-Provence Cedex 2<br>
                                            France</address>
                                    </div>
                                    <div class="col-md-6 phone">Phone: +33 4 42 52 43 64</div>
                                </div>


                            </div>
                            <div class="col-md-1">

                            </div>
                            <div class="col-md-3 social-networks">Suivez-nous sur les réseaux sociaux<br><br><a
                                    href="https://twitter.com/projetMBH" class="tw" target="_blank"><img
                                        src="https://heurist.huma-num.fr/h6-alpha/?db=MBH_Manuscripta_Bibliae_Hebraicae&file=1be81f1bc799e511f9f766da4119a297c0383240"
                                        width="32" alt="Twitter"></a>&nbsp;<a
                                    href="https://www.facebook.com/manuscriptabibliaehebraicae/" class="fb"
                                    target="_blank"><img
                                        src="https://heurist.huma-num.fr/h6-alpha/?db=MBH_Manuscripta_Bibliae_Hebraicae&file=082682853f46e477acd87c3c8e55cabd1aeb9bbc"
                                        width="32" alt="Facebook"></a></div>
                        </div>
                    </div>
                </div>
                <div class="region region-footer">
                    <div class="container">
                        <section id="block-block-3" class="block block-block clearfix">


                            <p><span style="font-size: 13.008px;">©</span><span
                                    style="font-size: 13.008px;">&nbsp;</span>MBH 2022 -
                                Tous droits réservés&nbsp;-&nbsp;<a
                                    href="/heurist/?db=MBH_Manuscripta_Bibliae_Hebraicae&website&id=1408&pageid=1414"
                                    title="Mentions légales">Mentions
                                    légales</a> - <a
                                    href="//heurist/?db=MBH_Manuscripta_Bibliae_Hebraicae&website&id=1408&pageid=1415">Conditions
                                    Générales
                                    d'Utilisation</a>&nbsp;-&nbsp;<a
                                    href="/heurist/?db=MBH_Manuscripta_Bibliae_Hebraicae&website&id=1408&pageid=1413">Contact</a> - 
                                Fièrement alimenté par <a href="https://heuristnetwork.org/">Heurist</a> @
                                <a href="https://huma-num.fr">Huma-Num</a>
                            </p>
                        </section>
                    </div>
                </div>
            </footer>
        </div>
        </div>
    </body>

    </html>