/**
* recordFieldSetEditor.js - Dataset field-set editor
*
* Selects direct and linked record fields and edits their ordered presentation
* options using the common Dataset DT_DATA_FIELDS JSON contract.
*
* @project     Heurist academic knowledge management system
* @package     hclient.widgets.record
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/

$.widget('heurist.recordFieldSetEditor', $.heurist.recordAction, {

    options: {
        height: 760,
        width: 1200,
        modal: true,
        title: 'Define field set',
        htmlContent: 'recordFieldSetEditor.html',
        scope_types: 'none',
        recordTypeId: null,
        value: null,
        maxLinkDepth: 3,
        helpContent: false
    },

    _selectedRtyID: null,
    _initialFields: null,
    _dirty: false,
    _syncingTree: false,

    _create: function(){
        this._initialFields = this._parseValue(this.options.value);
        if(!this.options.recordTypeId){
            this.options.recordTypeId = this._inferRecordType(this._initialFields);
        }
        this._super();
    },

    _initControls: function(){
        if(this._super() === false){ return false; }

        const that = this;
        this._$('.fieldset-fields tbody').sortable({
            handle: '.fieldset-drag',
            axis: 'y',
            update: function(){ that._markDirty(); }
        });

        this._on(this._$('.fieldset-show-field'), {change:function(event){
            this._$('.field-code-column').toggle($(event.currentTarget).is(':checked'));
        }});

        this._on(this._$('.fieldset-clear-all'), {click:function(){
            if(this._$('.fieldset-fields tbody tr').length===0){ return; }
            window.hWin.HEURIST4.msg.showMsgDlg(
                '<br>Clear all selected fields?',
                function(){ that._clearFields(); }
            );
        }});

        this._on(this._$('[name="tree_order"]'), {change:function(){
            sessionStorage.setItem('heurist_ftorder_fieldset', $(this).val());
            that._loadRecordTypesTreeView(that._selectedRtyID, true);
        }});

        const beforeClose = function(){
            if(!that._dirty || that._context_on_close){ return true; }
            return window.confirm(window.hWin.HR('Discard unsaved field-set changes?'));
        };
        this.options.beforeClose = beforeClose;
        if(this._as_dialog){ this._as_dialog.dialog('option','beforeClose',beforeClose); }

        this._renderInitialFields();
        return true;
    },

    _getActionButtons: function(){
        const buttons = this._super();
        buttons[0].text = window.hWin.HR('Cancel');
        buttons[1].text = window.hWin.HR('Apply');
        buttons[1].disabled = false;
        return buttons;
    },

    doAction: function(){
        const fields = this.getFields();
        if(fields.length===0){
            window.hWin.HEURIST4.msg.showMsgFlash(
                'No fields selected. Please select at least one field.', 2000
            );
            return;
        }
        this._context_on_close = {fields:fields};
        this._dirty = false;
        this.closeDialog(true);
    },

    _fillSelectRecordScope: function(){
        const select = this.selectRecordScope.empty().get(0);
        window.hWin.HEURIST4.ui.createRectypeSelect(
            select, null, 'select record type …', true
        );
        this._on(this.selectRecordScope, {change:this._onRecordScopeChange});
        if(this.options.recordTypeId){ this.selectRecordScope.val(String(this.options.recordTypeId)); }
        window.hWin.HEURIST4.ui.initHSelect(select);
        this._onRecordScopeChange(true);
    },

    _onRecordScopeChange: function(initial){
        const isInitial = initial===true;
        const next = String(this.selectRecordScope.val() || '');
        const current = String(this._selectedRtyID || '');
        const hasFields = this._$('.fieldset-fields tbody tr').length>0;

        if(!isInitial && current && next!==current && hasFields &&
           !window.confirm(window.hWin.HR('Changing record type will clear selected fields. Continue?'))){
            this.selectRecordScope.val(current);
            if(this.selectRecordScope.hSelect('instance')){ this.selectRecordScope.hSelect('refresh'); }
            return;
        }
        if(!isInitial && current && next!==current && hasFields){ this._clearFields(false); }
        this._loadRecordTypesTreeView(next, false);
    },

    _loadRecordTypesTreeView: function(rtyID, preserveRows){
        this._selectedRtyID = rtyID;
        const treeDiv = this._$('.rtt-tree');
        if(!treeDiv.is(':empty') && treeDiv.fancytree('instance')){ treeDiv.fancytree('destroy'); }
        treeDiv.empty();
        if(!rtyID){ return; }

        let order = Number(sessionStorage.getItem('heurist_ftorder_fieldset'));
        if(order!==1){ order = 0; }
        this._$('[name="tree_order"][value="'+order+'"]').prop('checked', true);

        const data = window.hWin.HEURIST4.dbs.createRectypeStructureTree(
            null, 6, rtyID, ['header_ext','all','parent_link'], null, order
        );
        if(!data || !data.length){ return; }
        data[0].expanded = true;

        const that = this;
        treeDiv.addClass('tree-csv').fancytree({
            checkbox: true,
            selectMode: 3,
            source: data,
            beforeSelect: function(event, treeData){
                if(treeData.node.hasChildren()){
                    treeData.node.setExpanded(!treeData.node.isExpanded());
                    return false;
                }
            },
            renderNode: function(event, treeData){
                if(treeData.node.data.is_generic_fields){
                    $(treeData.node.span.childNodes[1]).hide();
                }
                if(treeData.node.type==='separator'){
                    $(treeData.node.span).css({background:'none',color:'black'});
                    $(treeData.node.span.childNodes[1]).hide();
                }
            },
            lazyLoad: function(event, treeData){
                const node = treeData.node;
                const parentCode = node.data.code;
                const recordTypes = node.data.rt_ids;
                const depth = String(parentCode || '').split(':').length;
                if(depth < that.options.maxLinkDepth*2){
                    const result = window.hWin.HEURIST4.dbs.createRectypeStructureTree(
                        null, 6, recordTypes, ['header_ext','all','parent_link'], parentCode, order
                    );
                    treeData.result = result.length>1 ? result : (result[0] ? result[0].children : []);
                }else{
                    treeData.result = [];
                }
                return treeData;
            },
            loadChildren: function(){ that._syncTreeSelection(); },
            select: function(event, treeData){
                if(that._syncingTree || treeData.node.type==='separator'){ return; }
                const code = that._normaliseFieldCode(treeData.node.data.code);
                if(!code){ return; }
                if(treeData.node.isSelected()){
                    that._addFieldRow({
                        field:code,
                        title:treeData.node.data.name || treeData.node.title,
                        visible:true
                    }, treeData.node.type);
                }else{
                    that._removeFieldRow(code, false);
                }
            },
            click: function(event, treeData){
                if(treeData.node.type==='separator'){ return false; }
                const target = $(event.originalEvent.target);
                if(target.hasClass('fancytree-expander')){ return; }
                if(treeData.node.children || treeData.node.lazy){
                    treeData.node.setExpanded(!treeData.node.isExpanded());
                }
            },
            dblclick: function(event, treeData){
                if(treeData.node.type!=='separator'){ treeData.node.toggleSelected(); }
            },
            keydown: function(event, treeData){
                if(event.which===32){ treeData.node.toggleSelected(); return false; }
            }
        });

        if(preserveRows!==false){ this._syncTreeSelection(); }
    },

    _renderInitialFields: function(){
        const fields = this._initialFields || [];
        fields.forEach(field => this._addFieldRow(field, this._fieldType(field.field), false));
        this._syncTreeSelection();
        this._dirty = false;
        this._updateEmptyState();
    },

    _addFieldRow: function(field, type, markDirty){
        const code = this._normaliseFieldCode(field.field);
        if(!code || this._findRow(code).length){ return; }
        type = type || this._fieldType(code);

        const title = field.title || this._fieldTitle(code);
        const row = $('<tr>')
            .attr({'data-field':code,'data-type':type || ''})
            .appendTo(this._$('.fieldset-fields tbody'));

        $('<td>').append($('<span class="ui-icon ui-icon-arrowthick-2-n-s fieldset-drag">')
            .css('cursor','ns-resize')).appendTo(row);
        $('<td>').append($('<input type="checkbox" class="field-visible">')
            .prop('checked', field.visible!==false)).appendTo(row);
        $('<td class="field-code field-code-column">')
            .text(this._fieldTitle(code)).attr('title',code)
            .css({'display':this._$('.fieldset-show-field').is(':checked') ? 'table-cell' : 'none',
                  'white-space':'nowrap','overflow':'hidden','text-overflow':'ellipsis'})
            .appendTo(row);
        $('<td>').append($('<input type="text" class="field-title text ui-widget-content ui-corner-all">')
            .val(title).css('width','96%')).appendTo(row);

        const width = $('<select class="field-width">')
            .append('<option value="">Auto</option><option>5</option><option>10</option><option>20</option><option>30</option><option>50</option><option>100</option><option>200</option><option>300</option><option>400</option><option>500</option>')
            .val(field.width || '');
        $('<td>').append(width).appendTo(row);

        const ext = $('<select class="field-ext">');
        this._populateExt(ext, type, field.ext);
        ext.css('width','92px');
        $('<td>').append(ext).appendTo(row);

        const aggregation = $('<select class="field-aggregation">');
        this._populateAggregation(aggregation, type, field.aggregation);
        aggregation.css('width','88px');
        $('<td>').append(aggregation).appendTo(row);

        $('<td>').append($('<span class="ui-icon ui-icon-circlesmall-close field-remove">')
            .attr('title','Remove field').css('cursor','pointer')).appendTo(row);

        this._on(row.find('input,select'), {change:function(){ this._markDirty(); }});
        this._on(row.find('.field-title'), {keyup:function(){ this._markDirty(); }});
        this._on(row.find('.field-remove'), {click:function(){ this._removeFieldRow(code, true); }});
        this._updateEmptyState();
        if(markDirty!==false){ this._markDirty(); }
    },

    _removeFieldRow: function(code, syncTree){
        this._findRow(code).remove();
        if(syncTree!==false){ this._setTreeFieldSelected(code, false); }
        this._updateEmptyState();
        this._markDirty();
    },

    _clearFields: function(markDirty){
        this._$('.fieldset-fields tbody').empty();
        const tree = $.ui.fancytree.getTree(this._$('.rtt-tree'));
        if(tree){
            this._syncingTree = true;
            tree.visit(node => { if(node.isSelected()){ node.setSelected(false); } });
            this._syncingTree = false;
        }
        this._updateEmptyState();
        if(markDirty!==false){ this._markDirty(); }
    },

    getFields: function(){
        const fields = [];
        this._$('.fieldset-fields tbody tr').each(function(){
            const row = $(this);
            const item = {
                field:String(row.attr('data-field')),
                visible:row.find('.field-visible').is(':checked'),
                title:$.trim(row.find('.field-title').val() || '')
            };
            const width = row.find('.field-width').val();
            const ext = row.find('.field-ext').val();
            const aggregation = row.find('.field-aggregation').val();
            if(width){ item.width = String(width); }
            if(ext){ item.ext = String(ext); }
            if(aggregation){ item.aggregation = String(aggregation); }
            fields.push(item);
        });
        return fields;
    },

    _populateExt: function(select, type, value){
        const options = {
            enum:[['term','Term'],['code','Code'],['conceptid','Concept ID'],['id','Internal term ID']],
            relationtype:[['term','Term'],['code','Code'],['conceptid','Concept ID'],['id','Internal term ID']],
            file:[['url','URL'],['thumb','Thumbnail'],['id','Obfuscated file ID']],
            geo:[['wkt','WKT'],['geojson','GeoJSON'],['pair','Latitude, longitude']],
            date:[['iso','ISO'],['human','Human-readable'],['raw','Raw temporal']]
        };
        select.append('<option value="">Default</option>');
        (options[type] || []).forEach(option =>
            $('<option>').val(option[0]).text(option[1]).appendTo(select));
        select.val(value || '').prop('disabled', !options[type]);
    },

    _populateAggregation: function(select, type, value){
        select.append('<option value="">None</option><option value="count">Count</option>');
        if(type==='float' || type==='integer'){
            select.append('<option value="sum">Sum</option><option value="avg">Average</option><option value="min">Minimum</option><option value="max">Maximum</option>');
        }else if(type==='date'){
            select.append('<option value="min">Earliest</option><option value="max">Latest</option>');
        }
        select.val(value || '');
    },

    _parseValue: function(value){
        if(!value){ return []; }
        let parsed = value;
        if(typeof parsed==='string'){
            try{ parsed = JSON.parse(parsed); }
            catch(ignore){ parsed = {fields:parsed.split(',')}; }
        }
        const fields = Array.isArray(parsed) ? parsed : parsed.fields;
        if(!Array.isArray(fields)){ return []; }
        return fields.map(item => typeof item==='string' ? {field:item} : $.extend({},item))
            .filter(item => item && item.field);
    },

    _normaliseFieldCode: function(code){
        code = $.trim(String(code || ''));
        if(!code){ return ''; }
        const parts = code.split(':');
        const terminal = parts[parts.length-1].toLowerCase();
        const headers = {
            id:'rec_ID', typeid:'rec_RecTypeID', title:'rec_Title', url:'rec_URL',
            scratchpad:'rec_ScratchPad', owner:'rec_OwnerUGrpID', visibility:'rec_NonOwnerVisibility',
            added:'rec_Added', modified:'rec_Modified', addedby:'rec_AddedByUGrpID', hash:'rec_Hash'
        };
        return headers[terminal] || code;
    },

    _fieldType: function(code){
        if(String(code).indexOf('rec_')===0){
            return /Added|Modified/.test(code) ? 'date' : 'freetext';
        }
        const terminal = String(code).split(':').pop();
        return /^\d+$/.test(terminal) ? ($Db.dty(terminal,'dty_Type') || '') : '';
    },

    _fieldTitle: function(code){
        if(String(code).indexOf('rec_')===0){
            const titles = {rec_ID:'Record H-ID',rec_RecTypeID:'Record type',rec_Title:'Record title',rec_URL:'Record URL',rec_Added:'Added',rec_Modified:'Modified'};
            return titles[code] || String(code).replace(/^rec_/,'').replace(/_/g,' ');
        }
        const hierarchy = $Db.getHierarchyTitles(code);
        if(hierarchy && hierarchy.harchy){
            return $('<div>').html(hierarchy.harchy.join('')).text();
        }
        const terminal = String(code).split(':').pop();
        return $Db.dty(terminal,'dty_Name') || String(code);
    },

    _inferRecordType: function(fields){
        for(let index=0; index<fields.length; index++){
            const match = String(fields[index].field || '').match(/^(\d+):/);
            if(match){ return match[1]; }
        }
        return null;
    },

    _findRow: function(code){
        return this._$('.fieldset-fields tbody tr').filter(function(){
            return String($(this).attr('data-field'))===String(code);
        });
    },

    _setTreeFieldSelected: function(code, selected){
        const tree = $.ui.fancytree.getTree(this._$('.rtt-tree'));
        if(!tree){ return; }
        this._syncingTree = true;
        tree.visit(node => {
            if(this._normaliseFieldCode(node.data.code)===code){ node.setSelected(selected); }
        });
        this._syncingTree = false;
    },

    _syncTreeSelection: function(){
        const tree = $.ui.fancytree.getTree(this._$('.rtt-tree'));
        if(!tree){ return; }
        const selected = {};
        this._$('.fieldset-fields tbody tr').each(function(){ selected[$(this).attr('data-field')] = true; });
        this._syncingTree = true;
        tree.visit(node => {
            const code = this._normaliseFieldCode(node.data.code);
            if(code && selected[code] && !node.isSelected()){ node.setSelected(true); }
        });
        this._syncingTree = false;
    },

    _updateEmptyState: function(){
        this._$('.fieldset-empty').toggle(this._$('.fieldset-fields tbody tr').length===0);
    },

    _markDirty: function(){ this._dirty = true; }
});
