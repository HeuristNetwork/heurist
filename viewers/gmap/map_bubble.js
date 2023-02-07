
/**
* Bubble popup. This widget is used in mapping to show popup on timeline
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.bubble", $.ui.tooltip, {

    _create: function() {
        this._on({
            click: "open",
        });

        // IDs of generated tooltips, needed for destroy
        this.tooltips = {};
        // IDs of parent tooltips where we removed the title attribute
        this.parents = {};
        
        
        this.options.position = {
        my: "center bottom",
        at: "center top",
        /*using: function( position, feedback ) {
          $( this ).css( position );
          $( "<div>" )
            .addClass( "arrow" )
            .addClass( feedback.vertical )
            .addClass( feedback.horizontal )
            .appendTo( this );
        }*/
        };

        this.liveRegion = $( "<div>" )
            .attr( {
                role: "log",
                "aria-live": "assertive",
                "aria-relevant": "additions"
            } )
            .appendTo( this.document[ 0 ].body );
        this._addClass( this.liveRegion, null, "ui-helper-hidden-accessible" );        

        if ( this.options.disabled ) {
            this._disable();
        }
    },    
    
    closeAll: function(){
      
        var that = this;
        
        $.each( this.tooltips, function( id, tooltipData ) {
            var event = $.Event( "blur" );
            event.target = event.currentTarget = tooltipData.element[ 0 ];
            that.close( event, true );
        } );        
        
    },
    
    _open: function( event, target, content ) {

        if(!event || content=="") return; //==undefined   

        if(content.indexOf('http')==0){
            var that = this;
            $.get(content, function(responseTxt, statusTxt, xhr){
                if(statusTxt == "success"){
                    that._open( event, target, responseTxt )
                }
            });
            return;
        }

        
     var w = 420, h = 152;   
     
     //copy from google map    
content =     
'<div style="cursor: default; width: '+w+'px; height: '+h+'px;>'  //position: absolute; left: 428px; top: 249px; z-index: 249;"
    +'<div style="position: absolute; left: 0px; top: 0px;">'
/*    triangle arrow in bottom
        //arrow border
        +'<div class="tooltip-arrow-border" style="width: 0px; height: 0px; border-right: 10px solid transparent; border-left: 10px solid transparent; border-top: 24px solid rgba(0, 0, 0, 0.1); position: absolute; left: '+(w/2-10)+'px; top: '+h+'px;"></div>'
        //arrow shadow
        +'<div style="position: absolute; left: 0px; top: 0px; background-color: rgba(0, 0, 0, 0.2); border-radius: 2px; box-shadow: 0px 1px 4px -1px rgba(0, 0, 0, 0.3); width: 100%px; height: '+h+'px;"></div>'
        //arrow
        +'<div class="tooltip-arrow" style="border-top-width: 24px; position: absolute; left: '+(w/2-10)+'px; top: '+(h-3)+'px;">'
            +'<div style="position: absolute; overflow: hidden; left: -6px; top: -1px; width: 16px; height: 30px;">'
                +'<div style="position: absolute; left: 6px; background-color: rgb(255, 255, 255); transform: skewX(22.6deg); transform-origin: 0px 0px 0px; height: 24px; width: 10px; box-shadow: 0px 1px 6px rgba(0, 0, 0, 0.6);"></div>'
            +'</div>'
            +'<div style="position: absolute; overflow: hidden; top: -1px; left: 10px; width: 16px; height: 30px;">'
                +'<div style="position: absolute; left: 0px; background-color: rgb(255, 255, 255); transform: skewX(-22.6deg); transform-origin: 10px 0px 0px; height: 24px; width: 10px; box-shadow: 0px 1px 6px rgba(0, 0, 0, 0.6);"></div>'
            +'</div>'
        +'</div>'
*/        
        //borders
        +'<div style="position: absolute; left: 1px; top: 1px; border-radius: 2px; background-color: rgb(255, 255, 255); width: 100%; height: '+(h-2)+'px;"></div>'
    +'</div>'
    
    +'<div style="top: 9px; right:4px; padding-right:20px; position: absolute; left: 15px; bottom:2px; font-size:0.8em" class="gm-style-iw">'
        //+'<div style="overflow: auto; min-height: '+(h-14)+'px; width:'+(w-30)+'px">' //'max-width: '+(w-30)+'px;">'   display: inline-block; 
            +'<div style="height:100%;overflow-y: auto;overflow-x: hidden;">'
                +content  //<div>!!!</div><div class="infotitle">Fisher lib</div><div class="infodescription"></div>
            +'</div>'
        //+'</div>'
    +'</div>'
    /* CLOSER */
    +'<div style="width: 13px; height: 13px; overflow: hidden; position: absolute; opacity: 0.7; right: 0px; top: 0px; z-index: 10000; cursor: pointer;" class="bubbleCloser">'
        +'<img style="position: absolute; left: -2px; top: -336px; width: 59px; height: 492px; -moz-user-select: none; border: 0px none; padding: 0px; margin: 0px;"'
            +'src="https://maps.gstatic.com/mapfiles/api-3/images/mapcnt3.png" draggable="false">'
    +'</div>'
    
+'</div>';
    
        this._super( event, target, content );
        
        var tooltip = this._find( target );
        if ( tooltip.length>0 ) {
            
            tooltip.css({'box-shadow':'none', 'border':'none', 'background':'none !important', 'padding':0, 'max-width':'400px'});
            
            var posx = tooltip.position().left;
            var posy = tooltip.position().top;
         
            var docw = $(window).width();
            var a = tooltip.find('.tooltip-arrow');
            var ab = tooltip.find('.tooltip-arrow-border');
            if(posx<5){
                tooltip.css('left',5);
                /*var pos_ax = a.position().left;
                a.css('left', pos_ax + 5 + posx);
                ab.css('left', pos_ax + 5 + posx);*/
            }else if(posx+w > docw){
                var posxnew = docw - w - 5;
                tooltip.css('left', posxnew);
                /*var pos_ax = a.position().left;
                a.css('left', pos_ax + (posx - posxnew));
                ab.css('left', pos_ax + (posx - posxnew));*/
            }
        
            var posynew = posy-150;
            if(posynew<0) posynew = 0;
            //tooltip.css('top', posynew);
            /*var pos_ay = a.position().top;
            a.css('top', pos_ay + (posy - posynew));
            ab.css('top', pos_ay + (posy - posynew));*/
        
            //$(".bubbleCloser").click()
            this._on( $(".bubbleCloser"), { click:function(event){ event.currentTarget=target; this.close(event); }   }); 
            //{ click:function(event){  $( "#btnTest" ).bubble( "close" ) } });
            //{ click:function(event){ this._trigger( "close", event, { tooltip: tooltip } ); }   }); 
            //{click:function(){ $( document ).bubble('close') } }); //.close(event);
        }
    }    
    
});
