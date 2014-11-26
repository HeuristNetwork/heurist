<?php
    /**
    * springDiagram.php: Renders multiple levels of search results 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Spring Diagram</title>

        <!-- Css -->
        <link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
        <style>
            body, html {
                background-color: #fff;
            }
        </style>
        
         <!-- jQuery -->
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        
        <!-- D3 -->
        <script type="text/javascript" src="../ext/d3/d3.js"></script>
        <script type="text/javascript" src="../ext/d3/fisheye.js"></script>
        
        <!-- Colpick -->
        <script type="text/javascript" src="../ext/colpick/colpick.js"></script>
        <link rel="stylesheet" type="text/css" href="../ext/colpick/colpick.css">
        
        <!-- Visualize plugin --> 
        <script type="text/javascript" src="visualize/visualize.js"></script>
        <link rel="stylesheet" type="text/css" href="visualize/visualize.css">                          
    </head>
    
    <body>
        <!-- Visualize HTML -->
        <?php include "visualize/visualize.html"; ?>
    </body>
    
</html>