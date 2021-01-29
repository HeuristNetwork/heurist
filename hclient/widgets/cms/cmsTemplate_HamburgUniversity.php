<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="de">
    <head>
        <title>ZFDM Heurist Service</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta content="Hagen Peukert" name="author">
        <meta content="Open Source collaborative web database software for richly interlinked heterogeneous research data" name="description">

        <!-- 9 Oct 2019: The styles were commented out. Probably the stylesheets no longer contain
        styles for the homepage - to check - as the page is largely unstyled, but the button styles 
        ARE used and work since removal of the comments - so why were these commented out? -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

        <link rel="alternate" hreflang="de" href="index.html">
        <link rel="alternate" hreflang="en" href="index_en.html">

        <!--style type="text/css">
            button {width:200px;}
            .buttons {display: table-cell; padding-right: 20px; text-align: left; vertical-align: middle; width: 250;}
            .description {width:400px; display:table-cell; text-align:left;vertical-align: middle; margin-left: 20px; margin-top:10px; margin-bottom:20px;}
            .options {display: table; margin: 0 auto; padding: 10px 0; text-align: left;}
            body {overflow-y:auto}
            #page {left:10%; right:10%}
            .small {width:13px;margin:0 auto}
        </style-->
        
        <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>

    <?php
        include 'heurist_scripts_and_styles.php'  
    ?>
    <style>
        body{
            overflow:auto;
        }
    </style>
    </head>

    <body>
        <div class="suche" id="uhh-header-search">
          <form action="https://www.fdm.uni-hamburg.de/search.html">
            <h6 class="versteckt">Suche</h6>
            <label for="suchfeld" class="versteckt">Suche nach </label>
            <input id="suchfeld" placeholder="Suche" name="q" class="text" type="text">
            <input name="suchen" class="button" alt="suchen" title="suchen" type="submit">
          </form>
        </div>

        <div id="uhh-header-sublogo">
          <a href="#">
            <div class="kurz">ZFDM</div>
            <h1 class="wortmarke"><b>Zentrum</b>für Nachhaltiges Forschungsdatenmanagement</h1>
          </a>
        </div>

        <div id="uhh-header-nav">
          <ul>
            <li><a href="#1">Service</a>
              <ul>
                <li><a href="html/heurist/index.php?db=">Browse Datenbanken</a></li>
                <li><a href="html/heurist/admin/setup/dbcreate/createNewDB.php">Datenbank erstellen</a></li>
              </ul>
            </li>
            <li><a href="#2">Hilfe</a>
              <ul>
                <li><a href="#3">Tutorial</a></li>
                <li><a href="http://heuristnetwork.org/faq/">FAQ</a></li>
                <li><a href="http://HeuristNetwork.org">HeuristNetwork.org</a></li>
              </ul>
            </li>
            <li><a href="#5">About</a>
              <ul>
                <li><a href="https://www.fdm.uni-hamburg.de/kontakt.html">Kontakt</a></li>
                <li><a href="https://www.fdm.uni-hamburg.de/imprint.html">Impressum</a></li>
              </ul>
            </li>
          </ul>
        </div>
        <div class="page" style="overflow:auto">
                <div id="main-header" style="width:100%;min-height:40px;">
                    <div id="main-menu" class="mceNonEditable header-element" 
                        style="width:100%;min-height:40px;border:2px none yellow;color:black;font-size:1.1em;" 
                        data-heurist-app-id="heurist_Navigation" data-generated="1">
                        <?php print $page_header_menu; ?>
                    </div>
                </div>
                <div id="main-content" 
                    data-homepageid="<?php print $rec_id;?>" 
                    <?php print ($open_page_on_init>0)?' data-initid="'.$open_page_on_init.'"':''; ?> 
                    data-viewonly="<?php print ($hasAccess)?0:1;?>">
                </div>
<!--                
            <div class="container"> 
            </div>
-->            
        </div>
        <div id="uhh-footer-info">
          <ul>
            <li><a href="https://www.fdm.uni-hamburg.de/imprint.html">Impressum</a></li>
            <li><a href="https://www.fdm.uni-hamburg.de/privacypolicy.html">Datenschutzerklärung</a></li>
          </ul>
        </div>
    </body>
    <script id="UHH-DOM" src="https://www.uni-hamburg.de/onTEAM/inc/dom/v43/insert.js" async
      data-options='{
        "header": {
          "language":{},
          "sublogo":{},
          "mobilemenu":{"dom":""},
          "nav":{}, 
          "search":{}
        },
        "footer": {
            "info":{}
    },
    "links": {
      "leichtesprache":"leichtesprache.html",
      "barrierefreiheit":"barrierefreiheit.html"
    }
      }'>    
    </script>
</html>
