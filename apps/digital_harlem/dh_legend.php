<?php
    require_once(dirname(__FILE__)."/../../php/System.php");
   
    $statistics = "";
    $system = new System();
    // connect to given database
    if(!(@$_REQUEST['db'] && $system->init(@$_REQUEST['db']))){
        exit;   
    }
/*
<html>
<head>
<link rel="stylesheet" type="text/css" href="../../style3.css">
</head>
<body>
</body>
</html>
*/
?>  
<div id="legendtable">
<p><b>PLACES</b></p>
<table>
  <tbody>
  <tr>
    <td class="legend_icon"><img src="<?=HEURIST_TERM_ICON_URL?>4326.png"></td>
    <td class="legend_text">Address in DB</td>
  </tr>
</tbody></table>


<p><b>PEOPLE</b></p>
<table>
<tbody>
<?php
    $query = "SELECT trm_ID, trm_Label, trm_Code from defTerms where trm_ParentTermID=3306 ORDER BY trm_Label";
    $res = $system->get_mysqli()->query($query);
    while($row = $res->fetch_assoc()) {
            $filename = HEURIST_TERM_ICON_URL.$row["trm_ID"].".png";
//print $filename."<br>";            
//print HEURIST_TERM_ICON_DIR.$row["trm_ID"].".png<br>";
            if(file_exists(HEURIST_TERM_ICON_DIR.$row["trm_ID"].".png")){
print '<tr><td class="legend_icon"><img src="'.$filename.'"></td>';
print '<td class="legend_text">'.$row["trm_Label"].'</td></tr>';
                
            }
    }
?>
</tbody>
</table>

<p><b>EVENTS</b></p>
<table>
<tbody>
<?php
    $query = "SELECT trm_ID, trm_Label, trm_Code from defTerms where trm_ParentTermID=3297 ORDER BY trm_Label";
    $res = $system->get_mysqli()->query($query);
    while($row = $res->fetch_assoc()) {
            $filename = HEURIST_TERM_ICON_URL.$row["trm_ID"].".png";
            if(file_exists(HEURIST_TERM_ICON_DIR.$row["trm_ID"].".png")){
print '<tr><td class="legend_icon"><img src="'.$filename.'"></td>';
print '<td class="legend_text">'.$row["trm_Label"].'</td></tr>';
                
            }
    }
?>
</tbody>
</table>
</div>

