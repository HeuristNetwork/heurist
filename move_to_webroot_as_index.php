<?php

    /**
    * move_to_webroot_as_index.php (optional)
    * use this to redirect the root of the website to the Heurist swichboard, if desired
\    * You can also do this with html redirect (see below) but it is potentially less reliable
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Ian Johnson   <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    header( 'Location: /HEURIST/index.html');

    
/*   This is the html alternative - not tested

<!DOCTYPE HTML>
<html lang="en-US">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="refresh" content="1; url=/HEURIST/index.html">
        <script type="text/javascript">
            window.location.href = "/HEURIST/index.html"
        </script>
        <title>Redirect to Heurist switchboard</title>
    </head>
    <body>
        If you are not redirected automatically, follow the <a href='/HEURIST/index.html'>link to example</a>
    </body>
</html>
*/
?>
