/**
*  Dialog to edit profile(user) or register new user
*/
$.widget( "heurist.profile_edit", {

  // default options
  options: {
    isdialog: true,  
    
    ugr_ID:0,
    edit_data: null    
      
    // callbacks
  },

   // the widget's constructor
  _create: function() {

    // prevent double click to select text
    this.element.disableSelection();
      
    this.edit_form = $( "<div>" )
        .appendTo( this.element );
        
    // Sets up element to apply the ui-state-focus class on focus.
    //this._focusable($element);   
      
    this._refresh();

  }, //end _create

  // Any time the widget is called with no arguments or with only an option hash, 
  // the widget is initialized; this includes when the widget is created.
  _init: function() {

        if(this.edit_form.is(':empty') ){
            
            var that = this;
      
            this.edit_form.load("apps/profile_edit.html?t="+(new Date().getTime()), 
            function(){
                
                //find all labels and apply localization
                that.edit_form.find('label').each(function(){
                     $(this).html(top.HR($(this).html()));
                });
                var allFields = that.edit_form.find('input');
                
                function __doSave(){
                    that._doSave();
                }
                
                if(that.options.isdialog){
                       that.edit_form.dialog({
                          autoOpen: false,
                          height: 580,
                          width: 640,
                          modal: true,
                          resizable: true,
                          title: '',
                          buttons: [
                            {text:top.HR(Number(that.options.ugr_ID)>0?'Save':'Register'), click: __doSave},
                            {text:top.HR('Cancel'), click: function() {
                              $( this ).dialog( "close" );
                            }}
                          ],
                          close: function() {
                            allFields.val( "" ).removeClass( "ui-state-error" );
                          }
                       });
                }
                
                that._init();
                
              });
        
        }else{
      
            this._fromDataToUI();
                    
        }
        
  },
  
  _setOption: function( key, value ){
        if(key==='ugr_ID'){
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
      
      var allFields = this.edit_form.find('input, textarea');
      allFields.val( "" ).removeClass( "ui-state-error" );
      
      /*if(this.options.ugr_ID == top.HAPI.currentUser.ugr_ID){
          
          this.options.edit_data = top.HAPI.currentUser;
          
      }else */
      if(Number(this.options.ugr_ID)>0){
          
          if(this.options.edit_data && this.options.edit_data['ugr_ID']==this.options.ugr_ID) {
              
          }else{
                var that = this;
                top.HAPI.SystemMgr.user_get( { UGrpID: this.options.ugr_ID},
                        function(response){
                            var  success = (response.status == top.HAPI.ResponseStatus.OK);
                            if(success){
                                that.options.edit_data = response.data;
                                if(that.options.edit_data && that.options.edit_data['ugr_ID']==that.options.ugr_ID){
                                    that._fromDataToUI();
                                }else{
                                    top.HEURIST.util.showMsgErr("Unexpected user data obtained from server");
                                }
                            }else{
                                top.HEURIST.util.showMsgErr(response.message);
                            }
                        }
                );
                return;
          }
      }else{
         this.options.edit_data = null; 
      }
      
      if(this.options.edit_data){ //fill inputs with values
          
          for(id in this.options.edit_data){
              if(!top.HEURIST.util.isnull(id)){
                  var inpt = this.edit_form.find("#"+id).val(this.options.edit_data[id]);
                  //if(inpt){                    inpt.val(this.options.edit_data[id]);                  }
              }
          }
          
          if(Number(this.options.ugr_ID)>0){
              this.edit_form.find("#ugr_Password").removeClass('mandatory');
          }else{
              this.edit_form.find("#ugr_Password").addClass('mandatory');
          }
          
      }
      
      if(this.options.isdialog){
                this.edit_form.dialog('option', 'title', 
                        Number(this.options.ugr_ID)>0 ? top.HR('Edit profile')+': '+this.options.edit_data['ugr_FirstName'] + ' ' + this.options.edit_data['ugr_LastName'] //this.options.edit_data['ugr_FullName'] 
                                                      : top.HR('Registration')  );
                this.edit_form.dialog("open");
      }
  },
  
  _doSave: function(){
      
      var that = this;
      var allFields = this.edit_form.find('input, textarea');
      var err_text = '';
      
      // validate mandatory fields
      allFields.each(function(){
          var input = $(this);
          if(input.hasClass('mandatory') && input.val()==''){
              input.addClass( "ui-state-error" );
              err_text = err_text + ', '+that.edit_form.find('label[for="' + input.attr('id') + '"]').html();
          }
      });
      
      if(err_text==''){
          // validate email 
          // From jquery.validate.js (by joern), contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
          var email = this.edit_form.find("#ugr_eMail");
          var bValid = top.HEURIST.util.checkRegexp( email, /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i );
          if(!bValid){
                err_text = err_text + ', '+top.HR('Wrong email format');
          }
          
          // validate login
          var login = this.edit_form.find("#ugr_Name");
          if(!top.HEURIST.util.checkRegexp( login, /^[a-z]([0-9a-z_@.])+$/i)){
                err_text = err_text + ', '+top.HR('Wrong user name format');   // "Username may consist of a-z, 0-9, _, @, begin with a letter." 
          }else{
                var ss = top.HEURIST.util.checkLength2( login, "user name", 3, 16 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }
         }
      
          // validate passwords    
          var password = this.edit_form.find("#ugr_Password");
          var password2 = this.edit_form.find("#ugr_Password2");
          if(password.val()!=password2.val()){
              err_text = err_text + ', '+top.HR(' Passwords are different');
              password.addClass( "ui-state-error" );
          }else  if(password.val()!=''){
              if(!top.HEURIST.util.checkRegexp( password, /^([0-9a-zA-Z])+$/)){  //allow : a-z 0-9
                    err_text = err_text + ', '+top.HR('Wrong password format');
              }else{
                var ss = top.HEURIST.util.checkLength2( password, "password", 3, 16 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }
              }
          }
          
          if(err_text!=''){
              err_text = err_text.substring(2);
          }
          
          
      }else{
          err_text = top.HR('Missed required fields')+': '+err_text.substring(2);
      }
      
      if(err_text==''){
                // fill data with values from UI
                allFields.each(function(){
                        var input = $(this);
                        if(that.options.edit_data[input.attr('id')]!=undefined){
                            
                            if(input.attr('type') === "checkbox"){
                                that.options.edit_data[input.attr('id')] = input.is(':checked')?'y':'n';
                            }else{
                                that.options.edit_data[input.attr('id')] = input.val();
                            }
                        }
                });
                  
                var that = this;
                top.HAPI.SystemMgr.user_save( { UGrpID: this.options.ugr_ID},
                        function(response){
                            var  success = (response.status == top.HAPI.ResponseStatus.OK);
                            if(success){
                                if(that.options.isdialog){
                                        that.edit_form.dialog("close");
                                }
                            }else{
                                top.HEURIST.util.showMsgErr(response.message);
                            }
                        }
                );
      
      
      }else{       
          top.HEURIST.util.showMsgErr(err_text);
          /*var message = $dlg.find('.messages');
          message.html(err_text).addClass( "ui-state-highlight" );
          setTimeout(function() {
                        message.removeClass( "ui-state-highlight", 1500 );
          }, 500 );*/
      }
         
  }
  
  
});

//this function is used in edit_profile.html
function autofill_login(value){
    var ele = $('#ugr_Name');
    if(ele && ele.val()==''){
        ele.val(value);
    }
}
