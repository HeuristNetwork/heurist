<?php

    /**
    * move_to_webroot_as_index.html (optional)
    * use this to redirect the root of the website to the Heurist simlink if desired
    * you can also do this with html redirect but it is more complex to do properly and less reliable
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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

    header( 'Location: /heurist');
    // This must redirect to the heurist (sic) simlink NOT to the HEURIST base path, otherwise path to css will be incorrect

?>
