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
        resourcePath: 'hclient/widgets/HMenu/HMenuPersonal',
    },
    
    _needLoadContent: true,
    _needLoadCss: false,
    
    /**
     * Initializes UI controls and event listeners after content is loaded.
     */
    _initControls:function(){

        this._super();
        
        this._on( this._$('button[data-heurist-action]'), {click : this.menuActionHandler }); //system actions
        
        // http://127.0.0.1/heurist/hclient/widgets/cms/WebSiteEditor.php?db=osmak_11&ver=3&website=12&pageid=27"
        //url = window.hWin.HEURIST4.ui.getCmsLink({mode:'edit', version:3, websiteid:rec_ID});
        //window.open(url); //reload page
        
        
        this.onChangeCredentials();
    },
    
    /*
    * Show/hide elements on menu depends on current credentials
    */    
    onChangeCredentials: function(data){

        if (this.HAPI.has_access()) {
                //
             this._$('.usrFullName').text(this.HAPI.currentUser?.ugr_FullName);
            
             this._$('div[data-heurist-role="menuPersonal-login"]').hide();
             this._$('div[data-heurist-role="menuPersonal-dropdown"]').show();
        } else {
             this._$('div[data-heurist-role="menuPersonal-login"]').show();
             this._$('div[data-heurist-role="menuPersonal-dropdown"]').hide();
        }
    }

    
});
