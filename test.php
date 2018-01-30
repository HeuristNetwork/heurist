<?php
define('IS_INDEX_PAGE',true);
define('PDIR','');

require_once(dirname(__FILE__)."/hclient/framecontent/initPage.php");
?>
        <!-- os, browser detector -->
        <script type="text/javascript" src="ext/js/platform.js"></script>

        <script type="text/javascript">

           function onPageInit(success){
                if(!success) return;
                

           window.hWin.HEURIST4.ui.createTermSelectExt2($('#people').get(0),{termIDTree:513});
                
                        
           }
        </script>

        <style>
        
    /* select with CSS avatar icons */
    option.avatar {
      background-repeat: no-repeat !important;
      padding-left: 20px;
    }
    .avatar .ui-icon {
      background-position: left top;
    }
            
        </style>
        
    </head>
    <body style="background-color:#c9c9c9">
    
        <div>
        
        
   <label for="people">Select a Person:</label>
    <select name="people" id="people">
      <option value="1" data-class="avatar" data-style="background-image: url(&apos;http://www.gravatar.com/avatar/b3e04a46e85ad3e165d66f5d927eb609?d=monsterid&amp;r=g&amp;s=16&apos;);">John Resig</option>
      <option value="2" data-class="avatar" data-style="background-image: url(&apos;http://www.gravatar.com/avatar/e42b1e5c7cfd2be0933e696e292a4d5f?d=monsterid&amp;r=g&amp;s=16&apos;);">Tauren Mills</option>
      <option value="3" data-class="avatar" data-style="background-image: url(&apos;http://www.gravatar.com/avatar/bdeaec11dd663f26fa58ced0eb7facc8?d=monsterid&amp;r=g&amp;s=16&apos;);">Jane Doe</option>
    </select>        
        
        </div>    
    

        <div id="heurist-dialog">
        </div>
        
    </body>
</html>
