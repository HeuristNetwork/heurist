/**
* dbAction.js - popup dialog or widget to define action parameters, 
*               send request to server, show progress and final report
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.dbAction", $.heurist.baseAction, {

    // default options
    options: {
        actionName: '',
        default_palette_class: 'ui-heurist-admin',
        path: 'widgets/database/',
        entered_password: ''
    },
    
    _progressInterval:0,

    _init: function() {

        if(this.options.htmlContent=='' && this.options.actionName){
            this.options.htmlContent = this.options.actionName
                    +(window.hWin.HAPI4.getLocale()=='FRE'?'_fre':'')+'.html';
        }
        
        // dbCreate => create
        this.options.actionName = this.options.actionName.slice(2)
        this.options.actionName = this.options.actionName[0].toLowerCase()
                                    +this.options.actionName.slice(1)
        
        this._super();
    },
     
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        var that = this;
        
        //find and activate event listeners for elements
        this._on(this._$('input[type=text]'),{keypress:window.hWin.HEURIST4.ui.preventNonAlphaNumeric});
        
        this._$('button.ui-button-action').button(); // 
        this._on(this._$('button.ui-button-action'),{click:this.doAction});
        
        if(this.options.actionName=='create' &&
            window.hWin.HAPI4.sysinfo['pwd_DatabaseCreation']){
                this._$('#div_need_password').show();
        }
        else if(this.options.actionName=='clone'){
                this._checkNewDefinitions();
        }
        
        var ele = this._$('#uname');
        if(ele.val()=='' && window.hWin.HAPI4.currentUser){
            ele.val(window.hWin.HAPI4.currentUser.ugr_Name.substr(0,5).replace(/[^a-zA-Z0-9$_]/g,''));
        }
        this._on(this._$('#newdblink'),{click:this.closeDialog});
        this._$('span.dbname').text(window.hWin.HAPI4.database);

        this._$('#dbname').focus();
        
        return this._super();
    },

    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        //if(this.selectRecordScope) this.selectRecordScope.remove();

    },
    
    //----------------------
    //
    // array of button defintions
    //
    _getActionButtons: function(){
        return this._super();
    },

    //
    //
    //
    doAction: function(){
        
        var request;
        
        if(this.options.actionName=='create' 
            || this.options.actionName=='rename'
            || this.options.actionName=='clone')
        {

           var dbname = this._$('#dbname').val().trim();
           if(dbname==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of database'));
                return;  
           } 
           
           var ele = this._$('#uname');
           
           request = {action: this.options.actionName,
                      uname : (ele.length>0?ele.val().trim():''), 
                      dbname: dbname}; 

           if(this.options.actionName=='clone'){ 
                if(this._$('#nodata').is(':checked')){
                    request['nodata'] = 1;
                }
                var pwd = this._$('#pwd').val().trim();
                if(pwd!=''){
                    request['pwd'] = pwd;
                } 
                
           }else if(this.options.actionName=='create' && window.hWin.HAPI4.sysinfo['pwd_DatabaseCreation']){
                var pwd = this._$('#create_pwd').val().trim();
                if(pwd==''){
                    window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define password'));
                    return;  
                } 
                request['create_pwd'] = pwd;
           }

                      
        }else if(this.options.actionName=='clear' || this.options.actionName=='delete'){

           //challenged word
           var chpwd = this._$('#db-password').val().trim();
           if(chpwd==''){
               window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define challenge word'));
               return;  
           } 
           
           request = {action: this.options.actionName, 
                        //database : window.hWin.HAPI4.database, //current database
                        chpwd: chpwd
                     }; 

           if(this.options.entered_password){
                request['pwd'] = this.options.entered_password;
           }
              
        }else if(this.options.actionName=='restore'){
            
        }

        
        if(this.options.actionName=='delete' || this.options.actionName=='rename'){       
           if(!this._$('#db-archive').is(':checked')){
                request['noarchive'] = 1;
                this._$('li.archive').hide();
           }else{
                this._$('li.archive').show();
           }
        }
        
        //unique session id    
        var session_id = Math.round((new Date()).getTime()/1000);
        
        request['db'] = window.hWin.HAPI4.database;
        request['locale'] = window.hWin.HAPI4.getLocale();
        request['session'] = session_id;
        
        this._showProgress( session_id, false, 1000 );
        var that = this;
        
        window.hWin.HAPI4.SystemMgr.databaseAction( request,  function(response){

                that._hideProgress();
            
                if (response.status == window.hWin.ResponseStatus.OK) {
                    
                    that._afterActionEvenHandler(response.data);
                    
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    that._$('.ent_wrapper').hide();
                    that._$('#div_header').show();
                }
              
        });


        return;
    },
    
    //
    //
    //
    _checkNewDefinitions: function(){

        var that = this;
      
        var request = {action: 'check_newdefs',        
                       db: window.hWin.HAPI4.database};
        
        window.hWin.HAPI4.SystemMgr.databaseAction( request,  function(response){

                if (response.status == window.hWin.ResponseStatus.OK) {
                    if(response.data!=''){
                        that._$('#div_need_password').show();
                        that._$('#new_defs_warning').text(response.data);
                    }
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
              
        });
        
    },

    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler: function( response ){
        
        this._$('.ent_wrapper').hide();
        this._$("#div_result").show();
            
        if(this.options.actionName=='delete'){
            
            var msgAboutArc = '';
            if(this._$('input[name=db-archive]:checked').val()!=''){
               msgAboutArc = '<p>Associated files stored in upload subdirectories have been archived and moved to "DELETED_DATABASES" folder.</p>'
               + '<p>If you delete databases with a large volume of data, please ask your system administrator to empty this folder.</p>';                        
            }
    
            window.hWin.HEURIST4.msg.showMsgDlg(
                '<h3 style="margin:0">Database <b>'+window.hWin.HAPI4.database+'</b> has been deleted</h3>'+msgAboutArc
               ,null, 'Database deleted',
               {
                    width:700,
                    height:'auto',
                    close: function(){
                        //redirects to startup page - list of all databases
                        window.hWin.document.location = window.hWin.HAPI4.baseURL; //startup page
                    }
               }
            );             
            
        }else if(this.options.actionName=='rename'){
            
            var msgAboutArc = '';
            if(this._$('input[name=db-archive]:checked').val()!=''){
               msgAboutArc = '<p>Database with previous name has been archived and moved to "DELETED_DATABASES" folder.</p>';
            }
    
            window.hWin.HEURIST4.msg.showMsgDlg(
                '<h3 style="margin:0">Database <b>'+window.hWin.HAPI4.database+'</b> has been renamed</h3>'
                +msgAboutArc
                +'<p>You will be redirected to renamed database <b>'+window.hWin.HEURIST4.util.htmlEscape(response.newdbname)+'</b></p>'
               ,null, 'Database renamed',
               {
                    width:700,
                    height:'auto',
                    close: function(){
                        //redirects to renamed database
                        window.hWin.document.location = response.newdblink;
                    }
               }
            );             
            
            
        }else{
            
            this._$('#newdbname').text(response.newdbname);
            this._$('#newusername').text(response.newusername);
            this._$('#newdblink').attr('href',response.newdblink).text(response.newdblink);
            
            if(response.warnings && response.warnings.length>0){
                this._$('#div_warnings').html(response.warnings.join('<br><br>')).show();
                this._$('#div_login_info').hide();
            }
               
            //clear local list of databases   
            if(window.hWin && window.hWin.HAPI4){
                window.hWin.HAPI4.EntityMgr.emptyEntityData('sysDatabases');
            }    
            
        }
                    
       
    },

    //
    // Requests reportProgress every t_interval ms 
    // is_autohide 
    //    true  - stops progress check if it returns null/empty value
    //    false - it shows rotating(loading) image for null values and progress bar for n,count values
    //                  in latter case _hideProgress should be called explicitely
    //
    _showProgress: function ( session_id, is_autohide, t_interval ){

        if(!(session_id>0)) {
             this._hideProgress();
             return;
        }
        
        //show wait screen
        window.hWin.HEURIST4.msg.bringCoverallToFront(null, {opacity: '0.3'}, window.hWin.HR(this.options.title));
        $('body').css('cursor','progress');
        
        var that = this;
       
        var progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";

        this._$('.ent_wrapper').hide();
        var progress_div = this._$('.progressbar_div').show();
        
        var div_loading = progress_div.find('.loading').show();
        div_loading.find('li').css('color','lightgray');
        var that = this;
        var prevStep = 0;
        
                
        this._progressInterval = setInterval(function(){ 
            
            var request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                
                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    that._hideProgress();
                }else if(response=='terminate' && is_autohide){
                    that._hideProgress();
                }else if(prevStep!=response){
                    prevStep = response;
                    if(window.hWin.HEURIST4.util.isNumber(prevStep)){
                        var arr = div_loading.find('li').slice(0,prevStep);
                        arr.css('color','black'); 
                    }else{
                        $('<li>'+prevStep+'</li>').appendTo(div_loading.find('ol'));
                    }
                }
            },'text');
        
        }, t_interval);                
        
    },
    
    _hideProgress: function (){
        
        $('body').css('cursor','auto');
        window.hWin.HEURIST4.msg.sendCoverallToBack(true);
        
        if(this._progressInterval!=null){
            
            clearInterval(this._progressInterval);
            this._progressInterval = null;
        }
        this._$('.ent_wrapper').hide();
        this._$('#div_header').show();
        
    },
    

});

