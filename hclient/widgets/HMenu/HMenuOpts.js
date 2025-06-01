/**
* HMenuOpts - form to modify HMenu options
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

import '../HBase/HBaseOpts.js';

$.widget( 'heurist.HMenuOpts', $.heurist.HBaseOpts, {
    
    // default options
    options: {
        resourcePath: 'hclient/widgets/HMenu/HMenuOpts',
    },
    
    /*
    *
    */
    _initControls:function(){
        
        this._super();

        let uiInput = this._$('#menuItems');
        
        let hiddenInput = this._$('input[name="menuItems"]');
        
        //let rval = hiddenInput.val();
        //rval =  rval?rval.split(','):[];
        
        /*
        if(!uiInput.editing_input('instance')){
            
            let rty_IDs = [window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU']];
            if(window.hWin.HAPI4.sysinfo['dbconst']['RT_WEB_CONTENT']){ //for Digital Harlem
                rty_IDs.push(window.hWin.HAPI4.sysinfo['dbconst']['RT_WEB_CONTENT']);
            }

            const ed_options = {
                recID: -1,                                                                                       
                dtID: uiInput.attr('id'), //'group_selector',
                //show_header: false,
                values: rval,
                readonly: false,
                show_header: false,
                showclear_button: true,
                dtFields:{
                    dty_Type:"resource", rst_MaxValues:0,
                    rst_DisplayName: 'Top level menu items', rst_DisplayHelpText:'',
                    rst_PtrFilteredIDs: rty_IDs,
                    rst_FieldConfig: {entity:'records', csv:false}
                },
                change: ()=>{
                    hiddenInput.val(uiInput.editing_input('getValues')).change();
                }
            };

            uiInput.editing_input(ed_options);
        }   */
        
        let uiTree = this._$('#menuTree');

        uiTree.HMenu({menuItems:hiddenInput.val(), viewMode:'treeview', isEditMode: true, expandLevels: 2, 
                onStructureChanged:(menuItems)=>{
                    hiddenInput.val(JSON.stringify(menuItems)).change();
                }});
                
        this._on(this._$('#menuTreeAdd').button(), {click: ()=>uiTree.HMenu('addMenuEntry',0)});

    }    
    
    //menu as website pages
    
    //menu as record view
    
    //menu as actions: add record, saved filter, filter string, export csv
    
});
