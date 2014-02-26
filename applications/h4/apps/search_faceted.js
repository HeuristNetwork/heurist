/**
*  Wizard to define new faceted search
*  Steps:
*  1. Select rectypes (use rectype_manager)
*  2. Select fields (recTitle, numeric, date, terms, pointers, relationships)
*  3. Options
*  4. Save into database
*/
$.widget( "heurist.search_faceted", {

  // default options
  options: {
    // callbacks
  },

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
  _setOptions: function( options ) {
  },
  
  /* 
  * private function 
  * show/hide buttons depends on current login status
  */
  _refresh: function(){

  },
  // 
  // custom, widget-specific, cleanup.
  _destroy: function() {
    // remove generated elements
    //this.select_rectype.remove();
  }

});

function showManageRecordTypes(){
    
       var manage_dlg = $('#heurist-rectype-dialog');

       if(manage_dlg.length<1){

            manage_dlg = $('<div id="heurist-rectype-dialog">')
                    .appendTo( $('body') )
                    .rectype_manager({ isdialog:true });
       }

       manage_dlg.rectype_manager( "show" );
}
