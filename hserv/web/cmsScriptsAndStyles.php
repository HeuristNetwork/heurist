<?php
/**
* cmsScriptsAndStyles.php - minimal set of scripts and styles for Heurist CMS website
* It is included in website output by 
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     7.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
?>
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha384-wsqsSADZR1YRBEZ4/kKHNSmU+aX8ojbnKUMN4RyD3jDkxw5mHtoe2z/T/n4l56U/" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js" integrity="sha384-/L7+EN15GOciWSd0nb17+43i1HKOo5t8SFtgDKGqRJ2REbp8N6fwVumuBezFc4qC" crossorigin="anonymous"></script>
    <link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/detectHeurist.js"></script>
    
    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/utils.js"></script>
    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/utils_dbs.js"></script>
    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/utils_msg.js"></script>
    
    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/hapi.js"></script>
    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/HSystemMgr.js"></script>
    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/recordset.js"></script>

    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/widgets/recordList/recordList.js"></script>

    <script type="text/javascript" src="<?php echo HEURIST_BASE_URL;?>hclient/core/HLayoutMgr.js"></script>
    
    <style>.dropdown-hover-all .dropdown-menu, .dropdown-hover > .dropdown-menu.dropend { margin-left:-1px !important }</style>
     
    <script>
        window.addEventListener('DOMContentLoaded', event => {
            
            if(!window.hWin.HAPI4){
                window.hWin.HAPI4 = new hAPI(null, onHapiInit);
            }else if(!window.isHapiInited){
                // Not standalone, use HAPI from parent window
                onHapiInit( true );
            }
        });
        
        function onHapiInit(success)
        {
            window.isHapiInited = true;
            
            if(success) // Successfully initialized system
            {
                //init layout - init Heurist widgets on this page
                let $bs = bootstrap;
  
        const CLASS_NAME = 'has-child-dropdown-show';
        $bs.Dropdown.prototype.toggle = function(_orginal) {
            return function() {
                document.querySelectorAll('.' + CLASS_NAME).forEach(function(e) {
                    e.classList.remove(CLASS_NAME);
                });
                let dd = this._element.closest('.dropdown').parentNode.closest('.dropdown');
                for (; dd && dd !== document; dd = dd.parentNode.closest('.dropdown')) {
                    dd.classList.add(CLASS_NAME);
                }
                return _orginal.call(this);
            }
        }($bs.Dropdown.prototype.toggle);                
                
        document.querySelectorAll('.dropdown').forEach(function(dd) {
            dd.addEventListener('hide.bs.dropdown', function(e) {
                if (this.classList.contains(CLASS_NAME)) {
                    this.classList.remove(CLASS_NAME);
                    e.preventDefault();
                }
                e.stopPropagation(); // do not need pop in multi level mode
            });
        });                
  
                /*
        document.querySelectorAll('.dropdown-hover-all .dropdown').forEach(function(dd) { //.dropdown-hover,  .dropdown-toggle
        dd.addEventListener('mouseenter', function(e) {
            let toggle = e.target.querySelector(':scope>[data-bs-toggle="dropdown"]');
            if (!toggle?.classList.contains('show')) {
                $bs.Dropdown.getOrCreateInstance(toggle).toggle();
                dd.classList.add(CLASS_NAME);
                $bs.Dropdown.clearMenus(e);
            }
        });
        dd.addEventListener('mouseleave', function(e) {
            let toggle = e.target.querySelector(':scope>[data-bs-toggle="dropdown"]');
            if (toggle?.classList.contains('show')) {
                $bs.Dropdown.getOrCreateInstance(toggle).toggle();
            }
        });                
         });        
                */
                
                //init layout
                window.hWin.HAPI4.layoutMgr = new HLayoutMgr();
                
                window.hWin.HAPI4.layoutMgr.layoutInit( document.getElementsByTagName('main').innerText, 'main', {} );
                
            }
        }
    </script>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
