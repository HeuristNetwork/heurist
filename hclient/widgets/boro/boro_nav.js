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
       entityType:'',
       entityID:null
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
        
        var wasChanged = (arguments['entityType']!=this.options.entityType ||
                          arguments['entityID']!=this.options.entityID);
        this._superApply( arguments );
        
        if(wasChanged){
            this.resolveEntity();
        }
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
        
        
        //load initial page based on url parameters
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
            //this.recordResolver( 'profile', profileID );
            this._setOptions({entityType:'profile', entityID:profileID});
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
    // executes smarty template for record   - not used
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
    },
     
    //
    //
    // 
    resolveEntity: function(){
        
        var entityType = this.options.entityType;
        var entityID = this.options.entityID;
        if(!window.hWin.HEURIST4.util.isempty(entityType) && entityID>0){
            //switch to page
            
            var hdoc = $(window.hWin.document);
            hdoc.find('#main_pane > .clearfix').hide();
            hdoc.find('.bor-page-'+entityType).show();
            hdoc.scrollTop(0);
            if(entityType=='profile'){
                this.fillProfilPage();    
            }
            
        } 
    },
    
    
    //
    //
    //
    fillProfilPage: function(){

        var recID = this.options.entityID;

        //search for record and all related records
        //that.loadanimation(true);
        var request = {
            w: 'a',
            q:'ids:'+recID,
            rules:[{"query":"t:31,27 linkedfrom:10","levels":[{"query":"t:26 linkedfrom:31,27","levels":[{"query":"t:25 linkedfrom:26"}]}]},  //education
                {"query":"t:24,28,29,33,37 linkedfrom:10","levels":[{"query":"t:25 linkedfrom:-78"}]},  //events
                {"query":"t:5 linkedfrom:10-61,135,144"}],  //documents
            detail: 'detail', 
            //id: current_page,
            source:this.element.attr('id') };
            
        var that = this;
        window.hWin.HAPI4.RecordMgr.search(request, function(response){

            //that.loadanimation(false);

            if(response.status != window.hWin.HAPI4.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }
            
            var DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],     //1
                DT_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATE'],     //9
                //DT_YEAR = window.hWin.HAPI4.sysinfo['dbconst']['DT_YEAR'],     //73
                DT_START_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_START_DATE'], //10
                DT_END_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_END_DATE'], //11
                DT_PLACE = 78;
                
            
            var recordset = new hRecordSet(response.data);
            
            function __getDate(rec){
                var date = recordset.fld(rec, DT_DATE);
                if(!date){
                    date = recordset.fld(rec, DT_START_DATE);
                }
                if(window.hWin.HEURIST4.util.isempty(date)) date = '';
                return date;
            }
            function __getPlace(rec){
                var placeID = recordset.fld(rec, DT_PLACE);
                if(placeID>0){
                    var place_rec = recordset.getById(placeID);
               
                    return ' in <a href="'+window.hWin.HAPI4.baseURL+'place/'+placeID+'/a" onclick="{window.hWin.boroResolver(event);}">'
                        + recordset.fld(place_rec, 'rec_Title') + '</a>';
                }else{
                    return '';
                }
            }
            
            //-----------------            
            var person = recordset.getById(recID);
            var fullName = composePersonName(recordset, person);
   
            //IMAGE AND NAME
            var html_thumb = '<a class="bor-emblem-portrait" href="#">';
            if( recordset.fld(person, 'rec_ThumbnailURL') ){
                html_thumb = html_thumb
                            //<div class="img-circle ab-light" data-ab-yaq="223" style="background-color: rgb(223, 223, 223);">
                            +'<div class="img-circle ab-dark" data-ab-yaq="59" style="background-color: rgb(59, 59, 59);">'
                                +'<img class="bor-emblem-portrait-image" src="' + recordset.fld(person, 'rec_ThumbnailURL')
                                    +'" alt="Photograph of '+fullName+'" data-adaptive-background="1" data-ab-color="rgb(59,59,59)">'
                            +'</div>';
            }else{
                html_thumb = html_thumb 
                        +'<div class="bor-emblem-portrait-image placeholder"></div>';
            }
            html_thumb = html_thumb +'<div class="bor-emblem-portrait-name">'+fullName+'</div></a>';
            
            $('.bor-emblem').empty().append($(html_thumb));
            
            //LEFT SIDE
            //Lifetime
            var html = '';
            var birthID = recordset.fld(person, 103);
            if(birthID>0){
                var birth_rec = recordset.getById(birthID);
                var sDate = __getDate(birth_rec);
                var sPlace = __getPlace(birth_rec);
                html = '<li>Born '+sDate+sPlace+'</li>';                            
            }
            var deathID = recordset.fld(person, 95);
            if(deathID>0){
                
                var death_rec = recordset.getById(deathID);
                var sDate = __getDate(death_rec);
                var sPlace = __getPlace(death_rec);
                
                var sDeathType = window.hWin.HEURIST4.ui.getTermValue('enum', recordset.fld(death_rec, 143) );
                if(sDeathType!='Killed in action'){
                    sDeathType = 'Died';
                }
                 
                html = html + '<li>'+sDeathType+' '+sDate+sPlace+'</li>';                            
            }
            $('#p_lifetime').empty().append($(html));
            
            //Gender
            $('#p_gender').empty().append(
                //@todo facet link
                $('<a href="#">'+window.hWin.HEURIST4.ui.getTermValue('enum',recordset.fld(person, 20))+'</a>'));

            //Early education
            var idx = 0, early = recordset.values(person, 83);
            html = '';
            if($.isArray(early)){
                for (idx in early){
                    var early_id = early[idx];
                    var early_rec = recordset.getById(early_id);
                    var sDate = __getDate(early_rec);
                    var sEduName = recordset.fld(recordset.getById( recordset.fld(early_rec, 97) ), DT_NAME);
                    //@todo facet link
                    html = html + '<li><a href="#">'+sEduName+'</a>'
                                +'<span class="bor-group-date">'+sDate+'</span></li>';
                }
            }
            $('#p_education').empty().append($(html));

            
        });

    } 
     
     
});


