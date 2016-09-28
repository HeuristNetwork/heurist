<?php
  print 'Hello! This is Heurist page. More content will be soon!'
?>      
<html>                                                        
<body>
<div id="status_demo">
BBB
</div>
<script>
function findTopMostHeurist() {
    if (window.top != window.self) {
        document.getElementById("status_demo").innerHTML = "This window is NOT the topmost window!";
    } else { 
        document.getElementById("status_demo").innerHTML = "This window is the topmost window!";
    } 
}
(function() {
   // your page initialization code here
   // the DOM will be available here
   findTopMostHeurist();
})();
</script>

</body>
</html>
