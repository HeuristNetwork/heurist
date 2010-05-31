<?php

/* Take a wg_id and fill in top.HEURIST.workgroups[wg_id].members with member details
   and top.HEURIST.workgroups[wg_id].savedSearches with workgroup saved search details */

//define("SAVE_URI", "disabled"); ????? WHAT DOES THAT DO???
require_once(dirname(__FILE__).'/../connect/db.php');
require_once(dirname(__FILE__).'/../connect/cred.php');

function get_search_type($query){
  $search = strstr($query, "w=");
  $search_n = explode("=", $search);
  return $search_n[1];
} //

if (! is_logged_in()) return;

header("Content-type: text/javascript");

$pub_id = @$_REQUEST["pub_id"] ;

if (! $pub_id) {
	print "null";
	return;
}


mysql_connection_db_select(DATABASE);

$res = mysql_query('select a.grp_name, a.grp_id from '.USERS_DATABASE.'.Groups a, '.USERS_DATABASE.'.UserGroups b where b.ug_group_id =a.grp_id and b.ug_user_id = '.get_user_id(). ' ORDER BY a.grp_name');

	 $rows = array();

		while ($row = mysql_fetch_assoc($res)){
		 $rows[$row['grp_id']] = $row['grp_name'];
		}

if (!empty($rows)){//populate with workgroup searches
	  $listquery = 'select * from published_searches LEFT JOIN '.USERS_DATABASE.'.Groups on pub_wg_id = grp_id where pub_usr_id='.get_user_id().' AND pub_temp ="N" AND pub_name <>"" ';
	    foreach ($rows as $key => $value){
	     $listquery .= ' OR pub_wg_id = '.$key;
	  }
	  $listquery .= '  order by grp_name, pub_name ';
	}else{
      $listquery = 'select * from published_searches where pub_usr_id='.get_user_id() .' AND pub_temp ="N" AND pub_name <>"" order by pub_name ';
	}
	$res = mysql_query($listquery);

	if ($res) {
      while ($pubs = mysql_fetch_assoc($res)){
        $id = $pubs['pub_id'];

		if ($pubs['pub_wg_id'] != 0){	   //in case its a workgroup search handle in differently
		 $wg_res[] = array($pubs['pub_id'], $pubs['grp_name'], $pubs['pub_name']);
	    }else {//different background colours depending on search type and  sort by type: my resources, all resources - 14/09/2007
          $search = get_search_type($pubs['pub_query']);
	        if ($search == 'all'){
			  $all_res[] = array($pubs['pub_id'], $pubs['pub_name']);
			}elseif($search == 'bookmark'){
			  $my_res[] = array($pubs['pub_id'], $pubs['pub_name']);
			}
		}
	}
}


?>

{
	"wgSearches": [ <?php

$first = true;
foreach ($wg_res as $value) {
    if (! $first) print ",";  print "\n"; $first = false;
    print "\t\t{ \"id\": \"".addslashes($value[0])."\", \"group\": \"".addslashes($value[1])."\", \"label\": \"".addslashes($value[2])."\" }";

}?>

	],

	"mySearches": [ <?php

$first = true;
foreach ($my_res as $value) {
    if (! $first) print ",";  print "\n"; $first = false;
	print "\t\t{ \"id\": \"".addslashes($value[0])."\", \"label\": \"".addslashes($value[1])."\" }";
}
?>

	],

	"allSearches": [ <?php
$first = true;
foreach ($my_res as $value) {
    if (! $first) print ",";  print "\n"; $first = false;
	print "\t\t{ \"id\": \"".addslashes($value[0])."\", \"label\": \"".addslashes($value[1])."\" }";
}
?>

	]
}