function composePersonName(recordset, record){
          
            function fld(fldname){
                return recordset.fld(record, fldname);
            }
            var DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], //1
                DT_GIVEN_NAMES = window.hWin.HAPI4.sysinfo['dbconst']['DT_GIVEN_NAMES'],
                DT_INITIALS = 72,
                DT_HONOR = 19;

            var fullName = fld(DT_GIVEN_NAMES);    
            if(window.hWin.HEURIST4.util.isempty(fullName)){
                fullName = fld(DT_INITIALS);        
            }
            if(!window.hWin.HEURIST4.util.isempty(fld(DT_HONOR))){
                fullName = window.hWin.HEURIST4.ui.getTermValue('enum', fld(DT_HONOR))+' '+fullName;
            }
            fullName = fullName + ' ' + fld(DT_NAME);
            return fullName;      
}
        

//
// global function
//
function boroResolver(event){

    var url = $(event.target).attr('href');
    var params = url.split('/');
    if(params.length>2){
        var recID = params[params.length-2];
        var type = params[params.length-3];
        
        window.hWin.HEURIST4.util.stopEvent(event);
        
       if(recID>0){
           
           if(type=='place'){
                //@todo?? move all functionality of boro_place to boro_nav??? 
                var app1 = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('boro_place');
                if(app1 && app1.widget){
                    var hdoc = $(window.hWin.document);
                    hdoc.find('#main_pane > .clearfix').hide();
                    hdoc.find('.bor-page-place').show();
                    hdoc.scrollTop(0);
                    $(app1.widget).boro_place('option', 'placeID', recID);    
                }
           }else{
                var app1 = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('boro_nav');
                if(app1 && app1.widget){
                    $(app1.widget).boro_nav({entityType:type, entityID:recID});    
                }               
           }
       }
    }   
}

