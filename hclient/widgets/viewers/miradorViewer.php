<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Lang" content="en">
<meta name="author" content="">
<meta name="description" content="">
<meta name="keywords" content="">
<title>Heurist Mirador Viewer</title>
<script src="https://unpkg.com/mirador@latest/dist/mirador.min.js"></script>
<!-- By default uses Roboto font. Be sure to load this or change the font -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500">
</head>
<body>
<?php

        $https = (isset($_SERVER['HTTPS']) &&
                    ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
                    isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                    $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
        
        $url = ($https ? 'https://' : 'http://').
            (!empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'].'@' : '').
            (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($_SERVER['SERVER_NAME'].
            ($https && $_SERVER['SERVER_PORT'] === 443 ||
            $_SERVER['SERVER_PORT'] === 80 ? '' : ':'.$_SERVER['SERVER_PORT'])));

        $url = $url . '/' .  $_SERVER['REQUEST_URI'];  
        
        if(@$_REQUEST['manifest'] || @$_REQUEST['iiif']){
            //load manifest directly
            $url = str_replace('hclient/widgets/viewers/miradorViewer.php','', $url);
            $url = str_replace($_SERVER['QUERY_STRING'],
                'db='.$_REQUEST['db']
                    .'&file='.($_REQUEST['manifest']?@$_REQUEST['manifest']:@$_REQUEST['iiif']),$url);
        }else{
            if(@$_REQUEST['iiif_image']){ //@todo
                //find record linked to this media 
                $rec_ID = 0;
                $url = $url.'&q=ids:'.$rec_ID;
            }
            //create manifest dynamically
            $url = str_replace('hclient/widgets/viewers/miradorViewer.php','hsapi/controller/record_output.php', $url);
            
        }
        
        if(strpos('format=iiif',$url)===false){
                $url = $url.'&format=iiif';    
        }
    
    //$_SERVER['QUERY_STRING'];
        $manifest_url = $url;
?>
<!-- Container element of Mirador whose id should be passed to the instantiating call as "id" -->
<div id="my-mirador"/> 

<script type="text/javascript">
var mirador = Mirador.viewer({
  "id": "my-mirador",
  "windows": [
    {
      "loadedManifest": "<?php echo $manifest_url;?>",
      "thumbnailNavigationPosition": 'far-bottom'
    }
  ]
});
</script>  
  
</body>
</html>