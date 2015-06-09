<?php
    /*
    require_once(dirname(__FILE__)."/../../php/System.php");

    $system = new System();
    $system->init(@$_REQUEST['db']);
    
    //event types
    $query = "SELECT trm_ID, trm_Label, trm_Code from defTerms where trm_Code>0 and trm_ParentTermID=3297";
    $res = $system->get_mysqli()->query($query);
    while($row = $res->fetch_assoc()) { // each loop is a complete table row
            $filename = "assets/SteelBlue4_".$row["trm_Code"].".png";
            if(file_exists($filename)){
                copy($filename, "assets/".$row["trm_ID"].".png" );
print $filename."<br>";                
            }
    }
    $query = "SELECT trm_ID, trm_Label, trm_Code from defTerms where trm_Code>0 and trm_ParentTermID=3306";
    $res = $system->get_mysqli()->query($query);
    while($row = $res->fetch_assoc()) { // each loop is a complete table row
            $filename = "assets/iv_SteelBlue4_".$row["trm_Code"].".png";
            if(file_exists($filename)){
                copy($filename, "assets/".$row["trm_ID"].".png" );
print $filename."<br>";                
            }
    }
      */
      
    header('Content-type: image/png');
    $filelocation = "assets/assault.png";
    readfile($filelocation);  
    
?>
