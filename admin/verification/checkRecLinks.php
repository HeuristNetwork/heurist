<?php
$is_included = (defined('PDIR'));

if($is_included){

    print '<div style="padding:10px"><h3 id="relationship_cache_msg">Check Relationship cache</h3><br>';
    
}else{
    require_once (dirname(__FILE__).'/../System.php');
    
    $cache_missed = false;
    
    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        print $system->getError();
        return;
    }
    if(!$system->is_admin()){ //  $system->is_dbowner()
        print '<span>You must be logged in as Database Administrator to perform this operation</span>';
    }
    
    
?>    
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">
        <div class="banner">
            <h2>Check Relationship cache</h2>
        </div>
        <div id="page-inner">
<?php    
}
    $is_ok = true;
    if(@$_REQUEST['fixcache']=='1'){
        
        if(recreateRecLinks($system, true)){
            print '<div><h3 class="res-valid">Relationship cache has been successfully recreated</h3></div>';
        }else{
            $response = $system->getError();    
            print '<div><h3 class="error">'.$response['message'].'</h3></div>';
            $is_ok = false;
        }
    }

    if($is_ok){
        $mysqli = $system->get_mysqli();
        
        if(!defined('RT_RELATION')) $system->defineConstant('RT_RELATION');

        //count of relations 
        $query = 'SELECT count(rec_ID) FROM Records '
                .'where rec_RecTypeID='.RT_RELATION
                .' and rec_FlagTemporary=0';
        $cnt_relationships = mysql__select_value($mysqli, $query);

        //count of missed relations in recLinks                    
        $query = 'SELECT count(rec_ID) FROM Records left join recLinks on rec_ID=rl_RelationID '
                .'where rec_RecTypeID='.RT_RELATION
                .' and rec_FlagTemporary=0 and rl_RelationID is null';
        $missed_relationships = mysql__select_value($mysqli, $query);
        

        //count of links
        $query = 'SELECT count(dtl_ID) FROM Records, recDetails, defDetailTypes '
                .' where rec_ID=dtl_RecID and dtl_DetailTypeID=dty_ID and dty_Type="resource"'
                .' and rec_FlagTemporary=0';
        $cnt_links = mysql__select_value($mysqli, $query);

        //count of missed links
        $query = 'SELECT count(dtl_ID) FROM Records, defDetailTypes, recDetails left join recLinks on dtl_ID=rl_DetailID'
                .' where rec_RecTypeID!='.RT_RELATION
                .' and rec_ID=dtl_RecID and dtl_DetailTypeID=dty_ID and dty_Type="resource"'
                .' and rec_FlagTemporary=0 and rl_DetailID is null';
        $missed_links = mysql__select_value($mysqli, $query);
            
            
    print '<div style="padding:5px">Total count of relationships:&nbsp;<b>'.$cnt_relationships.'</b>'
            .($missed_relationships>0?'':'&nbsp;&nbsp;&nbsp;&nbsp;All relationships are in cache. Cache is OK.').'</div>';
    
    if($missed_relationships>0){
        print '<div style="padding:5px;color:red">Missed relationships in cache:&nbsp;<b>'.$missed_relationships.'</b></div>';
    }
    
    print '<br><div style="padding:5px">Total count of links/resources:&nbsp;<b>'.$cnt_links.'</b>'
            .($missed_links>0?'':'&nbsp;&nbsp;&nbsp;&nbsp;All links are in cache. Cache is OK.').'</div>';

    if($missed_links>0){
        print '<div style="padding:5px;color:red">Missed links in cache:&nbsp;<b>'.$missed_links.'</b></div>';
    }

        $cache_missed = ($missed_relationships>0 || $missed_links>0);

        if($cache_missed){
            echo '<div><h3 class="error">Recreate Relationship cache to restore missed entries</h3></div>';        
        }else{
            echo '<div><h3 class="res-valid">OK: Relationship cache is valid</h3></div>';        
        }
    
?>        
        <div><br><br>
                <button onclick="{window.open('listDatabaseErrors.php?db=<?php echo HEURIST_DBNAME;?>&fixcache=1','_self')}">Recreate cache</button>
        </div>        
<?php                
    }
    

    
if(!$is_included){    
    print '</div></body></html>';
}else{
    
    if($cache_missed){
        echo '<script>$(".relationship_cache").css("background-color", "#E60000");</script>';
    }else{
        echo '<script>$(".relationship_cache").css("background-color", "#6AA84F");</script>';        
    }
    print '<br /></div>';
}
?>
