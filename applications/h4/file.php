<?php
/*
    shorten part to access files - redirects to php/common/file_download.php
*/
 header( 'Location: php/common/file_download.php?'.$_SERVER['QUERY_STRING'] );
?>
