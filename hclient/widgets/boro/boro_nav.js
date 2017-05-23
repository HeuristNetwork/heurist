/**
*
* boro_nav.js (Beyond1914)
* 1. Search for Web Content records
* 2. Construct navigation menu (container specified by)
* 3. Event handler for navigation menu 
*       a) loads web content
*       b) switch to search widgets
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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


$.widget( "heurist.boro_nav", {

    // default options
    options: {
       //WebContent:25, // rectype ID for information pages content
       menu_div:'',   // id of navigation menu div
    },
    
    data_content:{},

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        // prevent double click to select text
        .disableSelection();
        
        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if($('#'+this.options.menu_div).length==1){
            var that = this;
            //seach for web content records
            var details = [window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                window.hWin.HAPI4.sysinfo['dbconst']['DT_ORDER'], //order
                window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']];

            var request = request = {q: 't:'+window.hWin.HAPI4.sysinfo['dbconst']['RT_WEB_CONTENT']
                     +' sortby:f:'+window.hWin.HAPI4.sysinfo['dbconst']['DT_ORDER'],
                     w: 'all', detail:details };

            window.hWin.HAPI4.SearchMgr.doSearchWithCallback( request, function( recordset )
                {
                    if(recordset!=null){
                        that._constructNavigationMenu( recordset );
                    }
            });

        }
        
    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        //this.select_rectype.remove();
    },
    
    _constructNavigationMenu: function( recordset ){
    
        var that = this;
        var menu_ele = $('#'+this.options.menu_div);
        
        menu_ele.empty();    

        var recs = recordset.getRecords();
        var rec_order = recordset.getOrder();
        var idx=0, recID;
        
        var html = '<ul class="nav navbar-nav">';

        for(; idx<rec_order.length; idx++) {
            recID = rec_order[idx];
            if(recID && recs[recID]&& recordset.fld(recs[recID], window.hWin.HAPI4.sysinfo['dbconst']['DT_ORDER'])>0){
                    //var recdiv = this._renderRecord(recs[recID]);
                    html  += 
                        ('<li><a href="#" class="nav-link" data-id="'+recID+'">'
                        +recordset.fld(recs[recID], window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'])
                        +'</a></li>');
                    
                    //that.data_content[recID]
                    var content = recordset.fld(recs[recID], 
                                    window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']);
                     
                    var ele = that._addClearPageDiv(recID);                

                    ele.html( content );      
            }
        }//for
        //add two special items - People and Search
        html  = html 
            +'<li><a href="#" class="nav-link" data-id="people">People</a></li>'
            +'<li><a href="#" class="nav-link" data-id="search">Search</a></li></ul>';
        
        menu_ele.html(html);
        
        /*
        $('.bor-dismiss-veil').click(function(event){
            event.preventDefault(); 
            $('.bor-veil').toggle("slide",{direction:"right"});
        });
        */
         
        menu_ele.find('.nav-link').click(function(event){
            var recID = $(event.target).attr('data-id');
            window.hWin.HEURIST4.util.stopEvent(event);
            
            //hide all 
            $('#main_pane').find('.clearfix').hide();
            //show selected 
            $('.bor-page-'+recID).show();
        });
        
        var placeID = window.hWin.HEURIST4.util.getUrlParameter('placeid');
        var profileID = window.hWin.HEURIST4.util.getUrlParameter('profileid');
        
        if(placeID>0){

            
            var app1 = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('boro_place');
            if(app1 && app1.widget){
                $('#main_pane').find('.clearfix').hide();
                $('.bor-page-place').show();
                $(app1.widget).boro_place('option', 'placeID', placeID);
            }

            //this.recordResolver( 'place', placeID );
            
        }else if(profileID>0){
            this.recordResolver( 'profile', profileID );
        }else{
            $(menu_ele.find('.nav-link')[0]).click();
        }
    },

    //
    // add/clear page div on main_pane
    //
    _addClearPageDiv: function( recID ){
            var ele = $('.bor-page-'+recID); 
            
            if(ele.length==1){ 
                
               ele.empty(); 
               return ele;
            }else{
                return $('<div>').addClass('clearfix bor-page-'+recID).appendTo($('#main_pane'));
            }                                    
    },
    
    //
    // executes smarty template for record
    //    
    recordResolver: function( sType, recID ){
    
            var ele = this._addClearPageDiv( sType );
        
            //hide all 
            $('#main_pane').find('.clearfix').hide();
            //show selected 
            ele.show();
            //load and execute smarty template for record
            
            var sURL = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?h4=1&w=a&db='+window.hWin.HAPI4.database
                            +'&q=ids:'+recID+'&template=BoroProfileOrPlace.tpl';
            
            ele.load(sURL);
    }
    
});


function boroResolver(event){

    var url = $(event.target).attr('href');
    var params = url.split('/');
    if(params.length>2){
        var recID = params[params.length-2];
        var type = params[params.length-3];
        
        window.hWin.HEURIST4.util.stopEvent(event);
        
       if(recID>0){
            var app1 = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('boro_'+type);
            if(app1 && app1.widget){
                var hdoc = $(window.hWin.document);
                hdoc.find('#main_pane > .clearfix').hide();
                hdoc.find('.bor-page-'+type).show();
                hdoc.scrollTop(0);
                if(type=='place'){
                    $(app1.widget).boro_place('option', type+'ID', recID);    
                }else{
                    $(app1.widget).boro_profile('option', type+'ID', recID);    
                }
            }
       }
    }   
}

