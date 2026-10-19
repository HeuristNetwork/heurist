/**
* @file databaseOverview.js
* @brief Display general information and statistics regarding the database.
*
* @project     Heurist academic knowledge management system
* @package     hclient\widgets\admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

$.widget('heurist.dbOverview', {

    options: {
        publishMode: false
    },

    _mainMenu: null,

    _dbOverviewFile: 'hclient/widgets/admin/databaseOverview.html',
    _exploreImage: 'hclient/assets/v6/gs_explore_cb.png',
    _loadedHTML: false,
    _editingProperties: false,

    _$: $,

    _create: function(){
        this._mainMenu = window.hWin.HAPI4.LayoutMgr.getWidgetByName('slidersMenu') || $();
        this._loadOverview();
    },

    _refresh: function(){
        this.updateDetails();
        this.updateAccess();
    },

    _loadOverview: function(){

        if(this._loadedHTML){
            this._refresh();
            return;
        }

        this.element.load(`${window.hWin.HAPI4.baseURL}${this._dbOverviewFile}`, () => {
            this._loadedHTML = true;
            this._setupInterface();
        });
    },

    _setupInterface: function(){

        this._setupMockHeaders();
        this._setupRecordDetails();

        // Link to quick tips popup
        this._$('div#quick_tips')
        .css({
            'text-decoration': 'underline',
            color: 'blue',
            width: '100px',
            'text-align': 'center',
            'margin-left': 'auto',
            'margin-right': 'auto',
            'margin-top': '40px',
            cursor: 'pointer'
        })
        .on('click', function(){
            window.hWin.HAPI4.actionHandler.executeActionById('menu-help-quick-tips');
        });

        this.updateDetails();
        this.updateAccess();
    },

    _setupMockHeaders: function(){

        // Section headers within Content
        let $headers = this._$('div.mock-header');
        // Explanation Text for each section
        let $flavourTexts = this._$('span.flavour-text');
        // Add image to explore header
        let $exploreImg = this._$('img#explore-img');

        if(this.options.publishMode){
            this._$('div.mock-headers').hide();
            return;
        }
        this._$('div.mock-headers').show();

        $headers.css({
            'font-weight': 'bold',
            'text-align': 'center',
            padding: '10px 0px',
            width: '85px',
            display: 'inline-block',
            color: 'white',
            border: '1px solid lightgray',
            cursor: 'pointer'
        });

        this._on($headers, {
            click: (event) => {
                let option = $(event.target).is('img') ? 'explore' : $(event.target).attr('id');

                if(window.hWin.HEURIST4.util.isempty(option)){
                    option = $(event.target).parent().attr('id');
                }

                this.hide();
                
                if(option != 'explore'){
                    this._switchMenu(option[0].toLowerCase() + option.slice(1));
                }
            }
        })

        $exploreImg.attr('src', `${window.hWin.HAPI4.baseURL}${this._exploreImage}`);

        $flavourTexts.css({
            display: 'inline-block',
            'margin-left': '10px'
        });
    },

    _setupRecordDetails: function(){

        // "New" and "Add record"
        let $addNewRec = this._$('div.add-new');
        // Commonly used entities/rectypes
        let $usageContainer = this._$('ul#entity-usage')

        if(this.options.publishMode){
            $addNewRec.hide();
            $usageContainer.hide();
            return;
        }

        $addNewRec.css({
            background: 'lightgray',
            display: 'inline-block',
            'margin-left': '10px',
            padding: '10px',
            cursor: 'pointer'
        });

        this._on($addNewRec, {
            click: () => {
                if(this._mainMenu.length > 0){
                    this._mainMenu.find('li.menu-explore[data-action-popup="recordAdd"]').trigger('mouseenter');
                }
            },
            mouseenter: () => {
                if(this._mainMenu.length > 0){
                    this._mainMenu.find('li.menu-explore[data-action-popup="recordAdd"]').trigger('mouseenter');
                }
            },
            mouseleave: () => {
                if(this._mainMenu.length > 0){
                    this._mainMenu.find('li.menu-explore[data-action-popup="recordAdd"]').trigger('mouseleave');
                }
            }
        });

        $usageContainer.css({
            'list-style-type': 'none',
            'padding-left': '30px',
            'margin-top': '0px'
        });

        let options = {
            select_name: 'usage_select', 
            useCounts: true, 
            useGroups: false, 
            useHtmlSelect: true, 
            useIcons: true, 
            useIds: true, 
            ancor: null
        };

        let $entityUsage = window.hWin.HEURIST4.ui.createRectypeSelectNew(null, options);
        const rtyIconBG = `${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif`;

        $entityUsage.find('option').each(function(idx, item){

            if(idx >= 11){ // Max of 10
                return false;
            }

            let $opt = $(item);
            const count = $opt.attr('rt-count') >= 0 ? $opt.attr('rt-count') : '';

            const rtyID = $opt.attr('entity-id');
            const rtyIcon = $opt.attr('icon-url');
            const rtyName = $opt.text();

            let $li = $('<li>', {
                'data-id': rtyID,
                style: 'font-size:smaller;padding:4px 0px 2px 0px;'
            }).appendTo($usageContainer);

            $('<img>', {
                src: rtyIconBG,
                class: 'rt-icon',
                style: `vertical-align:bottom;background-image: url(&quot;${rtyIcon}&quot;);`
            }).appendTo($li);

            $('<div>', {
                class: 'menu-text truncate',
                style: 'max-width:13em;display:inline-block;',
                title: `Search for ${rtyName} records`,
                html: rtyName
            }).appendTo($li);

            $('<span>', {
                style: 'float:right;min-width:20px;margin-left:10px;',
                text: count
            }).appendTo($li);
        });

        $usageContainer.find('li[data-id]').on('click', (e) => this._searchByRecType(e));
    },

    updateDetails: function(){

        // Add DB Logo
        let $thumbContainer = this._$('div#db-thumb')
            .text('')
            .css({
                display: 'block',
                'margin-left': 'auto',
                'margin-right': 'auto',
                border: 'none',
                background: 'none'
            });

        const date = new Date();
        const thumbURL = `${window.hWin.HAPI4.getImageUrl('sysIdentification', 1, 'thumb', 1)}&t=${date.getTime()}`;

        $('<img>', {
            src: thumbURL,
            class: 'image_input'
        }).appendTo($thumbContainer);

        window.hWin.HAPI4.checkImage('sysIdentification', 1, 'thumb', (response) => {

            if(response.status != 'ok' || response.data != 'ok'){

                $thumbContainer.find('img').remove();

                $thumbContainer
                    .text('No Logo')
                    .css({
                        border: '1px solid gray', 
                        background: '#F4F2F4',
                        display: 'flex',
                        'justify-content': 'center',
                        'align-items': 'center',
                        width: 'fit-content',
                        color: 'gray'
                    });
            }
        });

        // Fill in System Identification info {DB Name, Owner, Copyright and Description}
        window.hWin.HAPI4.EntityMgr.getEntityData('sysIdentification', false, (response) => {
            
            if(window.hWin.HEURIST4.util.isempty(response)){
                this._updateStyles();
                return;
            }

            let record = response.getFirstRecord();

            let name = response.fld(record, 'sys_dbName');
            let ownership = response.fld(record, 'sys_dbOwner');
            let rights = response.fld(record, 'sys_dbRights');
            let desc = response.fld(record, 'sys_dbDescription');

            if(!window.hWin.HEURIST4.util.isempty(name) && name != 'Please enter a DB name ...'){
                this._$('h3#title').text(name);
            }else{
                this._$('h3#title').text('Database Title');
            }

            if(!window.hWin.HEURIST4.util.isempty(ownership)){
                this._$('span#owner').html(`Owner: ${ownership}`);
            }else{
                this._$('span#owner').html('Database Ownership');
            }

            if(!window.hWin.HEURIST4.util.isempty(rights) && rights != 'Please define ownership and rights here ...'){
                this._$('span#rights').html(`Rights: ${rights}`);
            }else{
                this._$('span#rights').html('Database Rights');
            }

            if(!window.hWin.HEURIST4.util.isempty(desc)){
                this._$('div#description').html(desc);
            }else{
                this._$('div#description').html('Database Description');
            }

            this._updateStyles();
        });
    },

    updateAccess: function(){

        // DB Logo container
        let $thumbContainer = this._$('div#db-thumb')
            .css({
                display: 'block',
                'margin-left': 'auto',
                'margin-right': 'auto'
            })
            .off('click');

        this._$('#title_cont, #owner_cont, #rights_cont').off('click');
        this._$('div#description, button#btnEdit').off('click');

        // Check if logged in user has access to DB Props
        if(window.hWin.HAPI4.has_access(1)){ // Has access

            this._$('h3#title, span#owner, span#rights')
                .parent()
                .css('cursor', 'pointer')
            
            this._$('#title_cont, #owner_cont, #rights_cont').one('click', (e) => this._openDBProperties(e));

            this._$('div#description')
                .css({'cursor': 'pointer', 'white-space': 'pre-wrap'})
                .on('click', (e) => this._openDBProperties(e));

            this._$('button#btnEdit')
                .prop('disabled', false)
                .button({label: 'Edit Metadata'})
                .addClass('ui-button-action')
                .css({
                    'display': 'inline-block'
                })
                .on('click', (e) => this._openDBProperties(e));

            $thumbContainer.on('click', (e) => this._openDBProperties(e));

        }else{ // Has no access

            this._$('h3#title, span#owner, span#rights')
                .parent()
                .css({'cursor': 'default', 'resize': 'none'});

            this._$('button#btnEdit')
                .hide()
                .prop('disabled', true);

            $thumbContainer.css('cursor', 'default');
        }
    },

    _updateStyles: function(){

        let owner = this._$('span#owner');
        let rights = this._$('span#rights');
        let desc = this._$('div#description');

        let ownerCSS = {
            background: '#F4F2F4',
            border: '1px solid gray',
            width: '90vw'
        };
        if(!window.hWin.HEURIST4.util.isempty(owner.html()) && owner.html() != 'Database Ownership'){
            ownerCSS = {
                background: 'white',
                border: 'none',
                width: 'auto'
            };
        }
        owner.parent().css(ownerCSS);

        let rightsCSS = {
            background: '#F4F2F4',
            border: '1px solid gray',
            width: '90vw'
        };
        if(!window.hWin.HEURIST4.util.isempty(rights.html()) && rights.html() != 'Database Rights'){
            rightsCSS = {
                background: 'white',
                border: 'none',
                width: 'auto'
            };
        }
        rights.parent().css(rightsCSS);

        let descCSS = {
            background: '#F4F2F4',
            border: '1px solid gray',
            width: '90vw'
        };
        if(!window.hWin.HEURIST4.util.isempty(desc.html()) && desc.html() != 'Database Description'){
            descCSS = {
                background: 'white',
                border: 'none',
                width: 'auto'
            };
        }
        desc.parent().css(descCSS);

        this.element.css({
            'z-index': 10,
            background: 'white'
        });
    },

    show: function(){
        this._switchMenu('explore');
        this.element.show();
        this._refresh();
    },
    hide: function(){
        this.element.hide();
    },

    _switchMenu: function(menu){

        if(this._mainMenu.length === 0 || this._mainMenu.slidersMenu('instance') === undefined){
            return;
        }

        this._mainMenu.slidersMenu('switchContainer', menu);
    },

    _openDBProperties: function(event){

        if($(event.target).is('a') || this._editingProperties){
            return;
        }

        this._editingProperties = true;

        window.hWin.HAPI4.SystemMgr.verify_credentials((e) => {

            window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification', {
                beforeClose: () => {
                    this._editingProperties = false;
                    this._refresh();
                }
            });

        }, 1);
    },

    _searchByRecType: function(event){

        let $ele = $(event.target).is('li') ? $(event.target) : $(event.target).closest('li');

        const rtyID = $ele.attr('data-id');

        if(!window.hWin.HEURIST4.util.isPositiveInt(rtyID)){
            return;
        }

        let request = {
            q: `t:${rtyID}`,
            w: 'a',
            qname: $Db.rty(rtyID, 'rty_Plural'),
            detail: 'ids'
        };

        window.hWin.HAPI4.RecordSearch.doSearch(this, request);

        this._switchMenu('explore');
    }
});