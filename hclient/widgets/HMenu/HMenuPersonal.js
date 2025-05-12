/**
* HMenuPersonal - login button and dropdown menu
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
import './HMenu.js';

$.widget( 'heurist.HMenuPersonal', $.heurist.HMenu, {

    // default options
    options: {
        isMenuMode: false, //if false - button mode
        resourcePath: 'hclient/widgets/HMenu/HPersonalMenu',
        reloadOnLogin: false
    },
    
    _needLoadContent: true,
    _needLoadCss: false,
    
    _init: function() {
        
        if(this.options.isMenuMode){
            this.options.resourcePath = 'hclient/widgets/HMenu/HPersonalMenu';
        }else{
            this.options.resourcePath = 'hclient/widgets/HMenu/HPersonalBtn';
        }
        this._super();
        
        this.element.css('z-index',99999);
    },
    
    /**
     * Initializes UI controls and event listeners after content is loaded.
     */
    _initControls:function(){

        this._super();
        
        this._on( this._$('button[data-heurist-action]'), {click : this.menuActionHandler }); //system actions
      
        this._$('button[data-heurist-action="menu-profile-admin"]').attr('href',
                      window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database);
                      
        const isEdit = window.parent?.cmsEditor!=null;
        const sLabel = window.HR(isEdit?'Close editor':'Website editor');
            
        let btn = this._$('button[data-heurist-action="menu-cms-edit"] > span');
        if(btn.length>1){
            $(btn[0]).addClass('ui-icon-'+isEdit?'close':'pencil').removeClass('ui-icon-'+isEdit?'pencil':'close');
            btn[1].innerText = sLabel;
        }else{
            this._$('a[data-heurist-action="menu-cms-edit"]').text(sLabel);
        }
        
        this.onChangeCredentials();
    },
    
    /*
    * Show/hide elements on menu depends on current credentials
    */    
    onChangeCredentials: function(data){

        if (this.HAPI.has_access()) {
             
             if(this.options.reloadOnLogin){
                  location.reload();  
             }
            
             //
             this._$('.usrFullName').text(this.HAPI.currentUser?.ugr_FullName);
            
             this._$('div[data-heurist-role="menuPersonal-login"]').hide();
             this._$('div[data-heurist-role="menuPersonal-dropdown"]').show();
        } else {
             this._$('div[data-heurist-role="menuPersonal-login"]').show();
             this._$('div[data-heurist-role="menuPersonal-dropdown"]').hide();
        }
    },
    
    /*
    *
    */    
    menuActionHandler:function(event, ui){
        
        event.preventDefault(); 
        let ele = this.getUiEle(event, ui);
        let action_id = ele.attr('data-heurist-action');
        if(action_id=='menu-cms-edit'){
            if(window.parent?.cmsEditor){
                window.hWin.webSite.closePageEditor();
            }else{
                window.hWin.webSite.openPageEditor();
            }
            return;
        }
        
        this._super(event, ui);
    }

    
});
