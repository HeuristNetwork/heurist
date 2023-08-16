<?php
    //Deprecated. Not used. To be removed.
    
    //to avoid "Open Redirect" security warning    
    parse_str($_SERVER['QUERY_STRING'], $vars);
    $query_string = http_build_query($vars);
    
    header( 'Location:../../hsapi/controller/file_download.php?'.$query_string );
    return;
?>
