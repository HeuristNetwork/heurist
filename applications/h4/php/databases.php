<?php
/**
* Produces the page - list of available databases
*/

require_once(dirname(__FILE__)."/System.php");

$system = new System();

if(! $system->init(@$_REQUEST['db'], false) ){  //init with
   //@todo - redirect to error page
   print_r($system->getError(),true);
   exit();
}
?>
<html>
<head>
  <title><?=HEURIST_TITLE?></title>

  <link rel="stylesheet" href="../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />
  <link rel="stylesheet" type="text/css" href="../style.css">

  <script type="text/javascript">
  </script>

</head>
<body style="padding:44px;">
  <div class="ui-corner-all ui-widget-content" style="text-align:left; width:300px; margin:0px auto; padding: 0.5em;">

    <div class="logo"></div>
    <div style="padding: 0.5em;">Please select a database from the list</div>
    <div style="overflow-y:auto;display: inline-block;width:100%;height:80%">

        <ul class="db-list">
<?php
    //get list of databases on this server (in dbutils.php)
    $list =  mysql__getdatabases($system->get_mysqli());

    /* DEBUG for($i=0;$i<100;$i++) {
        array_push($list, "database".$i);
    }*/
    foreach ($list as $name) {
            print("<li><a href='".HEURIST_SERVER_NAME."?db=$name'>$name</a></li>");
    }

?>
        </ul>

  </div>

  </div>
</body>
</html>
