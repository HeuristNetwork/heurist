<?php
    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    include('../records/index/elasticSearchFunctions.php');
    $status = testElasticSearchOK();
    echo "Status: $status";
?>
