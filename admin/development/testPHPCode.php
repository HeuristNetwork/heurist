<?php

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

// Deals with all the database connections stuff

    mysql_connection_db_select(DATABASE);
//place code here
$res = mysql_query("select rtg_ID,rty_ID, rty_ShowInLists
						from defRecTypes left join defRecTypeGroups on rtg_ID = (select substring_index(rty_RecTypeGroupIDs,',',1))
						where 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
while ($row = mysql_fetch_assoc($res)) {
//error_log(print_r($row,true));
	if (!array_key_exists($row['rtg_ID'],$typesByGroup)){
		$typesByGroup[$row['rtg_ID']] = array();
	}
	$typesByGroup[$row['rtg_ID']][$row["rty_ID"]] = $row["rty_ShowInLists"];
}
 echo print_r($typesByGroup,true);
?>
