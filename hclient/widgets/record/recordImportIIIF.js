/**
* @file recordImportIIIF.js
* @brief Import a selected IIIF Manifest into Heurist Manifest, Canvas and Annotation records.
*/
$.widget( "heurist.recordImportIIIF", $.heurist.recordAction, {

    options: {
        height: 820,
        width:  840,
        modal:  true,
        title:  'Import IIIF Manifest',
        htmlContent: 'recordImportIIIF'
    },

    _init: function() {
        this.options.htmlContent = this.options.htmlContent+'.html';
        this._manifestFileId = null;
        this._super();
    },

    _initControls:function(){

        this._$('.btnAction').button();
        this._on(this._$('.btnAction'), {click:this.doAction});

        this._initManifestSelector();
        this.checkRequiredRecordTypes();

        return this._super();
    },

    checkRequiredRecordTypes: function(callback){

        let dbconst = (window.hWin.HAPI4.sysinfo && window.hWin.HAPI4.sysinfo['dbconst'])
                    ? window.hWin.HAPI4.sysinfo['dbconst'] : {};

        this.RT_IIIF_MANIFEST = dbconst['RT_IIIF_MANIFEST'];
        this.RT_IIIF_CANVAS = dbconst['RT_IIIF_CANVAS'];
        this.RT_IIIF_ANNOTATION = dbconst['RT_IIIF_ANNOTATION'];

        this.DT_IIIF_ID = dbconst['DT_IIIF_ID'];
        this.DT_IIIF_CANVAS = dbconst['DT_IIIF_CANVAS'];
        this.DT_ANNOTATION_STATE = dbconst['DT_ANNOTATION_STATE'];
        this.DT_ANNOTATION_INFO = dbconst['DT_ANNOTATION_INFO'];

        let missing = '';

        if (!(this.RT_IIIF_MANIFEST > 0 && this.RT_IIIF_CANVAS > 0 && this.RT_IIIF_ANNOTATION > 0)) {
            missing = 'You will need record types 2-110 (IIIF Manifest), 2-111 (IIIF Canvas) and 2-109 (IIIF Annotation)';
        }else{
            let hDb = window.hWin.$Db || (typeof $Db !== 'undefined' ? $Db : null);
            if(hDb && $.isFunction(hDb.rst)){
                if (!(this.DT_IIIF_ID > 0) || !hDb.rst(this.RT_IIIF_ANNOTATION, this.DT_IIIF_ID)) {
                    missing = 'You will need record type 2-109 (IIIF Annotation) with field DT_IIIF_ID (Original IIIF ID)';
                } else if (!(this.DT_IIIF_CANVAS > 0)
                    || !hDb.rst(this.RT_IIIF_MANIFEST, this.DT_IIIF_CANVAS)
                    || !hDb.rst(this.RT_IIIF_ANNOTATION, this.DT_IIIF_CANVAS)) {
                    missing = 'You will need field DT_IIIF_CANVAS on record types 2-110 (IIIF Manifest) and 2-109 (IIIF Annotation)';
                } else if (!(this.DT_ANNOTATION_STATE > 0)
                    || !hDb.rst(this.RT_IIIF_ANNOTATION, this.DT_ANNOTATION_STATE)
                    || !hDb.rst(this.RT_IIIF_CANVAS, this.DT_ANNOTATION_STATE)) {
                    missing = 'You will need field DT_ANNOTATION_STATE on record types 2-109 (IIIF Annotation) and 2-111 (IIIF Canvas)';
                } else if (!(this.DT_ANNOTATION_INFO > 0) || !hDb.rst(this.RT_IIIF_ANNOTATION, this.DT_ANNOTATION_INFO)) {
                    missing = 'You will need record type 2-109 (IIIF Annotation) with field DT_ANNOTATION_INFO (IIIF Annotation JSON)';
                }
            }
        }

        if (missing !== '') {
            window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('2-109', 2,
                missing + ' which are available as part of Heurist_Core_Definitions.',
                callback,
                true  // Force import
            );
            return false;
        }

        return true;
    },

    _initManifestSelector: function(){

        let ele = this._$('#manifest_selector');
        if(ele.length===0 || !$.isFunction(ele.editing_input)){
            // Fallback for older pages where editing_input is not loaded.
            ele.html('<input type="text" id="manifest_file_id" style="width:95%" placeholder="Registered manifest file ID or obfuscated file ID">');
            return;
        }

        let that = this;
        const ed_options = {
            recID: -1,
            readonly: false,
            showclear_button: true,
            dtFields:{
                dty_Type: 'file',
                rst_MaxValues: 1,
                rst_DisplayName: 'IIIF Manifest:',
                rst_DisplayHelpText: 'Select an existing registered IIIF Manifest JSON file, or upload/register a new one.',
                rst_FieldConfig: {entity:'records', accept:'.json', registerAtOnce:1},
                dty_Role: 'virtual'
            },
            change: function(event, data){
                that._manifestFileId = that._extractManifestFileId(data);
            }
        };

        ele.editing_input(ed_options);
    },

    _extractManifestFileId: function(data){
        if(data==null) return null;
        if(typeof data === 'string' || typeof data === 'number') return data;
        if($.isArray(data)) return data.length>0 ? this._extractManifestFileId(data[0]) : null;
        if(typeof data === 'object'){
            return data.ulf_ID || data.id || data.value || data.file || data.file_id || data.ulf_ObfuscatedFileID || null;
        }
        return null;
    },

    _getSelectedManifestFileId: function(){

        if(this._manifestFileId){
            return this._manifestFileId;
        }

        let fallback = this._$('#manifest_file_id');
        if(fallback.length>0 && fallback.val()){
            return fallback.val();
        }

        let ele = this._$('#manifest_selector');
        if(ele.length>0 && $.isFunction(ele.editing_input)){
            try{
                let val = ele.editing_input('getValues');
                return this._extractManifestFileId(val);
            }catch(e){
                try{
                    let val = ele.editing_input('getValue');
                    return this._extractManifestFileId(val);
                }catch(e2){}
            }
        }

        return null;
    },

    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Proceed');
        res[0].text = window.hWin.HR('Close');
        return res;
    },

    _esc: function(value){
        return $('<div>').text(value==null ? '' : String(value)).html();
    },

    _renderIdList: function(ele, ids){
        let link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=ids:';
        ids = ids || [];
        if(ids.length>0){
            ele.html(`<a href="${link+ids.join(',')}" target="_blank">${ids.length} <span class="ui-icon ui-icon-extlink">&nbsp;</span></a>`);
        }else{
            ele.text('0');
        }
    },

    _renderReport: function(data){

        this._$('#div_header').hide();
        this._$('#div_result').show();

        let link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=ids:';

        if(data.manifest_rec_id>0){
            this._$('#manifest_record').html(`<a href="${link+data.manifest_rec_id}" target="_blank">${data.manifest_rec_id}</a>`);
        }else{
            this._$('#manifest_record').text('not created (annotation overlay)');
        }

        this._$('#total_canvases').text(data.total_canvases || 0);
        this._renderIdList(this._$('#canvases_added'), data.canvases_added);
        this._renderIdList(this._$('#canvases_updated'), data.canvases_updated);
        this._renderIdList(this._$('#canvases_retained'), data.canvases_retained);
        this._renderIdList(this._$('#canvases_preserved_local'), data.canvases_preserved_local);

        this._$('#total_annotations').text(data.total_annotations || 0);
        this._$('#processed').text(data.processed || 0);
        this._$('#without_annotations').text(data.without_annotations ? 'yes' : 'no');

        this._renderIdList(this._$('#added'), data.added);
        this._renderIdList(this._$('#updated'), data.updated);
        this._renderIdList(this._$('#retained'), data.retained);
        this._renderIdList(this._$('#preserved_local'), data.preserved_local);

        let s = ' ';
        let issueCount = 0;
        if(data.issues){
            for(const key in data.issues){
                issueCount++;
                s += `<div><b>${this._esc(key)}:</b> ${this._esc(data.issues[key])}</div>`;
            }
        }
        this._$('#issues').html(issueCount>0 ? s : '');
    },

    doAction: function(){

        if(!this.checkRequiredRecordTypes(this.doAction.bind(this))){
            return;
        }

        let manifestFileId = this._getSelectedManifestFileId();
        if(window.hWin.HEURIST4.util.isempty(manifestFileId)){
            window.hWin.HEURIST4.msg.showMsgFlash('Please select or upload a IIIF Manifest JSON file');
            return;
        }

        let request = {
            db: window.hWin.HAPI4.database,
            controller: 'ImportAnnotations',
            session: window.hWin.HEURIST4.msg.showProgress(),
            manifest_file_id: manifestFileId,
            import_level: this._$('input[name="import_level"]:checked').val() || 'managed',
            create_thumb: this._$('#chb_create_thumbs').is(':checked')?1:0
        };

        let url = window.hWin.HAPI4.baseURL;
        let that = this;

        window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){
            window.hWin.HEURIST4.msg.sendCoverallToBack();
            window.hWin.HEURIST4.msg.hideProgress();

            if(response.status == window.hWin.ResponseStatus.OK){
                that._renderReport(response.data);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }
});
