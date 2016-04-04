<?php
/*
    if ( extension_loaded('zlib') )
    {
            ob_start('ob_gzhandler');
    }    
*/    
//ini_set("zlib.output_compression", 4096); //default buffer size 4K
//ini_set("zlib.output_compression_level", 9); //default -1
/*
if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
    ob_start('ob_gzhandler');
}else{
    ob_start();
}
echo $_SERVER['HTTP_ACCEPT_ENCODING'].'<br>';   
echo phpinfo();
*/  
ob_start(); 
echo phpinfo(); 
$output=gzencode(ob_get_contents(),9); 
ob_end_clean(); 
header('Content-Encoding: gzip'); 
echo $output; 
unset($output);   
?>
