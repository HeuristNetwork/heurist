/**
*  tempalte to define new widget
*/
$.widget( "heurist.widgetname", {

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
  _setOptions: function( ) {
        this._superApply( arguments );
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
