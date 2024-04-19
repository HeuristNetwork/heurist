/**
* dbAction.js - popup dialog or widget to define action parameters, 
*               send request to server, show progress and final report
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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
        path: 'widgets/database/'
    },
    
    _progressInterval:0,


    _init: function() {

        if(this.options.htmlContent=='' && this.options.actionName){
            this.options.htmlContent = this.options.actionName
                    +(window.hWin.HAPI4.getLocale()=='FRE'?'_fre':'')+'.html';
        }
        
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
        this._on(this.element.find('input[type=text]'),{keypress:window.hWin.HEURIST4.ui.preventNonAlphaNumeric});
        
        this.element.find('#btnCreateDb').button(); // 
        this._on(this.element.find('#btnCreateDb'),{click:this.doAction});
        
        if(this.options.actionName=='create'){
                var ele = this.element.find('#uname');
                if(ele.val()=='' && window.hWin.HAPI4.currentUser){
                    ele.val(window.hWin.HAPI4.currentUser.ugr_Name.substr(0,5).replace(/[^a-zA-Z0-9$_]/g,''));
                }
                $('#dbname').focus();
                
                this._on(this.element.find('#newdblink'),{click:this.closeDialog});
        }
        
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
        
        if(this.options.actionName=='create'){
           
           request = {action: 'create',
                      uname : $('#uname').val(), 
                      dbname: $('#dbname').val()}; 

        }else if(this.options.actionName=='clone'){
            
           request = {action: 'clone', 
                      nodata: $('#nodata').is(':checked'),
                      dbname: $('#dbname').val(),
                      }; 
            
        }else if(this.options.actionName=='clear'){

           request = {action: 'clear', 
                      dbname: $('#dbname').val()
                      }; 
            
        }else if(this.options.actionName=='delete'){

           request = {action: 'delete', 
                      dbname: $('#dbname').val(),
                      archive: $('#iszip').is(':checked')
                      }; 
            
        }else if(this.options.actionName=='rename'){

           request = {action: 'rename', 
                      dbname: $('#dbname').val()
                      }; 
            
        }else if(this.options.actionName=='restore'){
            
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
                    that.element.find('.ent_wrapper').hide();
                    that.element.find('#div_header').show();
                }
          
              
        });


        return;
    },

    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler: function( response ){
        
        if(this.options.actionName=='create'){
            
            this.element.find('.ent_wrapper').hide();
                        
            this.element.find("#div_result").show();
            this.element.find('#newdbname').text(response.newdbname);
            this.element.find('#newusername').text(response.newusername);
            this.element.find('#newdblink').attr('href',response.newdblink).text(response.newdblink);
            
            if(response.warnings && response.warnings.length>0){
                this.element.find('#div_warnings').html(response.warnings.join('<br><br>')).show();
                this.element.find('#div_login_info').hide();
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
        var that = this;
       
        var progress_url = window.hWin.HAPI4.baseURL + "viewers/smarty/reportProgress.php";

        //this.element.find('#div_header').hide();
        this.element.find('.ent_wrapper').hide();
        var progress_div = this.element.find('.progressbar_div').show();
        
        $('body').css('cursor','progress');
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
                    var arr = div_loading.find('li').slice(0,prevStep);
                    arr.css('color','black'); 
                }
            },'text');
        
        }, t_interval);                
        
    },
    
    _hideProgress: function (){
        
        $('body').css('cursor','auto');
        
        if(this._progressInterval!=null){
            
            clearInterval(this._progressInterval);
            this._progressInterval = null;
        }
        this.element.find('.progressbar_div').hide();
        this.element.find('#div_header').show();
        
    },
    

});

