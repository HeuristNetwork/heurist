/**
* @file recordNotify.js
* @brief Send email about a set of records.
* @fileOverview This file defines the `recordNotify` widget. It enables users to send email
* notifications to other users about a specific set of records. The notification includes a URL that,
* when opened, displays the list of these records in a Heurist search. The widget utilizes the
* `usrReminders` entity's dialog/widget for composing the notification message and selecting recipients.
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @class recordNotify
 * @augments {recordAction}
 * @memberof Widgets.Records
 * @description jQuery widget for sending email notifications about a set of records.
 * This widget allows a user to select a scope of records and then compose an email
 * notification to share these records with other users. It embeds and uses the
 * `usrReminders` widget/dialog for handling the email composition and recipient selection.
 *
 * @param {object} options - Configuration options for the widget.
 */
$.widget( "heurist.recordNotify", $.heurist.recordAction, {

    /**
     * @memberof Widgets.Records.recordNotify
     * @type {object}
     * @property {number} [height=500] - Dialog height.
     * @property {number} [width=700] - Dialog width.
     * @property {boolean} [modal=true] - Is dialog modal.
     * @property {string} [init_scope='selected'] - Initial record scope.
     * @property {string} [title='Notification'] - Dialog title.
     * @property {boolean|string} [helpContent=false] - Help content.
     */
    options: {
    
        height: 500,
        width:  700,
        modal:  true,
        init_scope: 'selected',
        title:  'Notification',
        helpContent: false  //'usrTags'
    },

    /**
     * @member {?jQuery} _reminderWidgetContainer
     * @memberof Widgets.Records.recordNotify
     * @private
     * @description jQuery object for the div that contains the embedded `usrReminders` widget.
     */
    _reminderWidgetContainer:null,
    /**
     * @member {?object} _reminderWidget
     * @memberof Widgets.Records.recordNotify
     * @private
     * @description Instance of the `usrReminders` widget used for composing the notification.
     */
    _reminderWidget:null,
    
    /**
     * @function _initControls
     * @memberof Widgets.Records.recordNotify
     * @private
     * @description Initializes controls after HTML content is loaded.
     * Sets a header message. Embeds and initializes the `usrReminders` widget
     * within `_reminderWidgetContainer` for composing the notification, hiding irrelevant parts
     * of the reminders widget (like scheduling).
     * Calls the parent widget's `_initControls` method.
     * @returns {boolean|undefined} Value returned by parent's `_initControls`.
     */
    _initControls:function(){
        
        this._$('#div_header')
            .css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html('Share these records with other users via email<br>'
            +'Notification includes a URL which will open the list of records<br>'
            +'in a Heurist search, from which they can be bookmarked<br>');
        
        this._reminderWidgetContainer = $('<div>').addClass('ent_wrapper').css({'top':'120px'}).appendTo( this.element );
        
        let that = this;
        
        this._reminderWidget = window.hWin.HEURIST4.ui.showEntityDialog('usrReminders', {
                isdialog: false,
                container: this._reminderWidgetContainer,
                edit_mode: 'editonly',
                onInitFinished: function(){
                    that._reminderWidgetContainer.find('.heurist-helper1').each(function(idx,item){
                        $(item).html($(item).html().replace('reminder', "notification") );
                    });
                    that._reminderWidgetContainer.find('fieldset').last().hide(); //hide When
                    that._reminderWidgetContainer.find('.ent_footer.editForm-toolbar').hide(); // hide Toolbar                    
                }
        });
        
        
        //align scope selector and edit form
        this._$('#div_fieldset').css({'padding':'10px 0px'});
        this._$('#div_fieldset .header').css({'padding':'0 24px 0 0'});
        
        return this._super();
    },
    
    /**
     * @function _destroy
     * @memberof Widgets.Records.recordNotify
     * @private
     * @description Cleans up the widget. Removes the embedded `_reminderWidget` if it exists.
     * Calls the parent widget's `_destroy` method.
     */
    _destroy: function() {
        this._super();
        if(this._reminderWidget) this._reminderWidget.remove();
    },
    
    /**
     * @function _getActionButtons
     * @memberof Widgets.Records.recordNotify
     * @private
     * @description Gets action buttons for the dialog, setting the main action button text to 'Notify'.
     * @returns {Array<object>} Array of button definition objects.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Notify');
        return res;
    },
    
    /**
     * @function doAction
     * @memberof Widgets.Records.recordNotify
     * @private
     * @description Performs the notification sending action.
     * Retrieves validated field values (recipients, subject, message) from the embedded `usrReminders` widget.
     * Determines the scope of records to be included in the notification.
     * Constructs a batch request to the `EntityMgr` for the `usrReminders` entity to send the notification.
     * Displays a success or error message based on the API response.
     */
    doAction: function(){

        let scope_val = this.selectRecordScope.val();
        if(scope_val=='')    return;
        
        let editForm = $(this._reminderWidgetContainer).manageUsrReminders('instance');
        let fields = editForm._getValidatedValues();//this._reminderWidget.manageUsrReminders('_getValidatedValues'); 
        if(fields==null) return; //validation failed

        let scope = [], 
        rec_RecTypeID = 0;
        
        if(scope_val == 'selected'){
            scope = this._currentRecordsetSelIds;
        }else { //(scope_val == 'current'
            scope = this._currentRecordset.getIds();
            if(scope_val  >0 ){
                rec_RecTypeID = scope_val;
            }   
        }
        
        let request = {                                                                                        
            'a'          : 'batch',
            'entity'     : 'usrReminders',
            'request_id' : window.hWin.HEURIST4.util.random(),
            'fields'     : fields,
            'rec_IDs'    : scope                     
            };
            
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
            
            let dlged = editForm._getEditDialog();
            if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgFlash('Notification '+window.hWin.HR('has been sent'));
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
                        
    },

  
});

