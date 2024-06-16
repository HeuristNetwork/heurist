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
    _select_file_dlg:null,
    Heurist_Reference_Index: 'Heurist_Reference_Index',

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
        
        this._$('button').button();
        this._on(this._$('button.ui-button-action'),{click:this.doAction});
        
        if(this.options.actionName=='create' &&
            window.hWin.HAPI4.sysinfo['pwd_DatabaseCreation'])
        {
            this._$('#div_need_password').show();
        }
        else if(this.options.actionName=='clone')
        {
            this._checkNewDefinitions();
        }else if(this.options.actionName=='restore')
        {
            this._on(this._$('#btnSelectZip'),{click:this._selectArchive});
        
        }else if(this.options.actionName=='register')
        {
            
            var that = this;
            window.hWin.HAPI4.EntityMgr.getEntityData('sysIdentification', false, function(response){
                if(!window.hWin.HEURIST4.util.isempty(response)){
                    var record = response.getFirstRecord();
                    that._$('.dbDescription').text(record[17]);
                    that._$('#dbTitle').val(record[17]).trigger('keyup');
                }});

            if(window.hWin.HAPI4.sysinfo['db_registeredid']>0){
 
                this._$('.dbDescription').text('');
                this._$('span.dbId').text(window.hWin.HAPI4.sysinfo['db_registeredid']);
                this._$('a.dbLink').attr('href',
                    window.hWin.HAPI4.sysinfo['referenceServerURL']
                        +'?fmt=edit&recID='+window.hWin.HAPI4.sysinfo['db_registeredid']
                        +'&db='+this.Heurist_Reference_Index)
                       
                this._$('.ent_wrapper').hide();
                var div_res = this._$("#div_result").show();
                        
            }else{
            
                this._$('#serverURL').val(window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database);
                this._$('#dbTitle').val('');
                
                if(window.hWin.HAPI4.user_id()!=2){
                    this._$('#div_need_password').show();
                }else{
                    this._$('#div_need_password').hide();
                }
                
                this._on(this._$('#dbTitle'),{keyup:
                        function ( event ){
                            var len = $(event.target).val().length;
                            var ele = this._$('#cntChars').text(len);
                            ele.parent().css('color',(len<40)?'red':'#6A7C99');
                        }
                });
                
            }

        }


        //user and database name inputs        
        var ele = this._$('#uname');
        if(ele.val()=='' && window.hWin.HAPI4.currentUser){
            ele.val(window.hWin.HAPI4.currentUser.ugr_Name.substr(0,5).replace(/[^a-zA-Z0-9$_]/g,''));
        }
        this._on(this._$('#newdblink'),{click:this.closeDialog});
        this._$('span.dbname').text(window.hWin.HAPI4.database);

        this._$('#dbname').focus();

        //find and activate event listeners for elements
        this._on(this._$('input[type=text]'),{keypress:window.hWin.HEURIST4.ui.preventNonAlphaNumeric});
        
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
           
           request = {uname : (ele.length>0?ele.val().trim():''), 
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
           
           request = {
                        //database : window.hWin.HAPI4.database, //current database
                        chpwd: chpwd
                     }; 

           if(this.options.entered_password){
                request['pwd'] = this.options.entered_password;
           }
              
        }else if(this.options.actionName=='restore'){
            
           var dbname = this._$('#dbname').val().trim();
           if(dbname==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of database'));
                return;  
           } 
           
           //check for source/archive file
           var dbarchive_file = this._$('#selectedZip').text();
           if(dbarchive_file==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of source zip archive'));
                return;  
           }
           
           var dbarchive_folder = this._$('input[name=selArchiveFolder]:checked').val();

           request = {file: dbarchive_file,
                      folder: dbarchive_folder, //id
                      dbname: dbname,
                      pwd: this.options.entered_password}; 
                      
        }else if(this.options.actionName=='register'){
            
            let description = this._$('#dbTitle').val().trim();
            if(description.length<40){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define informative description (min 40 characters)'));
                return;  
            }

           request = {dbReg: window.hWin.HAPI4.database,
                      dbTitle: description,
                      dbVer: window.hWin.HAPI4.sysinfo['db_version'],
                      serverURL: this._$('#serverURL').val()
                      }; 
                      
           if(window.hWin.HAPI4.user_id()!=2){
                let pwd = this._$('#pwd').val().trim()    
                if(pwd==''){
                    window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define password'));
                    return;  
                } 
                request['pwd'] = pwd;
           }
        }//end switch

        
        if(this.options.actionName=='delete' || this.options.actionName=='rename'){       
           if(!this._$('#db-archive').is(':checked')){
                request['noarchive'] = 1;
                this._$('.archive').hide();
           }else{
                this._$('.archive').show();
           }
        }
        
        
        //unique session id    ------------------------
        var session_id = Math.round((new Date()).getTime()/1000);
        
        request['action'] = this.options.actionName;       
        request['db'] = window.hWin.HAPI4.database;
        request['locale'] = window.hWin.HAPI4.getLocale();
        request['session'] = session_id;

        this._showProgress( session_id, false, (this.options.actionName=='register')?0:1000 );
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
    
    // Action: Clone
    // Verify new defintions in database to be cloned
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

    // Action: Restore
    // select database zip archive to be restored
    //
    _selectArchive: function(){
        
        var that = this;
        var src_folder = this._$('input[name=selArchiveFolder]:checked').val();
        
        
        if(!this._select_file_dlg){
            this._select_file_dlg = $('<div>').hide().appendTo( this.element );
        
            this._select_file_dlg.selectFile({
               keep_dialogue: true,
               showFilter: true, 
               source: src_folder,     
               extensions: (src_folder==3)?'zip,bz2':'zip',
               title: window.HR('Select database archive'),
               onselect:function(res){
                    if(res && res.filename){
                        that._$('#selectedZip').text(res.filename);
                        that._$('#divSelectedZip').show();
                        //alert(res.path, res.filename);
                        
                        //suggest database name
                        if(that._$('#dbname').val().trim()==''){
                            var sname = res.filename; 
                            if(sname.indexOf('hdb_')==0){
                                sname = sname.substring(4);
                            }
                            if(sname.indexOf('.')>0){
                                sname = sname.substring(0,sname.indexOf('.'));
                            }
                            if(sname.length>24){
                                sname = sname.substring(0,23);
                            }
                            that._$('#dbname').val(sname);
                        }
                        
                    }else{
                        that._$('#selectedZip').text('');
                        that._$('#divSelectedZip').hide();
                    }
               }});        
        }else{
            this._select_file_dlg.selectFile({
               source: src_folder,     
               extensions: (src_folder==3)?'zip,bz2':'zip'
            });
        }

    },

    
    
    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler: function( response ){
        
        this._$('.ent_wrapper').hide();
        var div_res = this._$("#div_result").show();
        
        if(response && response.newdbname){
            this._$('#newdbname').text(response.newdbname);  
        } 
            
        if(this.options.actionName=='delete'){

            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database deleted'),
               {
                    width:700,
                    height:'auto',
                    close: function(){
                        //redirects to startup page - list of all databases
                        window.hWin.document.location = window.hWin.HAPI4.baseURL; //startup page
                    }
               }
            );
            
        }else if(this.options.actionName=='rename')
        {
            if(response.warning){
                div_res.find('.warning').html(window.hWin.HEURIST4.util.stripTags(response.warning,'p,br'));
            }
            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database renamed'),
               {
                    width:700,
                    height:'auto',
                    close: function(){
                        //redirects to renamed database
                        window.hWin.document.location = response.newdblink;
                    }
               }
            );
            
        }else if(this.options.actionName=='register'){

            this._$('.dbDescription').text(response.dbTitle);
            this._$('span.dbId').text(response.dbID);
            this._$('a.dbLink').attr('href',
                window.hWin.HAPI4.baseURL_pro
                    +'?fmt=edit&recID='+response.dbID
                    +'&db='+this.Heurist_Reference_Index);
            
            
            //reload page
            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database registered'),
               {
                    width:700,
                    height:'auto',
                    close: function(){
                        //redirects to renamed database
                        window.hWin.document.location.reload();
                    }
               }
            );
            
        }else{
            
            
            this._$('#newusername').text(response.newusername);
            this._$('#newdblink').attr('href',response.newdblink).text(response.newdblink);
            
            if(response.warnings && response.warnings.length>0){
                this._$('#div_warnings').html(response.warnings.join('<br><br>')).show();
                this._$('#div_login_info').hide();
            }
               
        }
                    
        //clear local list of databases   
        if(this.options.actionName!='clear'){
            window.hWin.HAPI4.EntityMgr.emptyEntityData('sysDatabases');
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
       
        this._$('.ent_wrapper').hide();
        var progress_div = this._$('.progressbar_div').show();
        
        var div_loading = progress_div.find('.loading').show();
        div_loading.find('li').css('color','lightgray');
        var that = this;
        var prevStep = 0;
        
        if(t_interval>900){

            var progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";
                
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
        
        }            
        
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

