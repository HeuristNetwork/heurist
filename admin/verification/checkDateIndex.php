<?php
$is_included = (defined('PDIR'));
$cache_missed = false;
$is_upgrade = (@$_REQUEST['upgrade']==1);

if(!$is_included){

    define('PDIR','../../');  //need for proper path to js and css    
    define('MANAGER_REQUIRED', 1);   
    require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
}
?>    
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">
        <div class="banner">
            <h2><?php echo $is_upgrade?'Upgrading date format':'Check Record Details Date Index';?></h2>
        </div>
        <div id="page-inner" style="min-height:200px">
<?php    

    $is_ok = true;
    if(@$_REQUEST['fixdateindex']=='1'){
        
        $need_convert_dates = (@$_REQUEST['convert_dates']=='1');
        $rep = recreateRecDetailsDateIndex($system, true, $need_convert_dates);
        if($rep){
            foreach($rep as $msg){
                print $msg.'<br>';
            }
            
            if($is_upgrade){
?>                
                <div><h3 class="res-valid">Record Details Date Index has been successfully created</h3></div>
                <div><a href="<?php echo HEURIST_BASE_URL.'?db='.HEURIST_DBNAME;?>">Open Heurist</a></div>
                </div></body></html>
<?php
                exit();
            }else{
                print '<div><h3 class="res-valid">Record Details Date Index has been successfully recreated</h3></div>';    
            }
        }else{
            $response = $system->getError();    
            print '<div><h3 class="error">'.$response['message'].'</h3></div>';
            $is_ok = false;
        }
    }

    if($is_ok){
        $mysqli = $system->get_mysqli();
        
        $index_outdated = false;

        //count of date fields
        $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value!=""';
        $cnt_dates = mysql__select_value($mysqli, $query);

        if($is_upgrade){
            
            $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value LIKE "|VER=1%"';
            $cnt_fuzzy_dates = mysql__select_value($mysqli, $query);
            
?>            
            <p style="width:700px;font-size:1.1em">
We are upgrading the storage of fuzzy dates in this database from a format we developed more than 15 years ago to a newer, standards-based format with improved flexibility and usability. This should not take very long, will not touch simple dates (year, year-month or year-month-day) and should have no impact on your fuzzy dates other than improving their utility. 
            </p>
            <p style="width:700px;font-size:1.1em">
Please advise the Heurist team if you think you have a problem after the upgrade.
            </p>
<?php   
        }else{
            $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value LIKE "%estMinDate%"';
            $cnt_fuzzy_dates = mysql__select_value($mysqli, $query);
        }


        print '<div style="padding:5px">Total count of date fields:&nbsp;<b>'.$cnt_dates.'</b></div>';
        print '<div style="padding:5px">Fuzzy/complex dates:&nbsp;<b>'.$cnt_fuzzy_dates.'</b></div>';
    
        if($is_upgrade){
?>            
            <div><br><br>
                    <button onclick="{var ele = document.getElementById('page-inner'); ele.innerHTML=''; ele.classList.add('loading'); window.open('<?php echo HEURIST_BASE_URL;?>admin/verification/checkDateIndex.php?db=<?php echo HEURIST_DBNAME;?>&upgrade=1&fixdateindex=1&convert_dates=1','_self')}">Upgrade database</button>
            </div>        
<?php            
        }else{       
    
        $is_table_exist = hasTable($mysqli, 'recDetailsDateIndex');    
        
        if($is_table_exist){
    
            //count of index 
            $query = 'SELECT count(rdi_DetailID) FROM recDetailsDateIndex';
            $cnt_index = mysql__select_value($mysqli, $query);

            $query = 'SELECT count(rdi_DetailID) FROM recDetailsDateIndex WHERE rdi_estMinDate=0 AND rdi_estMaxDate=0';
            $cnt_empty = mysql__select_value($mysqli, $query);

            if($cnt_dates==$cnt_index && $cnt_empty==0){
                print '<div style="padding:5px">'
                    .'&nbsp;&nbsp;&nbsp;&nbsp;All date entries are in index. Index values are OK</div>';
            }
            
            if($cnt_dates > $cnt_index || $cnt_empty>0){
                if($cnt_dates > $cnt_index){
                    print '<div style="padding:5px;color:red">Missed entries in index:&nbsp;<b>'.($cnt_dates - $cnt_index).'</b></div>';
                }
                if($cnt_empty > 0){
                    print '<div style="padding:5px;color:red">Empty dates in index:&nbsp;<b>'.($cnt_empty).'</b></div>';
                }

                echo '<div><h3 class="error">Recreate Details Date Index table to restore missing entries</h3></div>';        
                
                $index_outdated = true;
            }else{
                echo '<div><h3 class="res-valid">Record Details Date Index is valid</h3></div>';        
            }

        }else{
                echo '<div><h3 class="res-valid">Record Details Date Index table does not exist</h3></div>';        
        }
        
?>            
            <div><br><br>
                    <button onclick="{window.open('listDatabaseErrors.php?db=<?php echo HEURIST_DBNAME;?>&fixdateindex=1&convert_dates=0','_self')}">Recreate date index</button>
            </div>        
<?php    
        }            
        
    }//isok            
    
if(!$is_included){    
    print '</div></body></html>';
}else{
    
    if($index_outdated || !$is_table_exist){ //highlight in header of verification
        echo '<script>$(".dateindex").css("background-color", "#E60000");</script>';
    }else{
        echo '<script>$(".dateindex").css("background-color", "#6AA84F");</script>';        
    }
    print '<br /></div>';
}
?>
