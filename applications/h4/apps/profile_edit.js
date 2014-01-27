/**
*  Dialog to edit profile(user) or register new user
*/
$.widget( "heurist.profile_edit", {

  // default options
  options: {
    isdialog: true,  
    
    usr_ID:null  
      
    // callbacks
  },

   // the widget's constructor
  _create: function() {

    var that = this;

    // prevent double click to select text
    this.element.disableSelection();
      
    var $dlg = this.edit_form = $( "<div>" )
        .appendTo( this.element );
        
    $dlg.load("apps/profile_edit.html", 
    function(){
            
            //find all labels and apply localization
            $dlg.find('label').each(function(){
                 $(this).html(top.HR($(this).html()));
            });
            var allFields = $dlg.find('input');
            
            if(this.options.isdialog){
                   $dlg.dialog({
                      autoOpen: false,
                      height: 480,
                      width: 640,
                      modal: true,
                      resizable: true,
                      title: '',
                      buttons: [
                        {text:top.HR('Save'), click: that._doSave},
                        {text:top.HR('Cancel'), click: function() {
                          $( this ).dialog( "close" );
                        }}
                      ],
                      close: function() {
                        allFields.val( "" ).removeClass( "ui-state-error" );
                      }
                   });
            }
            
    });
      
    // Sets up element to apply the ui-state-focus class on focus.
    //this._focusable($element);   
      
    this._refresh();

  }, //end _create

  // Any time the widget is called with no arguments or with only an option hash, 
  // the widget is initialized; this includes when the widget is created.
  _init: function() {
      
        this._fromDataToUI();
                
        if(this.options.isdialog){
            
            this.edit_form.dialog('option', 'title', top.HR('Edit profile'));
            
            this.edit_form.dialog("open");
        }else{
        }
  },
  
  _setOption: function( key, value ){
        if(key==='usr_ID'){
            this.options.ugr_ID = value;
            this._init();          
        }
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
  },
  
  //----
  _fromDataToUI: function(){
      
  },
  
  _doSave: function(){
      
  }
  
  
});
