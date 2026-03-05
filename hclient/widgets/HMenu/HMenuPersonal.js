/**
 * @file HMenuPersonal.js
 * @brief extension for persoanl menu actions (including login, signin, logout)
 * @fileOverview HPersonalBtn.html - as buttons
 * HPersonalMenu.html - as dropdown menu
 * @project     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       7.0
 */
import './HMenu.js';

/**
 * @class HMenuPersonal
 * @augments {HMenu}
 * @memberof Widgets.UI
 * @description extension for persoanl menu actions (including login, signin, logout)
 * @param {object} options - Configuration options for the widget.
 */
$.widget( 'heurist.HMenuPersonal', $.heurist.HMenu, {

    /**
     * @memberof Widgets.UI.HMenuPersonal
     * @type {object}
     * @property {boolean} isMenuMode - Whether the menu is in menu mode.
     * @property {string} resourcePath - The path to the widget's resources.
     * @property {boolean} reloadOnLogin - Whether to reload on login.
     */
    options: {
        isMenuMode: false, //navbar   if false - button mode p
        resourcePath: 'hclient/widgets/HMenu/HPersonalMenu',
        reloadOnLogin: false
    },
    
    _needLoadContent: true,
    _needLoadCss: false,
    
    /**
     * @private
     * @memberof Widgets.UI.HMenuPersonal
     * @description Initializes the widget.
     */
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
     * @private
     * @memberof Widgets.UI.HMenuPersonal
     * @description Initializes UI controls and event listeners after content is loaded.
     */
    _initControls:function(){

        this._super();
        
        this._on( this._$('button[data-heurist-action]'), {click : this.menuActionHandler }); //system actions
      
        this._$('button[data-heurist-action="menu-profile-admin"]').attr('href',
                      window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database);
                      
        const isCmsEdit = window.hWin.HEURIST4.util.getParentWinProperty('cmsEditor');
        const sLabel = window.HR( isCmsEdit?'Close editor':'Website editor' );
            
        let btn = this._$('button[data-heurist-action="menu-cms-edit"] > span');
        if(btn.length>1){
            $(btn[0]).addClass('ui-icon-'+isCmsEdit?'close':'pencil').removeClass('ui-icon-'+isCmsEdit?'pencil':'close');
            btn[1].innerText = sLabel;
        }else{
            this._$('a[data-heurist-action="menu-cms-edit"]').text(sLabel);
        }
        
        this.onChangeCredentials();
    },
    
    /**
     * @memberof Widgets.UI.HMenuPersonal
     * @description Show/hide elements on menu depends on current credentials
     * @param {object} data - The data from the event.
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
    
    /**
     * @private
     * @memberof Widgets.UI.HMenuPersonal
     * @description Handles the menu action.
     * @param {Event} event - The event object.
     * @param {object} ui - The UI object.
     */
    menuActionHandler:function(event, ui){
        
        event.preventDefault(); 
        let ele = this.getUiEle(event, ui);
        let action_id = ele.attr('data-heurist-action');
        if(action_id=='menu-cms-edit'){
            if(window.hWin.HEURIST4.util.getParentWinProperty('cmsEditor')){
                window.hWin.webSite.closePageEditor();
            }else{
                window.hWin.webSite.openPageEditor();
            }
            return;
        }
        
        this._super(event, ui);
    }

    
});
